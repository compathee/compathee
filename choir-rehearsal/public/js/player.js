(function () {
	'use strict';

	const player = document.getElementById('choir-sticky-player');
	if (!player) {
		return;
	}

	const audio = player.querySelector('.choir-sticky-player__audio');
	const title = player.querySelector('.choir-sticky-player__title');

	document.querySelectorAll('.choir-play-track').forEach(function (button) {
		button.addEventListener('click', function () {
			const url = button.getAttribute('data-track-url');
			const trackTitle = button.getAttribute('data-track-title');

			if (!url || !audio) {
				return;
			}

			player.classList.remove('is-hidden');
			player.setAttribute('aria-hidden', 'false');
			title.textContent = trackTitle || '';
			audio.src = url;
			audio.play().catch(function () {
				/* autoplay may be blocked until user gesture; button click should allow it */
			});
		});
	});
})();
