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
		const normalizedQuery = query.trim().toLowerCase();

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

		const matches = config.songs.filter(function (song) {
			return song.title.toLowerCase().includes(normalizedQuery);
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

	input.addEventListener('input', function () {
		renderResults(input.value);
	});
})();
