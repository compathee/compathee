/**
 * Song-page multi-track mixer.
 * One AudioContext clock; every buffer starts at the same time and offset.
 */
(function () {
	'use strict';

	var root = document.getElementById('choir-mixer');
	var launch = document.querySelector('.choir-mixer-launch');
	var logic = window.choirMixerLogic;
	if (!root || !launch || !logic || launch.tagName !== 'BUTTON') {
		return;
	}

	var i18n = window.choirRehearsalMixer || {};
	var seek = root.querySelector('.choir-mixer__seek');
	var timeEl = root.querySelector('.choir-mixer__time');
	var durationEl = root.querySelector('.choir-mixer__duration');
	var playBtn = root.querySelector('.choir-mixer__play');
	var backBtn = root.querySelector('.choir-mixer__back');
	var forwardBtn = root.querySelector('.choir-mixer__forward');
	var abBtn = root.querySelector('.choir-mixer__ab');
	var speedBtn = root.querySelector('.choir-mixer__speed');
	var resetBtn = root.querySelector('.choir-mixer__reset');
	var statusEl = root.querySelector('.choir-mixer__status');
	if (!seek || !timeEl || !durationEl || !playBtn || !backBtn || !forwardBtn || !abBtn || !speedBtn || !resetBtn) {
		return;
	}

	var ctx = null;
	var master = null;
	var channels = [];
	var playing = false;
	var offset = 0;
	var rate = 1;
	var startedAt = 0;
	var loop = { a: null, b: null };
	var ready = false;
	var loading = false;
	var seeking = false;
	var raf = 0;
	var defaultPercent = parseInt(root.getAttribute('data-default-volume') || '70', 10);
	if (!isFinite(defaultPercent)) {
		defaultPercent = 70;
	}

	function fill(template, values) {
		var text = String(template || '');
		var list = values || [];
		var index;
		for (index = 0; index < list.length; index += 1) {
			text = text.replace('%' + (index + 1) + '$s', String(list[index]));
			text = text.replace('%' + (index + 1) + '$d', String(list[index]));
		}
		if (list.length === 1) {
			text = text.replace('%s', String(list[0]));
			text = text.replace('%d', String(list[0]));
		}
		return text;
	}

	function setStatus(message) {
		if (statusEl) {
			statusEl.textContent = message || '';
		}
	}

	function songDuration() {
		var max = 0;
		var index;
		for (index = 0; index < channels.length; index += 1) {
			if (channels[index].buffer && channels[index].buffer.duration > max) {
				max = channels[index].buffer.duration;
			}
		}
		return max;
	}

	function now() {
		var elapsed;
		var time;
		if (!playing || !ctx) {
			time = offset;
		} else {
			elapsed = (ctx.currentTime - startedAt) * rate;
			if (elapsed < 0) {
				elapsed = 0;
			}
			time = offset + elapsed;
		}
		return logic.clampPlayhead(time, songDuration());
	}

	function paintFader(channel) {
		var fader = channel.el.querySelector('.choir-mixer__fader');
		var percent = Math.round(channel.volume * 100);
		if (!fader) {
			return;
		}
		fader.style.setProperty('--value', String(percent));
		fader.setAttribute('aria-valuenow', String(percent));
		fader.setAttribute('aria-valuetext', percent + '%');
	}

	function paintFlags(channel) {
		var muteBtn = channel.el.querySelector('.choir-mixer__mute');
		var soloBtn = channel.el.querySelector('.choir-mixer__solo');
		if (muteBtn) {
			muteBtn.setAttribute('aria-pressed', channel.mute ? 'true' : 'false');
			muteBtn.classList.toggle('is-on', channel.mute);
			muteBtn.setAttribute('aria-label', fill(channel.mute ? (i18n.unmute || 'Unmute %s') : (i18n.mute || 'Mute %s'), [channel.name]));
		}
		if (soloBtn) {
			soloBtn.setAttribute('aria-pressed', channel.solo ? 'true' : 'false');
			soloBtn.classList.toggle('is-on', channel.solo);
			soloBtn.setAttribute('aria-label', fill(channel.solo ? (i18n.unsolo || 'Unsolo %s') : (i18n.solo || 'Solo %s'), [channel.name]));
		}
	}

	function applyGains() {
		var anySolo = logic.soloActive(channels);
		var index;
		if (!ctx) {
			return;
		}
		for (index = 0; index < channels.length; index += 1) {
			if (!channels[index].gain) {
				continue;
			}
			var gain = logic.channelGain(channels[index], anySolo);
			channels[index].gain.gain.setValueAtTime(gain, ctx.currentTime);
			channels[index].el.classList.toggle('is-silent', gain === 0);
		}
	}

	function resetMix() {
		var fresh = logic.resetMix(channels);
		var index;
		for (index = 0; index < channels.length; index += 1) {
			channels[index].volume = fresh[index].volume;
			channels[index].mute = fresh[index].mute;
			channels[index].solo = fresh[index].solo;
			paintFader(channels[index]);
			paintFlags(channels[index]);
		}
		applyGains();
	}

	function stopSources() {
		var index;
		for (index = 0; index < channels.length; index += 1) {
			if (!channels[index].source) {
				continue;
			}
			try {
				channels[index].source.onended = null;
				channels[index].source.stop();
			} catch (err) {
				// Already stopped.
			}
			try {
				channels[index].source.disconnect();
			} catch (err) {
				// Already disconnected.
			}
			channels[index].source = null;
		}
	}

	function pauseSticky() {
		var audio = document.querySelector('.choir-sticky-player__audio');
		if (audio && !audio.paused) {
			audio.pause();
		}
	}

	function renderTimeline() {
		var length = songDuration();
		var time = seeking ? offset : now();
		timeEl.textContent = logic.formatTime(time);
		durationEl.textContent = logic.formatTime(length);
		if (!seeking) {
			seek.value = length > 0 ? String(Math.round((time / length) * 1000)) : '0';
		}
		seek.disabled = !(length > 0);
	}

	function renderTransport() {
		playBtn.classList.toggle('is-playing', playing);
		playBtn.setAttribute('aria-label', playing ? (i18n.pause || 'Pause') : (i18n.play || 'Play'));
		speedBtn.textContent = fill(i18n.speed || 'Speed %s', [String(rate) + '×']);
		if (logic.abEnabled(loop)) {
			abBtn.textContent = fill(i18n.loopRange || 'Loop %1$s–%2$s', [logic.formatTime(loop.a), logic.formatTime(loop.b)]);
			abBtn.setAttribute('aria-pressed', 'true');
		} else {
			abBtn.textContent = i18n.ab || 'A–B loop';
			abBtn.setAttribute('aria-pressed', 'false');
		}
		renderTimeline();
	}

	function tick() {
		var time;
		var wrapped;
		var length;
		if (!playing) {
			return;
		}
		time = now();
		wrapped = logic.wrapLoop(time, loop);
		if (wrapped !== time) {
			startAt(wrapped);
			return;
		}
		length = songDuration();
		if (length > 0 && time >= length - 0.02) {
			offset = length;
			pause();
			renderTransport();
			return;
		}
		if (!seeking) {
			renderTimeline();
		}
		raf = window.requestAnimationFrame(tick);
	}

	function startAt(nextOffset) {
		var when;
		var any = false;
		var index;
		var start;
		var src;
		if (!ctx) {
			return;
		}
		stopSources();
		window.cancelAnimationFrame(raf);
		when = ctx.currentTime + 0.05;
		offset = logic.clampPlayhead(nextOffset, songDuration());
		startedAt = when;
		for (index = 0; index < channels.length; index += 1) {
			if (!channels[index].buffer || !channels[index].gain) {
				continue;
			}
			start = logic.sourceOffset(channels[index].buffer.duration, nextOffset);
			if (start === null) {
				continue;
			}
			src = ctx.createBufferSource();
			src.buffer = channels[index].buffer;
			src.playbackRate.value = rate;
			src.connect(channels[index].gain);
			(function (channel, source) {
				source.onended = function () {
					var length;
					if (channel.source !== source || !playing) {
						return;
					}
					channel.source = null;
					length = songDuration();
					if (length > 0 && now() >= length - 0.08) {
						offset = length;
						pause();
					}
				};
				try {
					source.start(when, start);
					channel.source = source;
					any = true;
				} catch (err) {
					source.onended = null;
					channel.source = null;
				}
			})(channels[index], src);
		}
		playing = any;
		applyGains();
		if (playing) {
			pauseSticky();
			raf = window.requestAnimationFrame(tick);
		}
		renderTransport();
	}

	function pause() {
		if (playing) {
			offset = now();
		}
		playing = false;
		stopSources();
		window.cancelAnimationFrame(raf);
		renderTransport();
	}

	function ensureContext() {
		var AudioCtx = window.AudioContext || window.webkitAudioContext;
		var silence;
		var blip;
		var index;
		if (!AudioCtx) {
			setStatus(i18n.startError || 'Could not start audio. Tap Play again.');
			return null;
		}
		if (!ctx) {
			ctx = new AudioCtx();
			master = ctx.createGain();
			master.gain.value = 1;
			master.connect(ctx.destination);
			silence = ctx.createBuffer(1, 1, ctx.sampleRate);
			blip = ctx.createBufferSource();
			blip.buffer = silence;
			blip.connect(ctx.destination);
			try {
				blip.start(0);
			} catch (err) {
				// Older iOS still unlocks on resume().
			}
			for (index = 0; index < channels.length; index += 1) {
				channels[index].gain = ctx.createGain();
				channels[index].gain.connect(master);
			}
			applyGains();
		}
		if (ctx.state === 'suspended') {
			ctx.resume();
		}
		return ctx;
	}

	function decodeBuffer(arrayBuffer) {
		return new Promise(function (resolve, reject) {
			var copy = arrayBuffer.slice(0);
			var result;
			try {
				result = ctx.decodeAudioData(copy, function (audioBuffer) {
					resolve(audioBuffer);
				}, function () {
					reject(new Error('decode'));
				});
			} catch (err) {
				reject(err);
				return;
			}
			if (result && typeof result.then === 'function') {
				result.then(resolve, reject);
			}
		});
	}

	function preload() {
		var pending = channels.length;
		var failed = 0;
		var index;
		if (!ctx || loading || ready) {
			return;
		}
		loading = true;
		root.setAttribute('aria-busy', 'true');
		setStatus(fill(i18n.loadingCount || 'Loading %1$d of %2$d', [0, channels.length]));
		function finishOne() {
			var done;
			pending -= 1;
			done = channels.length - pending;
			if (pending > 0) {
				setStatus(fill(i18n.loadingCount || 'Loading %1$d of %2$d', [done, channels.length]));
				return;
			}
			loading = false;
			ready = channels.some(function (channel) {
				return !!channel.buffer;
			});
			root.setAttribute('aria-busy', 'false');
			if (!ready || failed > 0) {
				setStatus(i18n.loadError || 'Could not load one or more tracks.');
			} else {
				setStatus('');
			}
			renderTransport();
		}
		for (index = 0; index < channels.length; index += 1) {
			(function (channel) {
				window.fetch(channel.url, { credentials: 'same-origin' })
					.then(function (response) {
						if (!response.ok) {
							throw new Error('load');
						}
						return response.arrayBuffer();
					})
					.then(function (buffer) {
						return decodeBuffer(buffer);
					})
					.then(function (audioBuffer) {
						channel.buffer = audioBuffer;
						channel.failed = false;
						channel.el.classList.remove('is-error');
					})
					.catch(function () {
						channel.failed = true;
						failed += 1;
						channel.el.classList.add('is-error');
					})
					.then(finishOne);
			})(channels[index]);
		}
	}

	function play() {
		var playhead;
		var length;
		ensureContext();
		if (!ctx) {
			return;
		}
		if (!ready) {
			if (!loading) {
				preload();
			}
			setStatus(loading ? (statusEl ? statusEl.textContent : '') : (i18n.startError || 'Could not start audio. Tap Play again.'));
			return;
		}
		playhead = now();
		length = songDuration();
		if (length > 0 && playhead >= length - 0.05) {
			playhead = loop && logic.abEnabled(loop) ? loop.a : 0;
		}
		startAt(playhead);
	}

	function seekToRatio(ratio) {
		var length = songDuration();
		var next = length * ratio;
		if (playing) {
			startAt(next);
			return;
		}
		offset = next;
		renderTimeline();
	}

	function skip(delta) {
		var next = logic.skipTime(now(), delta, songDuration());
		if (playing) {
			startAt(next);
			return;
		}
		offset = next;
		renderTimeline();
	}

	function setChannelVolume(channel, percent) {
		var clamped = Math.min(100, Math.max(0, percent));
		channel.volume = clamped / 100;
		paintFader(channel);
		applyGains();
	}

	function bindFader(channel) {
		var fader = channel.el.querySelector('.choir-mixer__fader');
		if (!fader) {
			return;
		}
		function percentFromEvent(event) {
			var rect = fader.getBoundingClientRect();
			var ratio;
			if (!rect.height) {
				return Math.round(channel.volume * 100);
			}
			ratio = 1 - ((event.clientY - rect.top) / rect.height);
			return Math.round(Math.min(1, Math.max(0, ratio)) * 100);
		}
		fader.addEventListener('pointerdown', function (event) {
			fader.setPointerCapture(event.pointerId);
			setChannelVolume(channel, percentFromEvent(event));
		});
		fader.addEventListener('pointermove', function (event) {
			if (!fader.hasPointerCapture(event.pointerId)) {
				return;
			}
			setChannelVolume(channel, percentFromEvent(event));
		});
		fader.addEventListener('keydown', function (event) {
			var current = Math.round(channel.volume * 100);
			var next = null;
			if (event.key === 'ArrowUp' || event.key === 'ArrowRight') {
				next = current + 5;
			} else if (event.key === 'ArrowDown' || event.key === 'ArrowLeft') {
				next = current - 5;
			} else if (event.key === 'PageUp') {
				next = current + 10;
			} else if (event.key === 'PageDown') {
				next = current - 10;
			} else if (event.key === 'Home') {
				next = 0;
			} else if (event.key === 'End') {
				next = 100;
			}
			if (next === null) {
				return;
			}
			event.preventDefault();
			setChannelVolume(channel, next);
		});
	}

	function bindChannel(channel) {
		var muteBtn = channel.el.querySelector('.choir-mixer__mute');
		var soloBtn = channel.el.querySelector('.choir-mixer__solo');
		bindFader(channel);
		paintFader(channel);
		paintFlags(channel);
		if (muteBtn) {
			muteBtn.addEventListener('click', function () {
				channel.mute = !channel.mute;
				paintFlags(channel);
				applyGains();
			});
		}
		if (soloBtn) {
			soloBtn.addEventListener('click', function () {
				channel.solo = !channel.solo;
				paintFlags(channel);
				applyGains();
			});
		}
	}

	Array.prototype.forEach.call(root.querySelectorAll('.choir-mixer__channel'), function (el) {
		var url = el.getAttribute('data-url') || '';
		var nameEl = el.querySelector('.choir-mixer__name');
		var fader = el.querySelector('.choir-mixer__fader');
		var initial = fader ? parseInt(fader.getAttribute('aria-valuenow') || String(defaultPercent), 10) : defaultPercent;
		var channel;
		if (!url) {
			return;
		}
		if (!isFinite(initial)) {
			initial = defaultPercent;
		}
		channel = {
			el: el,
			url: url,
			name: nameEl ? nameEl.textContent.trim() : '',
			buffer: null,
			gain: null,
			source: null,
			failed: false,
			volume: Math.min(1, Math.max(0, initial / 100)),
			mute: false,
			solo: false,
		};
		channels.push(channel);
		bindChannel(channel);
	});

	if (!channels.length) {
		return;
	}

	function openMixer() {
		resetMix();
		root.hidden = false;
		launch.setAttribute('aria-expanded', 'true');
		launch.setAttribute('aria-label', i18n.close || 'Close mixer');
		ensureContext();
		if (!ready && !loading) {
			preload();
		}
		renderTransport();
	}

	function closeMixer() {
		pause();
		root.hidden = true;
		launch.setAttribute('aria-expanded', 'false');
		launch.setAttribute('aria-label', i18n.open || 'Open mixer');
	}

	launch.addEventListener('click', function () {
		if (root.hidden) {
			openMixer();
			return;
		}
		closeMixer();
	});

	playBtn.addEventListener('click', function () {
		ensureContext();
		if (playing) {
			pause();
			return;
		}
		play();
	});

	backBtn.addEventListener('click', function () {
		ensureContext();
		skip(-logic.SKIP_SECONDS);
	});

	forwardBtn.addEventListener('click', function () {
		ensureContext();
		skip(logic.SKIP_SECONDS);
	});

	abBtn.addEventListener('click', function () {
		loop = logic.abAdvance(loop, now());
		renderTransport();
	});

	speedBtn.addEventListener('click', function () {
		var at = now();
		rate = logic.nextSpeed(rate);
		if (playing) {
			startAt(at);
			return;
		}
		renderTransport();
	});

	resetBtn.addEventListener('click', function () {
		resetMix();
	});

	seek.addEventListener('input', function () {
		seeking = true;
		offset = songDuration() * (parseFloat(seek.value) / 1000);
		renderTimeline();
	});

	seek.addEventListener('change', function () {
		seeking = false;
		seekToRatio(parseFloat(seek.value) / 1000);
	});

	window.choirMixer = {
		pause: pause,
	};

	renderTransport();
})();
