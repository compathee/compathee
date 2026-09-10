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
	let durationFixToken = 0;

	function formatTime(seconds) {
		if (!isFinite(seconds) || seconds < 0) {
			return '0:00';
		}
		const m = Math.floor(seconds / 60);
		const s = Math.floor(seconds % 60);
		return m + ':' + String(s).padStart(2, '0');
	}

	function finiteDuration() {
		const duration = audio.duration;
		return isFinite(duration) && duration > 0 ? duration : 0;
	}

	function updateTime() {
		const current = audio.currentTime || 0;
		const duration = finiteDuration();
		timeEl.textContent = formatTime(current) + ' / ' + formatTime(duration);
		if (!isSeeking && duration > 0) {
			seek.value = String((current / duration) * 100);
		}
	}

	/**
	 * Chrome often reports duration=Infinity for MediaRecorder WebM until we
	 * briefly seek near the end, then reset. Needed for desktop scrubbing.
	 */
	function ensureSeekableDuration() {
		const token = ++durationFixToken;
		const known = finiteDuration();
		if (known > 0) {
			return Promise.resolve(known);
		}

		return new Promise(function (resolve) {
			let settled = false;

			function finish() {
				if (settled || token !== durationFixToken) {
					return;
				}
				settled = true;
				audio.removeEventListener('timeupdate', onTimeUpdate);
				audio.removeEventListener('durationchange', onDurationChange);
				audio.removeEventListener('error', onError);
				const duration = finiteDuration();
				try {
					if (audio.currentTime !== 0) {
						audio.currentTime = 0;
					}
				} catch (err) {
					// Ignore reset failures.
				}
				resolve(duration);
			}

			function onTimeUpdate() {
				if (finiteDuration() > 0) {
					finish();
				}
			}

			function onDurationChange() {
				if (finiteDuration() > 0) {
					finish();
				}
			}

			function onError() {
				finish();
			}

			audio.addEventListener('timeupdate', onTimeUpdate);
			audio.addEventListener('durationchange', onDurationChange);
			audio.addEventListener('error', onError);

			try {
				// Large seek forces Chromium to resolve MediaRecorder WebM duration.
				audio.currentTime = 1e101;
			} catch (err) {
				finish();
				return;
			}

			window.setTimeout(finish, 1500);
		});
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
		durationFixToken += 1;
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
		if (window.choirMetronome && typeof window.choirMetronome.close === 'function') {
			window.choirMetronome.close();
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
		durationFixToken += 1;
		const token = durationFixToken;
		audio.src = url;
		audio.load();

		const startPlayback = function () {
			if (token !== durationFixToken) {
				return;
			}
			updateTime();
			audio.play().catch(function () {
				setPlaying(false);
			});
		};

		const afterMeta = function () {
			if (token !== durationFixToken) {
				return;
			}
			ensureSeekableDuration().then(function () {
				if (token !== durationFixToken) {
					return;
				}
				updateTime();
				startPlayback();
			});
		};

		if (audio.readyState >= 1) {
			afterMeta();
		} else {
			audio.addEventListener('loadedmetadata', afterMeta, { once: true });
			// Some WebM captures fire durationchange instead of a useful loadedmetadata.
			audio.addEventListener('durationchange', function onDuration() {
				if (token !== durationFixToken) {
					audio.removeEventListener('durationchange', onDuration);
					return;
				}
				if (finiteDuration() > 0) {
					audio.removeEventListener('durationchange', onDuration);
					updateTime();
				}
			});
		}
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
		const duration = finiteDuration();
		if (!duration) {
			return;
		}
		audio.currentTime = (parseFloat(seek.value, 10) / 100) * duration;
		updateTime();
	});

	seek.addEventListener('change', function () {
		isSeeking = false;
		const duration = finiteDuration();
		if (!duration) {
			return;
		}
		audio.currentTime = (parseFloat(seek.value, 10) / 100) * duration;
		updateTime();
	});

	audio.addEventListener('timeupdate', updateTime);
	audio.addEventListener('loadedmetadata', updateTime);
	audio.addEventListener('durationchange', updateTime);
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
