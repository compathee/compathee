(function () {
	'use strict';

	const player = document.getElementById('choir-sticky-player');
	if (!player) {
		return;
	}

	const i18n = window.choirRehearsalPlayer || {};
	const audio = player.querySelector('.choir-sticky-player__audio');
	const title = player.querySelector('.choir-sticky-player__title');
	const playBtn = player.querySelector('.choir-sticky-player__play');
	const playIcon = player.querySelector('.choir-sticky-player__play-icon');
	const seek = player.querySelector('.choir-sticky-player__seek');
	const timeEl = player.querySelector('.choir-sticky-player__time');

	if (!audio || !playBtn || !seek || !timeEl) {
		return;
	}

	let isSeeking = false;

	function formatTime(seconds) {
		if (!isFinite(seconds) || seconds < 0) {
			return '0:00';
		}
		const mins = Math.floor(seconds / 60);
		const secs = Math.floor(seconds % 60);
		return mins + ':' + String(secs).padStart(2, '0');
	}

	function updateTime() {
		const current = audio.currentTime || 0;
		const duration = audio.duration || 0;
		timeEl.textContent = formatTime(current) + ' / ' + formatTime(duration);
		if (!isSeeking && duration > 0) {
			seek.value = String((current / duration) * 100);
		}
	}

	function setPlaying(playing) {
		playIcon.textContent = playing ? '❚❚' : '▶';
		playBtn.setAttribute('aria-label', playing ? (i18n.pause || 'Pause') : (i18n.play || 'Play'));
		playBtn.classList.toggle('is-playing', playing);
	}

	playBtn.addEventListener('click', function () {
		if (audio.paused) {
			audio.play().catch(function () {});
		} else {
			audio.pause();
		}
	});

	seek.addEventListener('input', function () {
		isSeeking = true;
		const duration = audio.duration || 0;
		if (duration > 0) {
			audio.currentTime = (parseFloat(seek.value, 10) / 100) * duration;
		}
		updateTime();
	});

	seek.addEventListener('change', function () {
		isSeeking = false;
	});

	audio.addEventListener('timeupdate', updateTime);
	audio.addEventListener('loadedmetadata', updateTime);
	audio.addEventListener('play', function () {
		setPlaying(true);
	});
	audio.addEventListener('pause', function () {
		setPlaying(false);
	});
	audio.addEventListener('ended', function () {
		setPlaying(false);
	});

	document.querySelectorAll('.choir-play-track').forEach(function (button) {
		button.addEventListener('click', function () {
			const url = button.getAttribute('data-track-url');
			const trackTitle = button.getAttribute('data-track-title');

			if (!url) {
				return;
			}

			player.classList.remove('is-hidden');
			player.setAttribute('aria-hidden', 'false');
			title.textContent = trackTitle || '';
			audio.src = url;
			seek.value = '0';
			audio.play().catch(function () {
				setPlaying(false);
			});
		});
	});
})();
