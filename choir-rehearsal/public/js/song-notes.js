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
			button.type = 'button';
			button.className = 'choir-notes-picker__item';
			button.textContent = track.label || '';
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

	function speechSupported() {
		return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
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

	function startDictation() {
		if (!input || !micBtn || !speechSupported()) {
			if (micBtn) {
				micBtn.hidden = true;
			}
			return;
		}
		const Ctor = window.SpeechRecognition || window.webkitSpeechRecognition;
		const prefix = input.value.slice(0, input.selectionStart || 0);
		const suffix = input.value.slice(input.selectionEnd || 0);
		recognition = new Ctor();
		recognition.lang = cfg.speechLang || 'en-US';
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
				micBtn.hidden = true;
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
			micBtn.hidden = true;
			stopDictation();
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
				startDictation();
			});
		}
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
