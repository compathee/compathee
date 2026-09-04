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
			needle: normalizeSearchText(song.title || '')
		};
	});

	function formatTracks(count) {
		const template = count === 1 ? config.i18n.trackSingular : config.i18n.trackPlural;
		return template.replace('%d', String(count));
	}

	function createItem(song) {
		const item = document.createElement('li');
		item.setAttribute('data-song-title', song.title);

		const main = document.createElement('div');
		main.className = 'choir-song-list__main';

		const link = document.createElement('a');
		link.href = song.url;
		link.textContent = song.title;
		main.appendChild(link);

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
