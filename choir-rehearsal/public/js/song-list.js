(function () {
	'use strict';

	const input = document.getElementById('choir-song-search');
	if (!input) {
		return;
	}

	const list = document.querySelector('.choir-song-list');
	if (!list) {
		return;
	}

	const items = list.querySelectorAll('li');
	const emptyMessage = document.querySelector('.choir-song-list__empty-search');

	input.addEventListener('input', function () {
		const query = input.value.trim().toLowerCase();
		let visibleCount = 0;

		items.forEach(function (item) {
			const title = (item.getAttribute('data-song-title') || '').toLowerCase();
			const matches = query === '' || title.includes(query);
			item.hidden = !matches;
			if (matches) {
				visibleCount += 1;
			}
		});

		if (emptyMessage) {
			emptyMessage.classList.toggle('is-visible', query !== '' && visibleCount === 0);
		}
	});
})();
