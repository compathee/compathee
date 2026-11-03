(function () {
	'use strict';

	const config = window.choirRehearsalSongList;
	const input = document.getElementById('choir-song-search');
	if (!input || !config || !Array.isArray(config.songs)) {
		return;
	}

	const list = document.querySelector('.choir-song-list');
	const emptyMessage = document.querySelector('.choir-song-list__empty-search');
	const pagination = document.querySelector('.choir-song-pagination');

	if (!list) {
		return;
	}

	const originalHtml = list.innerHTML;
	const showPublicBadges = Boolean(config.showPublicBadges);

	/**
	 * Unicode-aware normalize for Latin, Cyrillic, and other scripts.
	 */
	function normalizeSearchText(value) {
		let text = String(value || '');
		if (typeof text.normalize === 'function') {
			text = text.normalize('NFC');
		}
		text = text.trim();
		// Locale-aware case fold (handles Cyrillic А-Я and similar).
		if (typeof text.toLocaleLowerCase === 'function') {
			text = text.toLocaleLowerCase();
		} else {
			text = text.toLowerCase();
		}
		// Collapse whitespace so "Бого  родице" still matches.
		text = text.replace(/\s+/g, ' ');
		return text;
	}

	const songs = config.songs.map(function (song) {
		return {
			title: String(song.title || ''),
			url: String(song.url || ''),
			trackCount: Number(song.trackCount) || 0,
			editUrl: String(song.editUrl || ''),
			hasPdf: Boolean(song.hasPdf),
			isPublic: Boolean(song.isPublic),
			hasYoutube: Boolean(song.hasYoutube),
			needle: normalizeSearchText(song.title || '')
		};
	});

	function formatTracks(count) {
		const forms = config.i18n.trackForms;
		if (forms && typeof forms === 'object') {
			let category = count === 1 ? 'one' : 'other';
			try {
				if (typeof Intl !== 'undefined' && typeof Intl.PluralRules === 'function') {
					category = new Intl.PluralRules(document.documentElement.lang || 'en').select(count);
				}
			} catch (err) {
				category = count === 1 ? 'one' : 'other';
			}
			const template = forms[category] || forms.other || forms.one;
			if (template) {
				return String(template).replace('%d', String(count));
			}
		}
		const template = count === 1 ? config.i18n.trackSingular : config.i18n.trackPlural;
		return template.replace('%d', String(count));
	}

	function outlineSvg(className, inner) {
		return '<svg class="' + className + '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';
	}

	function createOutlinePdfBadge() {
		const badge = document.createElement('span');
		badge.className = 'choir-song-pdf-badge';
		badge.title = config.i18n.pdfAttached || 'PDF score attached';
		const sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = config.i18n.pdfAttached || 'PDF score attached';
		badge.appendChild(sr);
		badge.insertAdjacentHTML(
			'beforeend',
			outlineSvg(
				'choir-song-pdf-badge__icon choir-icon choir-icon--pdf',
				'<path d="M7 2.8h6.1L19.2 8.9V19.2A1.8 1.8 0 0 1 17.4 21H7A1.8 1.8 0 0 1 5.2 19.2V4.6A1.8 1.8 0 0 1 7 2.8z"/>' +
					'<path d="M13 2.9V8.2h5.3"/>' +
					'<text x="12" y="17.2" text-anchor="middle" font-size="6.4" font-weight="700" fill="currentColor" stroke="none" font-family="Arial, Helvetica, sans-serif">PDF</text>'
			)
		);
		return badge;
	}

	function createOutlineYoutubeBadge() {
		const badge = document.createElement('span');
		badge.className = 'choir-song-youtube-badge';
		badge.title = config.i18n.youtubeTrack || 'Has YouTube track';
		const sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = config.i18n.youtubeTrack || 'Has YouTube track';
		badge.appendChild(sr);
		badge.insertAdjacentHTML(
			'beforeend',
			outlineSvg(
				'choir-song-youtube-badge__icon choir-icon choir-icon--youtube',
				'<rect x="3.2" y="6.1" width="17.6" height="11.8" rx="2.6"/>' +
					'<path d="M10.5 9.2v5.6l4.8-2.8-4.8-2.8z"/>'
			)
		);
		return badge;
	}

	function createOutlinePublicBadge() {
		const badge = document.createElement('span');
		badge.className = 'choir-song-public-badge';
		badge.title = config.i18n.publicSong || 'Public — available without login';
		const sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = config.i18n.publicSong || 'Public — available without login';
		badge.appendChild(sr);
		badge.insertAdjacentHTML(
			'beforeend',
			outlineSvg(
				'choir-song-public-badge__icon choir-icon choir-icon--public',
				'<circle cx="12" cy="12" r="8.15"/>' +
					'<ellipse cx="12" cy="12" rx="3.5" ry="8.15"/>' +
					'<path d="M4.1 12h15.8M5.3 8.15h13.4M5.3 15.85h13.4"/>'
			)
		);
		return badge;
	}

	function createTrackBadge(count) {
		const label = formatTracks(count);
		const wrap = document.createElement('span');
		wrap.className = 'choir-track-count';
		wrap.setAttribute('aria-label', label);
		const sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = label;
		wrap.appendChild(sr);
		const badge = document.createElement('span');
		badge.className = 'choir-track-count__badge';
		badge.setAttribute('aria-hidden', 'true');
		const num = document.createElement('span');
		num.className = 'choir-track-count__num';
		num.textContent = String(count);
		badge.appendChild(num);
		badge.insertAdjacentHTML(
			'beforeend',
			outlineSvg(
				'choir-icon choir-icon--notes',
				'<ellipse cx="7.7" cy="16.7" rx="2.15" ry="1.55" transform="rotate(-24 7.7 16.7)"/>' +
					'<ellipse cx="15.5" cy="15" rx="2.15" ry="1.55" transform="rotate(-24 15.5 15)"/>' +
					'<path d="M9.55 15.9V6.6M17.35 14.2V4.9M9.55 6.6l7.8-1.7"/>'
			)
		);
		wrap.appendChild(badge);
		return wrap;
	}

	function cardSlot(className, node) {
		const slot = document.createElement('span');
		slot.className = 'choir-song-card__slot ' + className;
		if (node) {
			slot.appendChild(node);
		} else {
			slot.setAttribute('aria-hidden', 'true');
		}
		return slot;
	}

	function createWaveItem(song) {
		const item = document.createElement('li');
		item.setAttribute('data-song-title', song.title);

		const link = document.createElement('a');
		link.className = 'choir-song-list__title';
		link.href = song.url;
		link.textContent = song.title;
		link.title = song.title;
		item.appendChild(link);
		item.appendChild(cardSlot('choir-song-card__pdf', song.hasPdf ? createOutlinePdfBadge() : null));
		item.appendChild(cardSlot('choir-song-card__tracks', createTrackBadge(song.trackCount)));
		item.appendChild(cardSlot('choir-song-card__youtube', song.hasYoutube ? createOutlineYoutubeBadge() : null));
		item.appendChild(cardSlot('choir-song-card__public', showPublicBadges && song.isPublic ? createOutlinePublicBadge() : null));

		if (song.editUrl) {
			const edit = document.createElement('a');
			edit.className = 'choir-song-edit';
			edit.href = song.editUrl;
			edit.textContent = config.i18n.edit;
			item.appendChild(edit);
		}

		return item;
	}

	function createPdfBadge() {
		const badge = document.createElement('span');
		badge.className = 'choir-song-pdf-badge';
		badge.title = config.i18n.pdfAttached || 'PDF score attached';

		const sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = config.i18n.pdfAttached || 'PDF score attached';
		badge.appendChild(sr);

		badge.insertAdjacentHTML(
			'beforeend',
			'<svg class="choir-song-pdf-badge__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
				'<path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm1 7V3.5L19.5 9H15zM8.5 12h1.2c1.1 0 1.8.5 1.8 1.4 0 .9-.7 1.4-1.8 1.4H9.3v1.7H8.5V12zm1.2 2c.4 0 .7-.2.7-.6s-.3-.6-.7-.6H9.3v1.2h.4zm3.1-2h1.5c1.3 0 2.1.7 2.1 1.9s-.8 1.9-2.1 1.9h-.7v1.7h-.8V12zm1.5 3c.7 0 1.2-.4 1.2-1.1S15 12.8 14.3 12.8h-.7V15h.7zm3.2-3h.8v4.5h-.8V12z"/>' +
			'</svg>'
		);

		return badge;
	}

	function createPublicBadge() {
		const badge = document.createElement('span');
		badge.className = 'choir-song-public-badge';
		badge.title = config.i18n.publicSong || 'Public — available without login';

		const sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = config.i18n.publicSong || 'Public — available without login';
		badge.appendChild(sr);

		badge.insertAdjacentHTML(
			'beforeend',
			'<svg class="choir-song-public-badge__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
				'<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>' +
			'</svg>'
		);

		return badge;
	}

	function createYoutubeBadge() {
		const badge = document.createElement('span');
		badge.className = 'choir-song-youtube-badge';
		badge.title = config.i18n.youtubeTrack || 'Has YouTube track';

		const sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = config.i18n.youtubeTrack || 'Has YouTube track';
		badge.appendChild(sr);

		badge.insertAdjacentHTML(
			'beforeend',
			'<svg class="choir-song-youtube-badge__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
				'<path fill="currentColor" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.8 15.5v-7l6.3 3.5-6.3 3.5z"/>' +
			'</svg>'
		);

		return badge;
	}

	function appendBadges(titleRow, song) {
		const showPdf = Boolean(song.hasPdf);
		const showPublic = showPublicBadges && Boolean(song.isPublic);
		const showYoutube = Boolean(song.hasYoutube);
		if (!showPdf && !showPublic && !showYoutube) {
			return;
		}

		const badges = document.createElement('span');
		badges.className = 'choir-song-list__badges';
		if (showPdf) {
			badges.appendChild(createPdfBadge());
		}
		if (showPublic) {
			badges.appendChild(createPublicBadge());
		}
		if (showYoutube) {
			badges.appendChild(createYoutubeBadge());
		}
		titleRow.appendChild(badges);
	}

	function createItem(song) {
		if (config.waveAppearance) {
			return createWaveItem(song);
		}

		const item = document.createElement('li');
		item.setAttribute('data-song-title', song.title);

		const main = document.createElement('div');
		main.className = 'choir-song-list__main';

		const titleRow = document.createElement('div');
		titleRow.className = 'choir-song-list__title-row';

		const link = document.createElement('a');
		link.href = song.url;
		link.textContent = song.title;
		titleRow.appendChild(link);
		appendBadges(titleRow, song);

		main.appendChild(titleRow);

		const count = document.createElement('span');
		count.className = 'choir-track-count';
		count.textContent = formatTracks(song.trackCount);
		main.appendChild(count);

		item.appendChild(main);

		if (song.editUrl) {
			const edit = document.createElement('a');
			edit.className = 'choir-song-edit';
			edit.href = song.editUrl;
			edit.textContent = config.i18n.edit;
			item.appendChild(edit);
		}

		return item;
	}

	function renderResults(query) {
		const normalizedQuery = normalizeSearchText(query);

		if (normalizedQuery === '') {
			list.innerHTML = originalHtml;
			if (pagination) {
				pagination.hidden = false;
			}
			if (emptyMessage) {
				emptyMessage.classList.remove('is-visible');
			}
			return;
		}

		const matches = songs.filter(function (song) {
			return song.needle.indexOf(normalizedQuery) !== -1;
		});

		list.replaceChildren();
		matches.forEach(function (song) {
			list.appendChild(createItem(song));
		});

		if (pagination) {
			pagination.hidden = true;
		}
		if (emptyMessage) {
			emptyMessage.classList.toggle('is-visible', matches.length === 0);
		}
	}

	function onSearchInput() {
		renderResults(input.value);
	}

	input.setAttribute('lang', document.documentElement.lang || 'ru');
	input.addEventListener('input', onSearchInput);
	input.addEventListener('search', onSearchInput);
	// Mobile / IME composition (Cyrillic keyboards).
	input.addEventListener('compositionend', onSearchInput);
})();
