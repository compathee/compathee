(function () {
	'use strict';

	var sheet = document.getElementById('choir-metronome-sheet');
	var player = document.getElementById('choir-sticky-player');
	if (!sheet || !player) {
		return;
	}

	var openBtn = player.querySelector('.choir-sticky-player__metronome');
	var closeBtn = sheet.querySelector('.choir-metronome-sheet__close');
	var toggleBtn = sheet.querySelector('.choir-metronome-sheet__toggle');
	var slider = sheet.querySelector('.choir-metronome-sheet__slider');
	var bpmValue = sheet.querySelector('.choir-metronome-sheet__bpm-value');
	var i18n = window.choirRehearsalMetronome || {};

	if (!openBtn || !closeBtn || !toggleBtn || !slider || !bpmValue) {
		return;
	}

	var audioCtx = null;
	var isOpen = false;
	var isRunning = false;
	var bpm = parseInt(slider.value, 10) || 100;
	var nextNoteTime = 0;
	var timerId = null;
	var beatCount = 0;
	var LOOKAHEAD_MS = 25;
	var SCHEDULE_AHEAD = 0.12;

	function ensureAudio() {
		var Ctx = window.AudioContext || window.webkitAudioContext;
		if (!Ctx) {
			return null;
		}
		if (!audioCtx) {
			audioCtx = new Ctx({ latencyHint: 'interactive' });
		}
		if (audioCtx.state === 'suspended') {
			audioCtx.resume();
		}
		return audioCtx;
	}

	function playClick(time, accent) {
		var ctx = ensureAudio();
		if (!ctx) {
			return;
		}
		var osc = ctx.createOscillator();
		var gain = ctx.createGain();
		osc.type = 'square';
		osc.frequency.setValueAtTime(accent ? 1200 : 900, time);
		gain.gain.setValueAtTime(0.0001, time);
		gain.gain.exponentialRampToValueAtTime(accent ? 0.35 : 0.22, time + 0.002);
		gain.gain.exponentialRampToValueAtTime(0.0001, time + 0.06);
		osc.connect(gain);
		gain.connect(ctx.destination);
		osc.start(time);
		osc.stop(time + 0.07);
	}

	function secondsPerBeat() {
		return 60 / Math.max(40, Math.min(208, bpm));
	}

	function scheduler() {
		if (!isRunning || !audioCtx) {
			return;
		}
		while (nextNoteTime < audioCtx.currentTime + SCHEDULE_AHEAD) {
			playClick(nextNoteTime, beatCount % 4 === 0);
			nextNoteTime += secondsPerBeat();
			beatCount += 1;
		}
	}

	function startMetronome() {
		var ctx = ensureAudio();
		if (!ctx) {
			return;
		}
		if (isRunning) {
			return;
		}
		isRunning = true;
		beatCount = 0;
		nextNoteTime = ctx.currentTime + 0.05;
		timerId = window.setInterval(scheduler, LOOKAHEAD_MS);
		toggleBtn.setAttribute('aria-pressed', 'true');
		toggleBtn.textContent = i18n.stop || 'Stop';
		toggleBtn.classList.add('is-running');
		sheet.classList.add('is-running');
	}

	function stopMetronome() {
		isRunning = false;
		if (timerId) {
			window.clearInterval(timerId);
			timerId = null;
		}
		toggleBtn.setAttribute('aria-pressed', 'false');
		toggleBtn.textContent = i18n.start || 'Start';
		toggleBtn.classList.remove('is-running');
		sheet.classList.remove('is-running');
	}

	function setBpm(next) {
		bpm = Math.max(40, Math.min(208, parseInt(next, 10) || 100));
		slider.value = String(bpm);
		bpmValue.textContent = String(bpm);
	}

	function syncToggleButtons() {
		var label = isOpen ? (i18n.close || 'Close metronome') : (i18n.open || 'Open metronome');
		document.querySelectorAll('.choir-sticky-player__metronome, .choir-recorder-metronome').forEach(function (btn) {
			btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			btn.setAttribute('aria-label', label);
			btn.classList.toggle('is-open', isOpen);
		});
	}

	function openSheet() {
		if (isOpen) {
			return;
		}
		if (window.choirPiano && typeof window.choirPiano.close === 'function') {
			window.choirPiano.close();
		}
		isOpen = true;
		sheet.hidden = false;
		sheet.classList.remove('is-hidden');
		sheet.setAttribute('aria-hidden', 'false');
		document.body.classList.add('choir-metronome-open');
		syncToggleButtons();
		ensureAudio();
	}

	function closeSheet() {
		if (!isOpen) {
			return;
		}
		isOpen = false;
		stopMetronome();
		sheet.classList.add('is-hidden');
		sheet.hidden = true;
		sheet.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('choir-metronome-open');
		syncToggleButtons();
	}

	function toggleSheet() {
		if (isOpen) {
			closeSheet();
		} else {
			openSheet();
		}
	}

	openBtn.addEventListener('click', function (event) {
		event.preventDefault();
		event.stopPropagation();
		toggleSheet();
	});

	document.addEventListener('click', function (event) {
		var btn = event.target && event.target.closest ? event.target.closest('.choir-recorder-metronome') : null;
		if (!btn) {
			return;
		}
		event.preventDefault();
		toggleSheet();
	});

	closeBtn.addEventListener('click', function (event) {
		event.preventDefault();
		closeSheet();
	});

	toggleBtn.addEventListener('click', function (event) {
		event.preventDefault();
		if (isRunning) {
			stopMetronome();
		} else {
			startMetronome();
		}
	});

	slider.addEventListener('input', function () {
		setBpm(slider.value);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && isOpen) {
			closeSheet();
		}
	});

	setBpm(bpm);

	window.choirMetronome = {
		open: openSheet,
		close: closeSheet,
		toggle: toggleSheet,
		stop: stopMetronome,
		isOpen: function () {
			return isOpen;
		},
	};
})();
