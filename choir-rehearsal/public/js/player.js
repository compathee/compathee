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
	const closeBtn = player.querySelector('.choir-sticky-player__close');
	const waveEl = player.querySelector('.choir-sticky-player__wave');

	if (!audio || !playBtn || !seek || !timeEl) {
		return;
	}

	let isSeeking = false;

	function formatTime(seconds) {
		if (!isFinite(seconds) || seconds < 0) {
			return '0:00';
		}
		const m = Math.floor(seconds / 60);
		const s = Math.floor(seconds % 60);
		return m + ':' + String(s).padStart(2, '0');
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
		playBtn.classList.toggle('is-playing', playing);
		playBtn.setAttribute('aria-label', playing ? (i18n.pause || 'Pause') : (i18n.play || 'Play'));
		if (playIcon) {
			playIcon.classList.toggle('is-playing', playing);
		}
	}

	function setPlayerWaveform(url) {
		if (!waveEl) {
			return;
		}
		const next = url || '';
		waveEl.setAttribute('data-audio-url', next);
		waveEl.classList.toggle('is-empty', !next);
		if (window.choirWaveforms && typeof window.choirWaveforms.refresh === 'function') {
			window.choirWaveforms.refresh(waveEl);
		}
	}

	function closePlayer() {
		audio.pause();
		audio.removeAttribute('src');
		audio.load();
		seek.value = '0';
		timeEl.textContent = '0:00 / 0:00';
		if (title) {
			title.textContent = '';
		}
		setPlaying(false);
		setPlayerWaveform('');
		player.classList.add('is-hidden');
		player.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('choir-sticky-player-open');
		if (window.choirPiano && typeof window.choirPiano.close === 'function') {
			window.choirPiano.close();
		}
	}

	function playTrack(url, trackTitle) {
		if (!url) {
			return;
		}
		if (title) {
			title.textContent = trackTitle || '';
		}
		player.classList.remove('is-hidden');
		player.setAttribute('aria-hidden', 'false');
		document.body.classList.add('choir-sticky-player-open');
		setPlayerWaveform(url);
		audio.src = url;
		audio.play().catch(function () {
			setPlaying(false);
		});
	}

	playBtn.addEventListener('click', function () {
		if (audio.paused) {
			audio.play().catch(function () {});
		} else {
			audio.pause();
		}
	});

	if (closeBtn) {
		closeBtn.setAttribute('aria-label', i18n.close || 'Close player');
		closeBtn.addEventListener('click', closePlayer);
	}

	seek.addEventListener('input', function () {
		isSeeking = true;
		if (!audio.duration) {
			return;
		}
		audio.currentTime = (parseFloat(seek.value, 10) / 100) * audio.duration;
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

	document.addEventListener('click', function (event) {
		const button = event.target.closest('.choir-play-track');
		if (!button || button.disabled) {
			return;
		}
		event.preventDefault();
		playTrack(button.getAttribute('data-track-url'), button.getAttribute('data-track-title'));
	});
})();
