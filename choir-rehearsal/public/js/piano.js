(function () {
	'use strict';

	var sheet = document.getElementById('choir-piano-sheet');
	var player = document.getElementById('choir-sticky-player');
	if (!sheet || !player) {
		return;
	}

	var openBtn = player.querySelector('.choir-sticky-player__piano');
	var closeBtn = sheet.querySelector('.choir-piano-sheet__close');
	var keyboard = sheet.querySelector('.choir-piano-sheet__keyboard');
	var scrollEl = sheet.querySelector('.choir-piano-sheet__scroll');
	var i18n = window.choirRehearsalPiano || {};

	if (!openBtn || !closeBtn || !keyboard) {
		return;
	}

	var START_MIDI = parseInt(keyboard.getAttribute('data-start-midi') || '48', 10);
	var END_MIDI = parseInt(keyboard.getAttribute('data-end-midi') || '71', 10);
	var NOTE_NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

	var audioCtx = null;
	var masterGain = null;
	var activeVoices = Object.create(null);
	var pointerNotes = Object.create(null);
	var isOpen = false;

	function isBlack(midi) {
		var n = midi % 12;
		return n === 1 || n === 3 || n === 6 || n === 8 || n === 10;
	}

	function noteLabel(midi) {
		var name = NOTE_NAMES[midi % 12];
		var octave = Math.floor(midi / 12) - 1;
		return name + octave;
	}

	function ensureAudio() {
		var Ctx = window.AudioContext || window.webkitAudioContext;
		if (!Ctx) {
			return null;
		}
		if (!audioCtx) {
			audioCtx = new Ctx({ latencyHint: 'interactive' });
			masterGain = audioCtx.createGain();
			masterGain.gain.value = 0.22;
			masterGain.connect(audioCtx.destination);
		}
		if (audioCtx.state === 'suspended') {
			audioCtx.resume();
		}
		return audioCtx;
	}

	function midiToFreq(midi) {
		return 440 * Math.pow(2, (midi - 69) / 12);
	}

	function noteOn(midi) {
		var ctx = ensureAudio();
		if (!ctx || !masterGain) {
			return;
		}
		midi = String(midi);
		if (activeVoices[midi]) {
			noteOff(midi, true);
		}

		var freq = midiToFreq(Number(midi));
		var now = ctx.currentTime;
		var osc1 = ctx.createOscillator();
		var osc2 = ctx.createOscillator();
		var filter = ctx.createBiquadFilter();
		var gain = ctx.createGain();

		osc1.type = 'triangle';
		osc2.type = 'sine';
		osc1.frequency.setValueAtTime(freq, now);
		osc2.frequency.setValueAtTime(freq * 2, now);
		osc2.detune.setValueAtTime(6, now);

		filter.type = 'lowpass';
		filter.frequency.setValueAtTime(1800, now);
		filter.Q.setValueAtTime(0.7, now);

		gain.gain.setValueAtTime(0.0001, now);
		gain.gain.exponentialRampToValueAtTime(0.9, now + 0.012);
		gain.gain.exponentialRampToValueAtTime(0.45, now + 0.12);

		osc1.connect(filter);
		osc2.connect(filter);
		filter.connect(gain);
		gain.connect(masterGain);

		osc1.start(now);
		osc2.start(now);

		activeVoices[midi] = { osc1: osc1, osc2: osc2, gain: gain, filter: filter };

		var key = keyboard.querySelector('[data-midi="' + midi + '"]');
		if (key) {
			key.classList.add('is-active');
		}
	}

	function noteOff(midi, immediate) {
		midi = String(midi);
		var voice = activeVoices[midi];
		if (!voice) {
			return;
		}
		delete activeVoices[midi];

		var key = keyboard.querySelector('[data-midi="' + midi + '"]');
		if (key) {
			key.classList.remove('is-active');
		}

		var ctx = audioCtx;
		if (!ctx) {
			return;
		}
		var now = ctx.currentTime;
		try {
			if (immediate) {
				voice.gain.gain.cancelScheduledValues(now);
				voice.gain.gain.setValueAtTime(0.0001, now);
				voice.osc1.stop(now + 0.01);
				voice.osc2.stop(now + 0.01);
			} else {
				voice.gain.gain.cancelScheduledValues(now);
				voice.gain.gain.setValueAtTime(Math.max(voice.gain.gain.value, 0.0001), now);
				voice.gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
				voice.osc1.stop(now + 0.4);
				voice.osc2.stop(now + 0.4);
			}
		} catch (err) {
			// Oscillator may already be stopped.
		}
	}

	function releaseAll() {
		Object.keys(activeVoices).forEach(function (midi) {
			noteOff(midi, false);
		});
		Object.keys(pointerNotes).forEach(function (id) {
			delete pointerNotes[id];
		});
	}

	function buildKeyboard() {
		keyboard.innerHTML = '';
		var whites = document.createElement('div');
		whites.className = 'choir-piano-sheet__whites';
		var blacks = document.createElement('div');
		blacks.className = 'choir-piano-sheet__blacks';
		blacks.setAttribute('aria-hidden', 'true');

		var whiteIndex = 0;
		var midi;
		for (midi = START_MIDI; midi <= END_MIDI; midi++) {
			if (isBlack(midi)) {
				continue;
			}
			var white = document.createElement('button');
			white.type = 'button';
			white.className = 'choir-piano-key choir-piano-key--white';
			white.setAttribute('data-midi', String(midi));
			white.setAttribute('aria-label', noteLabel(midi));
			white.dataset.whiteIndex = String(whiteIndex);
			whites.appendChild(white);
			whiteIndex += 1;
		}

		for (midi = START_MIDI; midi <= END_MIDI; midi++) {
			if (!isBlack(midi)) {
				continue;
			}
			// Place black key between previous white and next white.
			var prevWhiteMidi = midi - 1;
			while (prevWhiteMidi >= START_MIDI && isBlack(prevWhiteMidi)) {
				prevWhiteMidi -= 1;
			}
			var prevWhite = whites.querySelector('[data-midi="' + prevWhiteMidi + '"]');
			var idx = prevWhite ? parseInt(prevWhite.dataset.whiteIndex || '0', 10) : 0;
			var black = document.createElement('button');
			black.type = 'button';
			black.className = 'choir-piano-key choir-piano-key--black';
			black.setAttribute('data-midi', String(midi));
			black.setAttribute('aria-label', noteLabel(midi));
			black.style.left = 'calc((100% / ' + whiteIndex + ') * ' + (idx + 1) + ' - (100% / ' + whiteIndex + ') * 0.32)';
			blacks.appendChild(black);
		}

		keyboard.appendChild(whites);
		keyboard.appendChild(blacks);
		keyboard.style.setProperty('--choir-piano-white-count', String(whiteIndex));
	}

	function syncToggleButtons() {
		var label = isOpen ? (i18n.close || 'Close piano') : (i18n.open || 'Open piano');
		document.querySelectorAll('.choir-sticky-player__piano, .choir-recorder-piano').forEach(function (btn) {
			btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			btn.setAttribute('aria-label', label);
			btn.classList.toggle('is-open', isOpen);
		});
	}

	function openSheet() {
		if (isOpen) {
			return;
		}
		isOpen = true;
		sheet.hidden = false;
		sheet.classList.remove('is-hidden');
		sheet.setAttribute('aria-hidden', 'false');
		document.body.classList.add('choir-piano-open');
		syncToggleButtons();
		ensureAudio();
		if (scrollEl) {
			// Center roughly on middle C area.
			scrollEl.scrollLeft = Math.max(0, (scrollEl.scrollWidth - scrollEl.clientWidth) / 2);
		}
	}

	function closeSheet() {
		if (!isOpen) {
			return;
		}
		isOpen = false;
		releaseAll();
		sheet.classList.add('is-hidden');
		sheet.hidden = true;
		sheet.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('choir-piano-open');
		syncToggleButtons();
	}

	function toggleSheet() {
		if (isOpen) {
			closeSheet();
		} else {
			openSheet();
		}
	}

	function keyFromEventTarget(target) {
		if (!target || !target.closest) {
			return null;
		}
		return target.closest('.choir-piano-key');
	}

	keyboard.addEventListener('pointerdown', function (event) {
		var key = keyFromEventTarget(event.target);
		if (!key) {
			return;
		}
		event.preventDefault();
		var midi = key.getAttribute('data-midi');
		if (!midi) {
			return;
		}
		pointerNotes[event.pointerId] = midi;
		try {
			key.setPointerCapture(event.pointerId);
		} catch (err) {
			// Ignore capture failures.
		}
		noteOn(midi);
	});

	keyboard.addEventListener('pointerup', function (event) {
		var midi = pointerNotes[event.pointerId];
		if (!midi) {
			return;
		}
		delete pointerNotes[event.pointerId];
		noteOff(midi, false);
	});

	keyboard.addEventListener('pointercancel', function (event) {
		var midi = pointerNotes[event.pointerId];
		if (!midi) {
			return;
		}
		delete pointerNotes[event.pointerId];
		noteOff(midi, false);
	});

	// Glide to neighbouring key while dragging with a pressed pointer.
	keyboard.addEventListener('pointermove', function (event) {
		var prev = pointerNotes[event.pointerId];
		if (!prev) {
			return;
		}
		var el = document.elementFromPoint(event.clientX, event.clientY);
		var key = keyFromEventTarget(el);
		if (!key) {
			return;
		}
		var midi = key.getAttribute('data-midi');
		if (!midi || midi === prev) {
			return;
		}
		noteOff(prev, false);
		pointerNotes[event.pointerId] = midi;
		noteOn(midi);
	});

	openBtn.addEventListener('click', function (event) {
		event.preventDefault();
		event.stopPropagation();
		toggleSheet();
	});

	document.addEventListener('click', function (event) {
		var btn = event.target && event.target.closest ? event.target.closest('.choir-recorder-piano') : null;
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

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && isOpen) {
			closeSheet();
		}
	});

	buildKeyboard();

	window.choirPiano = {
		open: openSheet,
		close: closeSheet,
		toggle: toggleSheet,
		isOpen: function () {
			return isOpen;
		},
	};
})();
