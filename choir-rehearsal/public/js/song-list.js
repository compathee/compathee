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
			needle: normalizeSearchText(song.title || '')
		};
	});

	function formatTracks(count) {
		const template = count === 1 ? config.i18n.trackSingular : config.i18n.trackPlural;
		return template.replace('%d', String(count));
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

	function createItem(song) {
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

		if (song.hasPdf) {
			titleRow.appendChild(createPdfBadge());
		}

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
