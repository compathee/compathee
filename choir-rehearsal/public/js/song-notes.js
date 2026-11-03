(function () {
	'use strict';

	const cfg = window.choirRehearsalNotes;
	if (!cfg || !cfg.songId) {
		return;
	}

	const layer = document.getElementById('choir-notes-layer');
	const sheet = document.getElementById('choir-song-notes');
	const listEl = sheet ? sheet.querySelector('.choir-notes-list') : null;
	const emptyEl = sheet ? sheet.querySelector('.choir-notes-empty') : null;
	const titleEl = document.getElementById('choir-notes-title');
	const form = sheet ? sheet.querySelector('.choir-notes-form') : null;
	const input = document.getElementById('choir-notes-input');
	const picker = sheet ? sheet.querySelector('.choir-notes-picker') : null;
	const guide = document.getElementById('choir-notes-guide');
	const infoBtn = sheet ? sheet.querySelector('.choir-notes-info') : null;
	const publishBtn = sheet ? sheet.querySelector('.choir-notes-publish') : null;
	const cancelBtn = sheet ? sheet.querySelector('.choir-notes-cancel') : null;
	const errorEl = sheet ? sheet.querySelector('.choir-notes-error') : null;
	const micBtn = sheet ? sheet.querySelector('.choir-notes-mic') : null;
	const i18n = cfg.i18n || {};
	const tracks = Array.isArray(cfg.tracks) ? cfg.tracks : [];

	if (!layer || !sheet || !listEl) {
		return;
	}

	let notes = [];
	let mode = 'song';
	let activeTrackId = 0;
	let editingId = 0;
	let pendingAt = null;
	let scrollY = 0;
	let recognition = null;
	let listening = false;
	let dragStartY = 0;
	let dragging = false;

	function t(key, fallback) {
		return i18n[key] || fallback;
	}

	function draftKey() {
		return 'choir-notes-draft-' + cfg.songId + (editingId ? '-edit-' + editingId : '');
	}

	function saveDraft() {
		if (!input || !window.localStorage) {
			return;
		}
		try {
			const value = input.value;
			if ('' === value.trim()) {
				window.localStorage.removeItem(draftKey());
				return;
			}
			window.localStorage.setItem(draftKey(), value);
		} catch (err) {
			// Private mode can block storage. The note stays in the field.
		}
	}

	function restoreDraft() {
		if (!input || !window.localStorage) {
			return null;
		}
		try {
			return window.localStorage.getItem(draftKey());
		} catch (err) {
			return null;
		}
	}

	function clearDraft() {
		if (!window.localStorage) {
			return;
		}
		try {
			window.localStorage.removeItem(draftKey());
		} catch (err) {
			// Ignore storage failures.
		}
	}

	function showError(message) {
		if (!errorEl) {
			return;
		}
		errorEl.textContent = message || '';
		errorEl.hidden = !message;
	}

	function headers(write) {
		const result = { Accept: 'application/json' };
		if (write) {
			result['Content-Type'] = 'application/json';
		}
		if (cfg.nonce) {
			result['X-WP-Nonce'] = cfg.nonce;
		}
		if (write && cfg.notesNonce) {
			result['X-Choir-Notes-Nonce'] = cfg.notesNonce;
		}
		return result;
	}

	function applyCounts(counts) {
		const map = counts && typeof counts === 'object' ? counts : {};
		document.querySelectorAll('.choir-track-notes').forEach(function (button) {
			const id = button.getAttribute('data-track-id') || '';
			const total = parseInt(map[id], 10) || 0;
			button.hidden = total < 1;
		});
	}

	function visibleNotes() {
		if ('track' === mode) {
			return notes.filter(function (note) {
				return parseInt(note.track_id, 10) === activeTrackId;
			});
		}
		return notes.filter(function (note) {
			return !parseInt(note.track_id, 10);
		});
	}

	function trackById(id) {
		const wanted = parseInt(id, 10) || 0;
		for (let i = 0; i < tracks.length; i++) {
			if (parseInt(tracks[i].id, 10) === wanted) {
				return tracks[i];
			}
		}
		return null;
	}

	function renderNote(note) {
		const item = document.createElement('article');
		item.className = 'choir-note';
		item.setAttribute('data-note-id', String(note.id));

		const meta = document.createElement('p');
		meta.className = 'choir-note__meta';
		const author = document.createElement('span');
		author.className = 'choir-note__author';
		author.textContent = note.author || '';
		meta.appendChild(author);
		if (note.created_at) {
			const time = document.createElement('time');
			time.dateTime = note.created_at;
			const when = new Date(note.created_at);
			time.textContent = isNaN(when.getTime()) ? '' : when.toLocaleString();
			meta.appendChild(time);
		}
		item.appendChild(meta);

		const body = document.createElement('p');
		body.className = 'choir-note__body';
		const segments = Array.isArray(note.segments) ? note.segments : [{ type: 'text', text: note.body || '' }];
		const addressed = trackById(note.track_id);
		segments.forEach(function (part) {
			if ('time' === part.type && addressed && addressed.audioUrl && window.choirPlayer) {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'choir-note__time';
				button.textContent = part.label || part.text || '';
				button.setAttribute('aria-label', (t('seekTime', 'Seek to this time')) + ' ' + button.textContent);
				button.addEventListener('click', function () {
					window.choirPlayer.seek(part.seconds || 0, addressed.audioUrl, addressed.title, addressed.id);
				});
				body.appendChild(button);
				return;
			}
			if ('mention' === part.type) {
				const mark = document.createElement('span');
				mark.className = 'choir-note__mention';
				mark.textContent = part.text || '';
				body.appendChild(mark);
				return;
			}
			body.appendChild(document.createTextNode(part.text || ''));
		});
		item.appendChild(body);

		if (cfg.canWrite && form) {
			const actions = document.createElement('div');
			actions.className = 'choir-note__actions';
			const edit = document.createElement('button');
			edit.type = 'button';
			edit.className = 'choir-note__edit';
			edit.textContent = t('edit', 'Edit note');
			edit.addEventListener('click', function () {
				startEdit(note);
			});
			const remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'choir-note__delete';
			remove.textContent = t('delete', 'Delete note');
			remove.addEventListener('click', function () {
				removeNote(note);
			});
			actions.appendChild(edit);
			actions.appendChild(remove);
			item.appendChild(actions);
		}

		return item;
	}

	function renderList() {
		const rows = visibleNotes();
		listEl.textContent = '';
		rows.forEach(function (note) {
			listEl.appendChild(renderNote(note));
		});
		if (emptyEl) {
			emptyEl.hidden = rows.length > 0;
			emptyEl.textContent = 'track' === mode ? t('emptyTrack', 'No recommendations for this track yet.') : t('emptySong', 'No song notes yet.');
		}
	}

	function setTitle() {
		if (!titleEl) {
			return;
		}
		if ('track' === mode) {
			const track = trackById(activeTrackId);
			titleEl.textContent = track && track.label
				? t('trackTitle', 'Track recommendations') + ' — ' + track.label
				: t('trackTitle', 'Track recommendations');
			return;
		}
		titleEl.textContent = t('songTitle', 'Song notes');
	}

	function lockPage() {
		scrollY = window.scrollY || window.pageYOffset || 0;
		document.body.style.top = '-' + scrollY + 'px';
		document.body.classList.add('choir-notes-lock');
	}

	function unlockPage() {
		document.body.classList.remove('choir-notes-lock');
		document.body.style.top = '';
		window.scrollTo(0, scrollY);
	}

	function closePicker() {
		if (picker) {
			picker.hidden = true;
			picker.textContent = '';
		}
		pendingAt = null;
	}

	function openSheet() {
		stopDictation();
		layer.hidden = false;
		sheet.hidden = false;
		lockPage();
		setTitle();
		renderList();
		if (input && !editingId) {
			const saved = restoreDraft();
			if (null !== saved) {
				input.value = saved;
			}
		}
		const body = sheet.querySelector('.choir-notes-sheet__body');
		if (body) {
			body.scrollTop = 0;
		}
		const closeBtn = sheet.querySelector('.choir-notes-close');
		if (closeBtn) {
			closeBtn.focus();
		}
	}

	function closeSheet() {
		saveDraft();
		stopDictation();
		closeLangPopover();
		closePicker();
		sheet.hidden = true;
		layer.hidden = true;
		sheet.style.transform = '';
		unlockPage();
		showError('');
	}

	function openSong() {
		mode = 'song';
		activeTrackId = 0;
		cancelEdit(false);
		openSheet();
		loadNotes();
	}

	function openTrack(trackId) {
		mode = 'track';
		activeTrackId = parseInt(trackId, 10) || 0;
		cancelEdit(false);
		openSheet();
		loadNotes();
	}

	function loadNotes() {
		return fetch(cfg.restUrl, { credentials: 'same-origin', headers: headers(false) })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('load');
				}
				return response.json();
			})
			.then(function (data) {
				notes = Array.isArray(data.notes) ? data.notes : [];
				applyCounts(data.counts);
				if (!sheet.hidden) {
					renderList();
				}
			})
			.catch(function () {
				if (!sheet.hidden) {
					showError(t('loadFailed', 'Could not load song notes. Please try again.'));
				}
			});
	}

	function mentionedTrackId(value) {
		const labels = tracks.slice().sort(function (a, b) {
			return String(b.label || '').length - String(a.label || '').length;
		});
		for (let i = 0; i < value.length; i++) {
			if ('@' !== value.charAt(i)) {
				continue;
			}
			const prev = i > 0 ? value.charAt(i - 1) : '';
			if (i > 0 && !/\s/.test(prev)) {
				continue;
			}
			for (let n = 0; n < labels.length; n++) {
				const label = String(labels[n].label || '');
				if (!label) {
					continue;
				}
				const token = '@' + label;
				if (value.substr(i, token.length) !== token) {
					continue;
				}
				const after = value.charAt(i + token.length);
				if ('' === after || /\s/.test(after)) {
					return parseInt(labels[n].id, 10) || 0;
				}
			}
		}
		return 0;
	}

	function formatClock(total) {
		let seconds = Math.floor(Number(total) || 0);
		if (seconds < 0) {
			seconds = 0;
		}
		const hours = Math.floor(seconds / 3600);
		const mins = Math.floor((seconds % 3600) / 60);
		const secs = seconds % 60;
		const pad = function (num) {
			return String(num).padStart(2, '0');
		};
		if (hours > 0) {
			return hours + ':' + pad(mins) + ':' + pad(secs);
		}
		return mins + ':' + pad(secs);
	}

	function insertText(text, replaceAt) {
		if (!input) {
			return;
		}
		const start = input.selectionStart || 0;
		const end = input.selectionEnd || 0;
		let from = start;
		if (typeof replaceAt === 'number' && replaceAt >= 0 && replaceAt <= start) {
			from = replaceAt;
		}
		const value = input.value;
		input.value = value.slice(0, from) + text + value.slice(end);
		const caret = from + text.length;
		input.setSelectionRange(caret, caret);
		input.focus();
		saveDraft();
	}

	function openPicker() {
		if (!picker) {
			return;
		}
		picker.textContent = '';
		if (!tracks.length) {
			const empty = document.createElement('p');
			empty.className = 'choir-notes-picker__empty';
			empty.textContent = t('noTracks', 'No tracks uploaded yet.');
			picker.appendChild(empty);
			picker.hidden = false;
			return;
		}
		const label = document.createElement('p');
		label.className = 'choir-notes-picker__label';
		label.textContent = t('choosePart', 'Choose a voice part');
		picker.appendChild(label);
		tracks.forEach(function (track) {
			const button = document.createElement('button');
			const name = document.createElement('span');
			const label = track.label || '';
			button.type = 'button';
			button.className = 'choir-notes-picker__item';
			name.className = 'choir-notes-picker__name';
			name.textContent = label;
			button.appendChild(name);
			button.addEventListener('click', function () {
				const token = '@' + (track.label || '') + ' ';
				insertText(token, pendingAt);
				closePicker();
			});
			picker.appendChild(button);
		});
		picker.hidden = false;
	}

	function insertTime() {
		let seconds = 0;
		const mentioned = input ? mentionedTrackId(input.value) : 0;
		if (window.choirPlayer && mentioned && window.choirPlayer.currentTrackId() === mentioned) {
			seconds = window.choirPlayer.currentTime();
		}
		insertText(formatClock(seconds) + ' ');
	}

	function startEdit(note) {
		if (!input || !publishBtn) {
			return;
		}
		editingId = parseInt(note.id, 10) || 0;
		const saved = restoreDraft();
		input.value = null !== saved && '' !== saved ? saved : (note.body || '');
		publishBtn.textContent = t('save', 'Save');
		if (cancelBtn) {
			cancelBtn.hidden = false;
		}
		input.focus();
	}

	function cancelEdit(clearField) {
		editingId = 0;
		if (publishBtn) {
			publishBtn.textContent = t('publish', 'Publish');
		}
		if (cancelBtn) {
			cancelBtn.hidden = true;
		}
		if (clearField && input) {
			input.value = '';
		}
		showError('');
	}

	function removeNote(note) {
		if (!window.confirm(t('confirmDelete', 'Delete this note?'))) {
			return;
		}
		const id = parseInt(note.id, 10) || 0;
		fetch(cfg.restUrl + '/' + id, { method: 'DELETE', credentials: 'same-origin', headers: headers(true) })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('delete');
				}
				return response.json();
			})
			.then(function (data) {
				notes = notes.filter(function (row) {
					return parseInt(row.id, 10) !== id;
				});
				applyCounts(data.counts);
				if (editingId === id) {
					cancelEdit(true);
				}
				renderList();
			})
			.catch(function () {
				showError(t('deleteFailed', 'Could not delete the note. Please try again.'));
			});
	}

	function submitNote(event) {
		event.preventDefault();
		if (!input) {
			return;
		}
		stopDictation();
		const body = input.value.trim();
		if (!body) {
			showError(t('saveFailed', 'Could not save the note. Please try again.'));
			return;
		}
		const editing = editingId;
		const url = editing ? cfg.restUrl + '/' + editing : cfg.restUrl;
		fetch(url, {
			method: editing ? 'PUT' : 'POST',
			credentials: 'same-origin',
			headers: headers(true),
			body: JSON.stringify({ body: body }),
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('save');
				}
				return response.json();
			})
			.then(function (data) {
				clearDraft();
				if (editing) {
					notes = notes.map(function (row) {
						return parseInt(row.id, 10) === editing && data.note ? data.note : row;
					});
				} else if (data.note) {
					notes.push(data.note);
				}
				applyCounts(data.counts);
				input.value = '';
				cancelEdit(false);
				renderList();
			})
			.catch(function () {
				showError(t('saveFailed', 'Could not save the note. Please try again.'));
			});
	}

	const SPEECH_KEY = 'choir-notes-speech-lang';
	const SPEECH_DEFAULT = 'en-US';
	const SPEECH_PINNED = ['en-US', 'et-EE', 'ru-RU'];
	const SPEECH_CODES = [
		'af-ZA', 'am-ET', 'ar-AE', 'ar-BH', 'ar-DZ', 'ar-EG', 'ar-IQ', 'ar-JO', 'ar-KW', 'ar-LB', 'ar-MA', 'ar-OM', 'ar-QA', 'ar-SA', 'ar-TN',
		'az-AZ', 'bg-BG', 'bn-BD', 'bn-IN', 'ca-ES', 'cs-CZ', 'da-DK', 'de-DE', 'el-GR',
		'en-AU', 'en-CA', 'en-GB', 'en-IN', 'en-US',
		'es-AR', 'es-CL', 'es-CO', 'es-ES', 'es-MX', 'es-US',
		'et-EE', 'eu-ES', 'fa-IR', 'fi-FI', 'fil-PH', 'fr-CA', 'fr-FR', 'gl-ES', 'gu-IN', 'he-IL', 'hi-IN', 'hr-HR', 'hu-HU',
		'hy-AM', 'id-ID', 'is-IS', 'it-IT', 'ja-JP', 'jv-ID', 'ka-GE', 'km-KH', 'kn-IN', 'ko-KR', 'lo-LA', 'lt-LT', 'lv-LV',
		'ml-IN', 'mr-IN', 'ms-MY', 'nb-NO', 'ne-NP', 'nl-NL', 'pl-PL', 'pt-BR', 'pt-PT', 'ro-RO', 'ru-RU',
		'si-LK', 'sk-SK', 'sl-SI', 'sr-RS', 'su-ID', 'sv-SE', 'sw-KE', 'ta-IN', 'te-IN', 'th-TH', 'tr-TR', 'uk-UA', 'ur-PK', 'vi-VN',
		'zh-CN', 'zh-HK', 'zh-TW', 'zu-ZA'
	];
	const langPop = document.getElementById('choir-notes-langpop');
	const langSearch = langPop ? langPop.querySelector('.choir-notes-langsearch') : null;
	const langList = document.getElementById('choir-notes-langlist');
	const speechNote = sheet.querySelector('.choir-notes-speech-unsupported');
	let langActive = -1;

	function speechSupported() {
		return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
	}

	function languageLabel(code) {
		const parts = code.split('-');
		try {
			const locale = document.documentElement.lang || 'en';
			const languages = new Intl.DisplayNames([locale, 'en'], { type: 'language' });
			const regions = new Intl.DisplayNames([locale, 'en'], { type: 'region' });
			const language = languages.of(parts[0]);
			if (parts[1]) {
				return language + ' (' + regions.of(parts[1]) + ')';
			}
			return language || code;
		} catch (err) {
			return code;
		}
	}

	function savedSpeechLang() {
		try {
			const saved = window.localStorage.getItem(SPEECH_KEY);
			if (saved && SPEECH_CODES.indexOf(saved) !== -1) {
				return saved;
			}
		} catch (err) {
			return '';
		}
		return '';
	}

	function currentSpeechLang() {
		return savedSpeechLang() || SPEECH_DEFAULT;
	}

	function rememberSpeechLang(code) {
		try {
			window.localStorage.setItem(SPEECH_KEY, code);
		} catch (err) {
			// The choice still applies for this recording.
		}
	}

	function visibleLangOptions() {
		return langList ? Array.prototype.slice.call(langList.querySelectorAll('[role="option"]')) : [];
	}

	function setActiveLang(index) {
		const options = visibleLangOptions();
		if (!options.length) {
			langActive = -1;
			return;
		}
		langActive = Math.max(0, Math.min(index, options.length - 1));
		options.forEach(function (option, i) {
			const on = i === langActive;
			option.classList.toggle('is-active', on);
			option.setAttribute('aria-selected', on ? 'true' : 'false');
			if (on) {
				option.scrollIntoView({ block: 'nearest' });
				if (langSearch) {
					langSearch.setAttribute('aria-activedescendant', option.id);
				}
			}
		});
	}

	function renderSpeechLanguages(query) {
		if (!langList) {
			return;
		}
		const needle = String(query || '').trim().toLowerCase();
		const chosen = currentSpeechLang();
		langActive = -1;
		const items = SPEECH_CODES.map(function (code) {
			return { code: code, label: languageLabel(code) };
		}).filter(function (item) {
			if (!needle) {
				return true;
			}
			return item.label.toLowerCase().indexOf(needle) !== -1 || item.code.toLowerCase().indexOf(needle) !== -1;
		});
		items.sort(function (a, b) {
			const ap = SPEECH_PINNED.indexOf(a.code);
			const bp = SPEECH_PINNED.indexOf(b.code);
			if (ap !== -1 || bp !== -1) {
				if (ap === -1) {
					return 1;
				}
				if (bp === -1) {
					return -1;
				}
				return ap - bp;
			}
			return a.label.localeCompare(b.label);
		});
		langList.textContent = '';
		if (!items.length) {
			const empty = document.createElement('li');
			empty.className = 'choir-notes-langempty';
			empty.textContent = t('noLanguageMatch', 'No matching language.');
			langList.appendChild(empty);
			langActive = -1;
			return;
		}
		items.forEach(function (item, index) {
			const option = document.createElement('li');
			const name = document.createElement('span');
			option.id = 'choir-notes-langopt-' + index;
			option.className = 'choir-notes-langopt';
			option.setAttribute('role', 'option');
			option.setAttribute('data-speech-lang', item.code);
			option.tabIndex = -1;
			name.className = 'choir-notes-langopt__name';
			name.textContent = item.label;
			option.appendChild(name);
			if (item.code === chosen) {
				option.classList.add('is-chosen');
				option.setAttribute('aria-selected', 'true');
				option.classList.add('is-active');
				langActive = index;
			} else {
				option.setAttribute('aria-selected', 'false');
			}
			option.addEventListener('click', function () {
				chooseSpeechLang(item.code);
			});
			langList.appendChild(option);
		});
		if (langActive < 0) {
			setActiveLang(0);
		} else if (langSearch) {
			const active = langList.querySelector('.is-active');
			if (active) {
				langSearch.setAttribute('aria-activedescendant', active.id);
			}
		}
	}

	function closeLangPopover() {
		if (!langPop) {
			return;
		}
		langPop.hidden = true;
		if (micBtn) {
			micBtn.setAttribute('aria-expanded', 'false');
		}
		if (langSearch) {
			langSearch.setAttribute('aria-expanded', 'false');
		}
	}

	function openLangPopover() {
		if (!langPop || !langSearch) {
			return;
		}
		langPop.hidden = false;
		if (micBtn) {
			micBtn.setAttribute('aria-expanded', 'true');
		}
		langSearch.setAttribute('aria-expanded', 'true');
		langSearch.value = '';
		renderSpeechLanguages('');
		langSearch.focus();
		const selected = langList ? langList.querySelector('[aria-selected="true"]') : null;
		if (selected) {
			selected.scrollIntoView({ block: 'nearest' });
		}
	}

	function chooseSpeechLang(code) {
		if (SPEECH_CODES.indexOf(code) === -1) {
			return;
		}
		rememberSpeechLang(code);
		closeLangPopover();
		startDictation(code);
	}

	function stopDictation() {
		listening = false;
		if (recognition) {
			recognition.onend = null;
			try {
				recognition.stop();
			} catch (err) {
				// Already stopped.
			}
			recognition = null;
		}
		if (micBtn) {
			micBtn.classList.remove('is-recording');
			micBtn.setAttribute('aria-pressed', 'false');
			micBtn.setAttribute('aria-label', t('dictate', 'Dictate a note'));
		}
	}

	function startDictation(lang) {
		if (!input || !micBtn || !speechSupported()) {
			showSpeechUnsupported();
			return;
		}
		const Ctor = window.SpeechRecognition || window.webkitSpeechRecognition;
		const prefix = input.value.slice(0, input.selectionStart || 0);
		const suffix = input.value.slice(input.selectionEnd || 0);
		recognition = new Ctor();
		recognition.lang = lang || currentSpeechLang();
		recognition.continuous = true;
		recognition.interimResults = true;
		recognition.onresult = function (event) {
			let spoken = '';
			for (let i = 0; i < event.results.length; i++) {
				spoken += event.results[i][0].transcript;
			}
			const spacer = prefix && spoken && !/\s$/.test(prefix) ? ' ' : '';
			input.value = prefix + spacer + spoken + suffix;
			saveDraft();
		};
		recognition.onerror = function (event) {
			if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
				showError(t('speechUnsupported', 'Voice input is not supported in this browser.'));
			}
			stopDictation();
		};
		recognition.onend = function () {
			stopDictation();
		};
		try {
			recognition.start();
			listening = true;
			micBtn.classList.add('is-recording');
			micBtn.setAttribute('aria-pressed', 'true');
			micBtn.setAttribute('aria-label', t('stopDictation', 'Stop dictation'));
		} catch (err) {
			showError(t('speechUnsupported', 'Voice input is not supported in this browser.'));
			stopDictation();
		}
	}

	function showSpeechUnsupported() {
		if (micBtn) {
			micBtn.hidden = true;
		}
		closeLangPopover();
		if (speechNote) {
			speechNote.hidden = false;
		}
	}


	function syncKeyboard() {
		if (!window.visualViewport || !window.matchMedia('(max-width: 640px)').matches) {
			sheet.style.setProperty('--choir-kb', '0px');
			return;
		}
		const inset = Math.max(0, window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop);
		sheet.style.setProperty('--choir-kb', inset + 'px');
	}

	document.querySelectorAll('.choir-notes-open').forEach(function (button) {
		button.addEventListener('click', function () {
			openSong();
		});
	});

	document.querySelectorAll('.choir-track-notes').forEach(function (button) {
		button.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			openTrack(button.getAttribute('data-track-id'));
		});
	});

	layer.querySelectorAll('[data-choir-notes-close]').forEach(function (control) {
		control.addEventListener('click', closeSheet);
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key && !sheet.hidden) {
			if (langPop && !langPop.hidden) {
				closeLangPopover();
				if (micBtn) {
					micBtn.focus();
				}
				return;
			}
			if (picker && !picker.hidden) {
				closePicker();
				return;
			}
			closeSheet();
		}
	});

	if (form) {
		form.addEventListener('submit', submitNote);
	}
	if (input) {
		input.addEventListener('input', function () {
			showError('');
			saveDraft();
			const pos = input.selectionStart || 0;
			if (pos > 0 && '@' === input.value.charAt(pos - 1)) {
				const prev = pos > 1 ? input.value.charAt(pos - 2) : '';
				if (1 === pos || /\s/.test(prev)) {
					pendingAt = pos - 1;
					openPicker();
				}
			}
		});
	}
	const atBtn = sheet.querySelector('.choir-notes-at');
	if (atBtn) {
		atBtn.addEventListener('click', function () {
			if (input) {
				const pos = input.selectionStart || input.value.length;
				pendingAt = null;
				input.focus();
				input.setSelectionRange(pos, pos);
			}
			openPicker();
		});
	}
	const timeBtn = sheet.querySelector('.choir-notes-time');
	if (timeBtn) {
		timeBtn.addEventListener('click', insertTime);
	}
	if (cancelBtn) {
		cancelBtn.addEventListener('click', function () {
			clearDraft();
			if (input) {
				input.value = '';
			}
			cancelEdit(false);
		});
	}
	if (infoBtn && guide) {
		let guidePinned = false;
		const showGuide = function (open) {
			guide.hidden = !open;
			infoBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		};
		infoBtn.addEventListener('click', function () {
			guidePinned = !guidePinned;
			showGuide(guidePinned);
		});
		if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
			const hideGuideLater = function () {
				window.setTimeout(function () {
					if (!guidePinned && !infoBtn.matches(':hover') && !guide.matches(':hover')) {
						showGuide(false);
					}
				}, 200);
			};
			infoBtn.addEventListener('mouseenter', function () {
				showGuide(true);
			});
			infoBtn.addEventListener('mouseleave', hideGuideLater);
			guide.addEventListener('mouseleave', hideGuideLater);
		}
	}
	if (micBtn) {
		if (speechSupported()) {
			micBtn.hidden = false;
			micBtn.addEventListener('click', function () {
				if (listening) {
					stopDictation();
					return;
				}
				if (langPop && !langPop.hidden) {
					closeLangPopover();
					return;
				}
				openLangPopover();
			});
		} else {
			showSpeechUnsupported();
		}
	}
	if (langSearch) {
		langSearch.addEventListener('input', function () {
			renderSpeechLanguages(langSearch.value);
		});
		langSearch.addEventListener('keydown', function (event) {
			const options = visibleLangOptions();
			if ('ArrowDown' === event.key) {
				event.preventDefault();
				setActiveLang(langActive + 1);
				if (options[langActive]) {
					options[langActive].focus();
				}
			} else if ('ArrowUp' === event.key) {
				event.preventDefault();
				setActiveLang(langActive - 1);
			} else if ('Enter' === event.key) {
				event.preventDefault();
				const chosen = options[langActive] || langList.querySelector('[aria-selected="true"]');
				if (chosen) {
					chooseSpeechLang(chosen.getAttribute('data-speech-lang'));
				}
			} else if ('Escape' === event.key) {
				event.preventDefault();
				closeLangPopover();
				if (micBtn) {
					micBtn.focus();
				}
			}
		});
	}
	document.addEventListener('click', function (event) {
		if (!langPop || langPop.hidden) {
			return;
		}
		const target = event.target;
		if (langPop.contains(target) || (micBtn && micBtn.contains(target))) {
			return;
		}
		closeLangPopover();
	});
	if (langList) {
		langList.addEventListener('keydown', function (event) {
			const options = visibleLangOptions();
			if ('ArrowDown' === event.key) {
				event.preventDefault();
				setActiveLang(langActive + 1);
				if (options[langActive]) {
					options[langActive].focus();
				}
			} else if ('ArrowUp' === event.key) {
				event.preventDefault();
				if (langActive <= 0) {
					setActiveLang(0);
					if (langSearch) {
						langSearch.focus();
					}
					return;
				}
				setActiveLang(langActive - 1);
				if (options[langActive]) {
					options[langActive].focus();
				}
			} else if ('Enter' === event.key) {
				event.preventDefault();
				const chosen = options[langActive];
				if (chosen) {
					chooseSpeechLang(chosen.getAttribute('data-speech-lang'));
				}
			} else if ('Escape' === event.key) {
				event.preventDefault();
				closeLangPopover();
				if (micBtn) {
					micBtn.focus();
				}
			}
		});
	}

	function bindSwipe(target) {
		if (!target) {
			return;
		}
		target.addEventListener('touchstart', function (event) {
			if (!window.matchMedia('(max-width: 640px)').matches) {
				return;
			}
			dragging = true;
			dragStartY = event.touches[0].clientY;
		}, { passive: true });
		target.addEventListener('touchmove', function (event) {
			if (!dragging) {
				return;
			}
			const delta = event.touches[0].clientY - dragStartY;
			if (delta > 0) {
				sheet.style.transform = 'translateY(' + delta + 'px)';
			}
		}, { passive: true });
		target.addEventListener('touchend', function (event) {
			if (!dragging) {
				return;
			}
			dragging = false;
			const delta = event.changedTouches[0].clientY - dragStartY;
			sheet.style.transform = '';
			if (delta > 80) {
				closeSheet();
			}
		});
	}

	bindSwipe(sheet.querySelector('.choir-notes-sheet__handle'));
	bindSwipe(sheet.querySelector('.choir-notes-sheet__header'));

	if (window.visualViewport) {
		window.visualViewport.addEventListener('resize', syncKeyboard);
		window.visualViewport.addEventListener('scroll', syncKeyboard);
	}

	applyCounts(cfg.counts || {});
	loadNotes();
})();
