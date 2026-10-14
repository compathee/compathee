(function () {
	'use strict';

	var COLOR = '#1f4fd8';
	var BAR_GAP = 1;
	var peakCache = Object.create(null);
	var audioCtx = null;

	function waveColor(el) {
		var custom = el && el.getAttribute && el.getAttribute('data-color');
		return custom || COLOR;
	}

	function getAudioContext() {
		if (audioCtx) {
			return audioCtx;
		}
		var Ctx = window.AudioContext || window.webkitAudioContext;
		if (!Ctx) {
			return null;
		}
		audioCtx = new Ctx();
		return audioCtx;
	}

	function fetchPeaks(url) {
		if (peakCache[url]) {
			return peakCache[url];
		}

		peakCache[url] = fetch(url, { credentials: 'same-origin', cache: 'force-cache' })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('waveform-fetch-failed');
				}
				return response.arrayBuffer();
			})
			.then(function (buffer) {
				var ctx = getAudioContext();
				if (!ctx) {
					throw new Error('no-audio-context');
				}
				return ctx.decodeAudioData(buffer.slice(0));
			})
			.then(function (audioBuffer) {
				var channel = audioBuffer.getChannelData(0);
				var samples = channel.length;
				var buckets = 256;
				var block = Math.max(1, Math.floor(samples / buckets));
				var peaks = new Array(buckets);
				var i;
				for (i = 0; i < buckets; i++) {
					var start = i * block;
					var end = Math.min(start + block, samples);
					var max = 0;
					var j;
					for (j = start; j < end; j++) {
						var v = Math.abs(channel[j]);
						if (v > max) {
							max = v;
						}
					}
					peaks[i] = max;
				}
				return peaks;
			})
			.catch(function () {
				delete peakCache[url];
				return null;
			});

		return peakCache[url];
	}

	function drawPeaks(canvas, peaks, color) {
		if (!canvas || !peaks || !peaks.length) {
			return;
		}

		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var cssWidth = Math.max(1, canvas.clientWidth || canvas.parentElement.clientWidth || 120);
		var cssHeight = Math.max(1, canvas.clientHeight || 36);
		canvas.width = Math.floor(cssWidth * dpr);
		canvas.height = Math.floor(cssHeight * dpr);

		var ctx = canvas.getContext('2d');
		if (!ctx) {
			return;
		}

		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		ctx.clearRect(0, 0, cssWidth, cssHeight);

		var barCount = Math.max(24, Math.min(peaks.length, Math.floor(cssWidth / 3)));
		var barWidth = Math.max(1, (cssWidth - (barCount - 1) * BAR_GAP) / barCount);
		var mid = cssHeight / 2;
		var i;

		ctx.fillStyle = color || COLOR;

		for (i = 0; i < barCount; i++) {
			var peakIndex = Math.floor((i / barCount) * peaks.length);
			var amp = peaks[peakIndex] || 0;
			var h = Math.max(2, amp * (cssHeight * 0.9));
			var x = i * (barWidth + BAR_GAP);
			var y = mid - h / 2;
			ctx.fillRect(x, y, barWidth, h);
		}
	}

	function clearCanvas(canvas) {
		if (!canvas) {
			return;
		}
		var ctx = canvas.getContext('2d');
		if (!ctx) {
			return;
		}
		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var cssWidth = Math.max(1, canvas.clientWidth || 1);
		var cssHeight = Math.max(1, canvas.clientHeight || 1);
		canvas.width = Math.floor(cssWidth * dpr);
		canvas.height = Math.floor(cssHeight * dpr);
		ctx.setTransform(1, 0, 0, 1, 0, 0);
		ctx.clearRect(0, 0, canvas.width, canvas.height);
	}

	function loadWaveform(el) {
		if (!el) {
			return;
		}
		var canvas = el.querySelector('canvas') || el;
		if (!canvas || canvas.tagName !== 'CANVAS') {
			return;
		}

		var url = (el.getAttribute('data-audio-url') || canvas.getAttribute('data-audio-url') || '').trim();
		if (!url) {
			el.classList.add('is-empty');
			clearCanvas(canvas);
			return;
		}

		el.classList.remove('is-empty');
		el.classList.add('is-loading');

		fetchPeaks(url).then(function (peaks) {
			el.classList.remove('is-loading');
			if (!peaks) {
				el.classList.add('is-empty');
				clearCanvas(canvas);
				return;
			}
			el._choirPeaks = peaks;
			drawPeaks(canvas, peaks, waveColor(el));
		});
	}

	function observe(el) {
		if (!el || el._choirWaveBound) {
			return;
		}
		el._choirWaveBound = true;

		var canvas = el.querySelector('canvas') || el;

		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (entry.isIntersecting) {
							loadWaveform(el);
							io.unobserve(el);
						}
					});
				},
				{ rootMargin: '80px' }
			);
			io.observe(el);
		} else {
			loadWaveform(el);
		}

		if ('ResizeObserver' in window) {
			var ro = new ResizeObserver(function () {
				if (el._choirPeaks) {
					drawPeaks(canvas, el._choirPeaks, waveColor(el));
				}
			});
			ro.observe(el);
		}
	}

	function refresh(root) {
		if (root && root.classList && root.classList.contains('choir-track-waveform')) {
			delete root._choirPeaks;
			root._choirWaveBound = false;
			loadWaveform(root);
			observe(root);
			return;
		}

		var scope = root && root.querySelectorAll ? root : document;
		scope.querySelectorAll('.choir-track-waveform').forEach(function (el) {
			delete el._choirPeaks;
			el._choirWaveBound = false;
			loadWaveform(el);
			observe(el);
		});
	}

	function initAll(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var nodes = [];
		if (root && root.classList && root.classList.contains('choir-track-waveform')) {
			nodes.push(root);
		}
		scope.querySelectorAll('.choir-track-waveform').forEach(function (el) {
			nodes.push(el);
		});
		nodes.forEach(observe);
	}

	window.choirWaveforms = {
		init: initAll,
		refresh: refresh,
		load: loadWaveform,
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initAll(document);
		});
	} else {
		initAll(document);
	}
})();
