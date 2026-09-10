(function ($) {
	'use strict';

	const i18n = choirRehearsalAdmin || {};

	function syncPublicButton(isPublic) {
		const $btn = $('#choir-toggle-public');
		const $input = $('#choir-is-public');
		const $hint = $('.choir-song-visibility__hint');
		if (!$btn.length || !$input.length) {
			return;
		}
		$input.val(isPublic ? '1' : '0');
		$btn.toggleClass('is-public', isPublic);
		$btn.attr('aria-pressed', isPublic ? 'true' : 'false');
		const label = isPublic ? (i18n.makePrivate || 'Make private') : (i18n.makePublic || 'Make public');
		$btn.attr('title', label);
		$btn.find('.choir-make-public__label').text(label);
		if ($hint.length) {
			$hint.text(isPublic ? (i18n.publicHint || '') : (i18n.privateHint || ''));
		}
	}

	const ICONS = {
		upload: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3l4.5 4.5h-3V14h-3V7.5h-3L12 3zm-7 14h14v2H5v-2z"/></svg>',
		record: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7" fill="currentColor"/></svg>',
		pause: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 5h3.5v14H7V5zm6.5 0H17v14h-3.5V5z"/></svg>',
		stop: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="6" y="6" width="12" height="12" rx="1.5" fill="currentColor"/></svg>',
		cancel: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 6.4l1.2-1.2L12 9.6l4.4-4.4 1.2 1.2L13.2 12l4.4 4.4-1.2 1.2L12 14.4l-4.4 4.4-1.2-1.2L10.8 12 6.4 6.4z"/></svg>',
		piano: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 16.5v-4.5h1V4.5h2v10.5h1v4.5h-4zM8 19.5H5.5c-.55 0-1-.45-1-1V5.5c0-.55.45-1 1-1H7v10.5h1v4.5zm8-4.5h1V4.5h1.5c.55 0 1 .45 1 1v13c0 .55-.45 1-1 1H16v-4.5z"/></svg>',
		metronome: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.5 2l7.5 18H5L12.5 2zm0 3.2L7.4 18h10.2L12.5 5.2zM11 10h1.5v5H11v-5zm0 6h1.5v1.5H11V16z"/></svg>',
		play: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="#ffffff" d="M7 3.8v16.4L20.2 12 7 3.8z"/></svg>',
		remove: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 6.4l1.2-1.2L12 9.6l4.4-4.4 1.2 1.2L13.2 12l4.4 4.4-1.2 1.2L12 14.4l-4.4 4.4-1.2-1.2L10.8 12 6.4 6.4z"/></svg>'
	};

	function nextIndex() {
		return $('#choir-tracks-body .choir-track-row').length;
	}

	function formatTime(totalSeconds) {
		const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
		const seconds = String(totalSeconds % 60).padStart(2, '0');
		return minutes + ':' + seconds;
	}

	function getSupportedMimeType() {
		if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') {
			return '';
		}

		const types = [
			'audio/webm;codecs=opus',
			'audio/webm',
			'audio/ogg;codecs=opus',
			'audio/mp4',
		];

		for (let i = 0; i < types.length; i += 1) {
			if (MediaRecorder.isTypeSupported(types[i])) {
				return types[i];
			}
		}

		return '';
	}

	function extensionFromMime(mimeType) {
		if (mimeType.indexOf('ogg') !== -1) {
			return 'ogg';
		}
		if (mimeType.indexOf('mp4') !== -1) {
			return 'm4a';
		}
		return 'webm';
	}

	function songTitle() {
		const titleInput = document.getElementById('title');
		if (titleInput && titleInput.value) {
			return String(titleInput.value).trim();
		}
		return '';
	}

	function voiceLabel($row) {
		const $select = $row.find('.choir-voice-select');
		const selected = $select.find('option:selected');
		if (selected.length) {
			return String(selected.text() || selected.val() || '').trim();
		}
		return '';
	}

	function trackPlayTitle($row) {
		const song = songTitle();
		const voice = voiceLabel($row);
		if (song && voice) {
			return song + ' — ' + voice;
		}
		return song || voice || (i18n.trackLabel || 'Track');
	}

	function setRowAudio($row, audioId, filename, url) {
		const name = filename || i18n.noAudio || 'No audio selected';
		$row.find('.choir-audio-id').val(audioId || '');
		$row.find('.choir-audio-name').text(name);
		$row.find('.choir-track-waveform').attr('title', name);
		updatePlayButton($row, url || '');
		updateWaveform($row, url || '');
	}

	function clearRowAudio($row) {
		setRowAudio($row, '', i18n.noAudio || 'No audio selected', '');
	}

	function updatePlayButton($row, url) {
		const $play = $row.find('.choir-play-track');
		const hasUrl = Boolean(url);
		$play.attr('data-track-url', hasUrl ? url : '');
		$play.attr('data-track-title', trackPlayTitle($row));
		$play.prop('disabled', !hasUrl);
	}

	function updateWaveform($row, url) {
		const $wave = $row.find('.choir-track-waveform');
		if (!$wave.length) {
			return;
		}
		$wave.attr('data-audio-url', url || '');
		$wave.toggleClass('is-empty', !url);
		if (window.choirWaveforms && typeof window.choirWaveforms.refresh === 'function') {
			window.choirWaveforms.refresh($wave.get(0));
		}
	}

	function syncPlayTitle($row) {
		const $play = $row.find('.choir-play-track');
		$play.attr('data-track-title', trackPlayTitle($row));
	}

	function getEditorPdfApi() {
		const viewer = document.getElementById('choir-editor-pdf-viewer');
		if (!viewer) {
			return null;
		}
		if (typeof window.choirInitPdfViewer === 'function') {
			return window.choirInitPdfViewer(viewer);
		}
		return viewer._choirPdfApi || null;
	}

	function setEditorPdf(url) {
		const viewer = document.getElementById('choir-editor-pdf-viewer');
		const api = getEditorPdfApi();
		if (viewer) {
			viewer.classList.toggle('is-empty', !url);
			viewer.classList.remove('is-error');
		}
		if (api && typeof api.load === 'function') {
			api.load(url || '');
		}
	}

	function Recorder($row) {
		this.$row = $row;
		this.$panel = $row.find('.choir-recorder-panel');
		this.$status = this.$panel.find('.choir-recorder-panel__status');
		this.$timer = this.$panel.find('.choir-recorder-panel__timer');
		this.$preview = this.$panel.find('.choir-recorder-panel__preview');
		this.$start = this.$panel.find('.choir-recorder-start');
		this.$stop = this.$panel.find('.choir-recorder-stop');
		this.$use = this.$panel.find('.choir-recorder-use');
		this.$cancel = this.$panel.find('.choir-recorder-cancel');
		this.$actions = this.$panel.find('.choir-recorder-panel__actions');
		this.stream = null;
		this.mediaRecorder = null;
		this.chunks = [];
		this.blob = null;
		this.mimeType = getSupportedMimeType();
		this.timerId = null;
		this.startedAt = 0;
		this.pausedTotalMs = 0;
		this.pauseStartedAt = 0;
		this.sessionActive = false;
		this.actionsVisible = true;
		this._observer = null;
	}

	Recorder.prototype.getElapsedSeconds = function () {
		if (!this.startedAt) {
			return 0;
		}
		let paused = this.pausedTotalMs;
		if (this.pauseStartedAt) {
			paused += Date.now() - this.pauseStartedAt;
		}
		return Math.max(0, Math.floor((Date.now() - this.startedAt - paused) / 1000));
	};

	Recorder.prototype.updateTimerDisplay = function () {
		this.$timer.text(formatTime(this.getElapsedSeconds()));
	};

	Recorder.prototype.isRecording = function () {
		return !!(this.mediaRecorder && this.mediaRecorder.state === 'recording');
	};

	Recorder.prototype.isPaused = function () {
		return !!(this.mediaRecorder && this.mediaRecorder.state === 'paused');
	};

	Recorder.prototype.isSessionActive = function () {
		return this.sessionActive && this.mediaRecorder && this.mediaRecorder.state !== 'inactive';
	};

	Recorder.prototype.syncUi = function () {
		const recording = this.isRecording();
		const paused = this.isPaused();
		const active = this.isSessionActive();

		if (recording) {
			this.$status.text(i18n.recording || 'Recording…');
			this.$start.prop('disabled', true);
			this.$stop.prop('disabled', false);
		} else if (paused) {
			this.$status.text(i18n.recordingPaused || 'Paused');
			this.$start.prop('disabled', false).text(i18n.resumeRecording || 'Resume recording');
			this.$stop.prop('disabled', false);
		} else {
			this.$start.prop('disabled', false).text(i18n.startRecording || 'Start recording');
			this.$stop.prop('disabled', true);
		}

		if (!active && !this.blob) {
			this.$use.prop('disabled', true);
		}

		RecordingDock.sync(this);
	};

	Recorder.prototype.resetState = function () {
		this.chunks = [];
		this.blob = null;
		this.startedAt = 0;
		this.pausedTotalMs = 0;
		this.pauseStartedAt = 0;
		this.sessionActive = false;
		if (this.timerId) {
			window.clearInterval(this.timerId);
			this.timerId = null;
		}
		this.$timer.text('00:00');
		this.$preview.prop('hidden', true).removeAttr('src');
		this.$start.prop('disabled', false).text(i18n.startRecording || 'Start recording');
		this.$stop.prop('disabled', true);
		this.$use.prop('disabled', true).text(i18n.useRecording || 'Use recording');
		this.$status.text(i18n.readyToRecord || 'Click start and sing your voice part.');
		RecordingDock.sync(this);
	};

	Recorder.prototype.stopStream = function () {
		if (this.stream) {
			this.stream.getTracks().forEach(function (track) {
				track.stop();
			});
			this.stream = null;
		}
	};

	Recorder.prototype.close = function () {
		if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
			try {
				this.mediaRecorder.onstop = null;
				this.mediaRecorder.stop();
			} catch (err) {
				// Ignore stop failures while cancelling.
			}
		}
		this.mediaRecorder = null;
		this.stopStream();
		this.resetState();
		this.$panel.addClass('is-hidden').attr('aria-hidden', 'true');
		RecordingDock.detach(this);
	};

	Recorder.prototype.open = function () {
		if (!i18n.postId) {
			window.alert(i18n.saveSongFirst || 'Save the song first, then you can record voice tracks.');
			return;
		}

		if (!this.mimeType || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			window.alert(i18n.micUnavailable || 'Microphone recording is not supported in this browser.');
			return;
		}

		$('.choir-recorder-panel').not(this.$panel).each(function () {
			const $otherRow = $(this).closest('.choir-track-row');
			if ($otherRow.data('recorder')) {
				$otherRow.data('recorder').close();
			}
		});

		this.resetState();
		this.$panel.removeClass('is-hidden').attr('aria-hidden', 'false');
		RecordingDock.attach(this);
	};

	Recorder.prototype.startTimer = function () {
		const self = this;
		if (this.timerId) {
			window.clearInterval(this.timerId);
		}
		this.updateTimerDisplay();
		this.timerId = window.setInterval(function () {
			self.updateTimerDisplay();
		}, 250);
	};

	Recorder.prototype.start = function () {
		const self = this;

		if (this.isPaused()) {
			this.resume();
			return;
		}

		if (this.isRecording()) {
			return;
		}

		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
			self.stream = stream;
			self.chunks = [];
			self.blob = null;
			self.pausedTotalMs = 0;
			self.pauseStartedAt = 0;
			self.mediaRecorder = new MediaRecorder(stream, { mimeType: self.mimeType });
			self.mediaRecorder.ondataavailable = function (event) {
				if (event.data && event.data.size > 0) {
					self.chunks.push(event.data);
				}
			};
			self.mediaRecorder.onstop = function () {
				self.sessionActive = false;
				self.blob = new Blob(self.chunks, { type: self.mimeType });
				const url = URL.createObjectURL(self.blob);
				self.$preview.attr('src', url).prop('hidden', false);
				self.$use.prop('disabled', false);
				self.$status.text(i18n.useRecording || 'Use recording');
				self.$start.prop('disabled', false).text(i18n.startRecording || 'Start recording');
				self.$stop.prop('disabled', true);
				self.stopStream();
				self.mediaRecorder = null;
				RecordingDock.sync(self);
			};
			self.mediaRecorder.start(1000);
			self.sessionActive = true;
			self.startedAt = Date.now();
			self.startTimer();
			self.syncUi();
		}).catch(function () {
			window.alert(i18n.micDenied || 'Microphone access was denied.');
		});
	};

	Recorder.prototype.pause = function () {
		if (!this.mediaRecorder || this.mediaRecorder.state !== 'recording') {
			return;
		}
		if (typeof this.mediaRecorder.pause !== 'function') {
			return;
		}
		try {
			this.mediaRecorder.pause();
		} catch (err) {
			return;
		}
		this.pauseStartedAt = Date.now();
		this.syncUi();
	};

	Recorder.prototype.resume = function () {
		if (!this.mediaRecorder || this.mediaRecorder.state !== 'paused') {
			return;
		}
		if (typeof this.mediaRecorder.resume !== 'function') {
			return;
		}
		try {
			this.mediaRecorder.resume();
		} catch (err) {
			return;
		}
		if (this.pauseStartedAt) {
			this.pausedTotalMs += Date.now() - this.pauseStartedAt;
			this.pauseStartedAt = 0;
		}
		this.syncUi();
	};

	Recorder.prototype.stop = function () {
		if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
			if (this.pauseStartedAt) {
				this.pausedTotalMs += Date.now() - this.pauseStartedAt;
				this.pauseStartedAt = 0;
			}
			try {
				this.mediaRecorder.stop();
			} catch (err) {
				// Ignore.
			}
		}
		if (this.timerId) {
			window.clearInterval(this.timerId);
			this.timerId = null;
		}
		this.sessionActive = false;
		this.$start.prop('disabled', false).text(i18n.startRecording || 'Start recording');
		this.$stop.prop('disabled', true);
		RecordingDock.sync(this);
	};

	Recorder.prototype.upload = function () {
		const self = this;

		if (!this.blob || !i18n.ajaxUrl || !i18n.recordingNonce || !i18n.postId) {
			return;
		}

		const formData = new FormData();
		const voice = this.$row.find('select[name*="[voice]"]').val() || 'other';
		const filename = 'voice-recording.' + extensionFromMime(this.mimeType);

		formData.append('action', 'choir_rehearsal_upload_recording');
		formData.append('nonce', i18n.recordingNonce);
		formData.append('post_id', String(i18n.postId));
		formData.append('voice', voice);
		formData.append('recording', this.blob, filename);

		this.$use.prop('disabled', true).text(i18n.uploading || 'Uploading…');

		$.ajax({
			url: i18n.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				window.alert((response && response.data && response.data.message) || i18n.uploadFailed || 'Upload failed. Please try again.');
				self.$use.prop('disabled', false).text(i18n.useRecording || 'Use recording');
				return;
			}

			self.$row.find('.choir-audio-id').val(response.data.id);
			self.$row.find('.choir-audio-name').text(response.data.filename || i18n.useAudio || 'Use this audio');
			self.$row.find('.choir-track-waveform').attr('title', response.data.filename || i18n.useAudio || 'Use this audio');
			updatePlayButton(self.$row, response.data.url || '');
			updateWaveform(self.$row, response.data.url || '');
			self.close();
		}).fail(function (xhr) {
			let message = i18n.uploadFailed || 'Upload failed. Please try again.';
			if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				message = xhr.responseJSON.data.message;
			}
			window.alert(message);
			self.$use.prop('disabled', false).text(i18n.useRecording || 'Use recording');
		});
	};

	Recorder.prototype.bind = function () {
		const self = this;

		this.$row.find('.choir-record-audio').on('click', function () {
			self.open();
		});

		this.$start.on('click', function () {
			self.start();
		});

		this.$stop.on('click', function () {
			self.stop();
		});

		this.$use.on('click', function () {
			self.upload();
		});

		this.$cancel.on('click', function () {
			self.close();
		});
	};

	const RecordingDock = {
		$el: null,
		active: null,
		mq: null,

		ensure: function () {
			if (this.$el && this.$el.length) {
				return this.$el;
			}
			const html =
				'<div id="choir-recording-dock" class="choir-recording-dock" hidden aria-hidden="true" role="toolbar" aria-label="' +
				(i18n.recording || 'Recording') +
				'">' +
					'<button type="button" class="choir-recording-dock__btn choir-recording-dock__record" aria-label="' + (i18n.resumeRecording || 'Resume recording') + '">' + ICONS.record + '</button>' +
					'<button type="button" class="choir-recording-dock__btn choir-recording-dock__pause" aria-label="' + (i18n.pauseRecording || 'Pause recording') + '">' + ICONS.pause + '</button>' +
					'<button type="button" class="choir-recording-dock__btn choir-recording-dock__stop" aria-label="' + (i18n.stopRecording || 'Stop') + '">' + ICONS.stop + '</button>' +
					'<button type="button" class="choir-recording-dock__btn choir-recording-dock__cancel" aria-label="' + (i18n.cancelRecording || 'Cancel') + '">' + ICONS.cancel + '</button>' +
				'</div>';
			this.$el = $(html).appendTo(document.body);
			const self = this;
			this.$el.on('click', '.choir-recording-dock__record', function (event) {
				event.preventDefault();
				if (self.active) {
					self.active.start();
				}
			});
			this.$el.on('click', '.choir-recording-dock__pause', function (event) {
				event.preventDefault();
				if (self.active) {
					self.active.pause();
				}
			});
			this.$el.on('click', '.choir-recording-dock__stop', function (event) {
				event.preventDefault();
				if (self.active) {
					self.active.stop();
				}
			});
			this.$el.on('click', '.choir-recording-dock__cancel', function (event) {
				event.preventDefault();
				if (self.active) {
					self.active.close();
				}
			});
			this.mq = window.matchMedia('(max-width: 782px)');
			if (this.mq.addEventListener) {
				this.mq.addEventListener('change', function () {
					self.sync(self.active);
				});
			} else if (this.mq.addListener) {
				this.mq.addListener(function () {
					self.sync(self.active);
				});
			}
			return this.$el;
		},

		isMobile: function () {
			return !!(this.mq && this.mq.matches);
		},

		attach: function (recorder) {
			this.ensure();
			this.active = recorder;
			const self = this;
			if (recorder._observer) {
				recorder._observer.disconnect();
			}
			const target = recorder.$actions.get(0);
			if (target && 'IntersectionObserver' in window) {
				recorder._observer = new IntersectionObserver(function (entries) {
					const entry = entries[0];
					recorder.actionsVisible = !!(entry && entry.isIntersecting && entry.intersectionRatio > 0.15);
					self.sync(recorder);
				}, { threshold: [0, 0.15, 1] });
				recorder._observer.observe(target);
				const rect = target.getBoundingClientRect();
				recorder.actionsVisible = rect.bottom > 0 && rect.top < window.innerHeight;
			} else {
				recorder.actionsVisible = true;
			}
			this.sync(recorder);
		},

		detach: function (recorder) {
			if (recorder && recorder._observer) {
				recorder._observer.disconnect();
				recorder._observer = null;
			}
			if (this.active === recorder) {
				this.active = null;
			}
			this.hide();
		},

		shouldShow: function (recorder) {
			if (!recorder || !this.isMobile()) {
				return false;
			}
			if (!recorder.isSessionActive()) {
				return false;
			}
			const pdfFullscreen = document.body.classList.contains('choir-pdf-fullscreen-open');
			return pdfFullscreen || !recorder.actionsVisible;
		},

		hide: function () {
			if (!this.$el) {
				return;
			}
			this.$el.removeClass('is-visible is-recording is-paused').attr({ hidden: true, 'aria-hidden': 'true' });
			document.body.classList.remove('choir-recording-dock-open');
			document.documentElement.style.removeProperty('--choir-recording-dock-height');
		},

		sync: function (recorder) {
			if (this._syncing) {
				return;
			}
			this._syncing = true;
			try {
				if (!recorder || this.active !== recorder || !this.shouldShow(recorder)) {
					this.hide();
					return;
				}

				this.ensure();
				const recording = recorder.isRecording();
				const paused = recorder.isPaused();
				this.$el.addClass('is-visible')
					.toggleClass('is-recording', recording)
					.toggleClass('is-paused', paused)
					.removeAttr('hidden')
					.attr('aria-hidden', 'false');
				if (!document.body.classList.contains('choir-recording-dock-open')) {
					document.body.classList.add('choir-recording-dock-open');
				}

				this.$el.find('.choir-recording-dock__record').prop('disabled', recording);
				this.$el.find('.choir-recording-dock__pause').prop('disabled', !recording);
				this.$el.find('.choir-recording-dock__stop').prop('disabled', !(recording || paused));

				const height = Math.ceil(this.$el.outerHeight() || 52);
				document.documentElement.style.setProperty('--choir-recording-dock-height', height + 'px');
				this.updateOffset();
			} finally {
				this._syncing = false;
			}
		},

		updateOffset: function () {
			if (!this.$el || !this.$el.hasClass('is-visible')) {
				return;
			}
			const player = document.getElementById('choir-sticky-player');
			let bottom = 0;
			if (player && !player.classList.contains('is-hidden') && document.body.classList.contains('choir-sticky-player-open')) {
				bottom = Math.ceil(player.getBoundingClientRect().height) || 56;
			}
			const piano = document.getElementById('choir-piano-sheet');
			const metro = document.getElementById('choir-metronome-sheet');
			if (piano && !piano.hidden && !piano.classList.contains('is-hidden')) {
				bottom = Math.max(bottom, Math.ceil(window.innerHeight - piano.getBoundingClientRect().top));
			}
			if (metro && !metro.hidden && !metro.classList.contains('is-hidden')) {
				bottom = Math.max(bottom, Math.ceil(window.innerHeight - metro.getBoundingClientRect().top));
			}
			const nextBottom = 'calc(' + bottom + 'px + env(safe-area-inset-bottom, 0px))';
			if (this.$el[0].style.bottom !== nextBottom) {
				this.$el.css('bottom', nextBottom);
			}

			if (document.body.classList.contains('choir-pdf-fullscreen-open')) {
				const dockHeight = Math.ceil(this.$el.outerHeight() || 52);
				const reserve = (bottom + dockHeight + 8) + 'px';
				if (document.documentElement.style.getPropertyValue('--choir-pdf-player-reserve') !== reserve) {
					document.documentElement.style.setProperty('--choir-pdf-player-reserve', reserve);
				}
			}
		},
	};

	window.addEventListener('resize', function () {
		if (!RecordingDock.active) {
			return;
		}
		RecordingDock.updateOffset();
		RecordingDock.sync(RecordingDock.active);
	});

	document.addEventListener('scroll', function () {
		if (!RecordingDock.active || !RecordingDock.$el || !RecordingDock.$el.hasClass('is-visible')) {
			return;
		}
		RecordingDock.updateOffset();
	}, true);

	// PDF expand/collapse toggles body class; refresh dock without a MutationObserver
	// (observing body.class + mutating it caused freezes on the song editor).
	document.addEventListener('click', function (event) {
		if (!RecordingDock.active) {
			return;
		}
		var t = event.target && event.target.closest ? event.target.closest('.choir-pdf-expand, .choir-pdf-close-fs') : null;
		if (!t) {
			return;
		}
		window.setTimeout(function () {
			RecordingDock.sync(RecordingDock.active);
			RecordingDock.updateOffset();
		}, 0);
	});


	function bindRow($row) {
		if (i18n.isPro) {
			const recorder = new Recorder($row);
			$row.data('recorder', recorder);
			recorder.bind();
		}

		$row.find('.choir-select-audio').on('click', function () {
			const frame = wp.media({
				title: i18n.selectAudio || 'Upload / Select',
				button: { text: i18n.useAudio || 'Use this audio' },
				library: { type: 'audio' },
				multiple: false,
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				setRowAudio(
					$row,
					attachment.id,
					attachment.filename || attachment.title || i18n.noAudio || 'No audio selected',
					attachment.url || ''
				);
			});

			frame.open();
		});

		$row.find('.choir-voice-select').on('change', function () {
			syncPlayTitle($row);
		});

		$row.find('.choir-remove-track').on('click', function () {
			const recorder = $row.data('recorder');
			if (recorder) {
				recorder.close();
			}

			if ($('#choir-tracks-body .choir-track-row').length > 1) {
				$row.remove();
			} else {
				clearRowAudio($row);
			}
		});
	}

	function recorderPanelHtml() {
		const pianoLabel = i18n.openPiano || 'Open piano';
		const metronomeLabel = i18n.openMetronome || 'Open metronome';
		return (
			'<div class="choir-recorder-panel is-hidden" aria-hidden="true">' +
				'<p class="choir-recorder-panel__status">' + (i18n.readyToRecord || 'Click start and sing your voice part.') + '</p>' +
				'<p class="choir-recorder-panel__timer">00:00</p>' +
				'<audio class="choir-recorder-panel__preview" controls hidden></audio>' +
				'<div class="choir-recorder-panel__actions">' +
					'<div class="choir-recorder-panel__start-row">' +
						'<button type="button" class="button button-primary choir-recorder-start">' + (i18n.startRecording || 'Start recording') + '</button>' +
						'<button type="button" class="button choir-recorder-piano" title="' + pianoLabel + '" aria-label="' + pianoLabel + '" aria-expanded="false" aria-controls="choir-piano-sheet">' +
							ICONS.piano +
						'</button>' +
						'<button type="button" class="button choir-recorder-metronome" title="' + metronomeLabel + '" aria-label="' + metronomeLabel + '" aria-expanded="false" aria-controls="choir-metronome-sheet">' +
							ICONS.metronome +
						'</button>' +
					'</div>' +
					'<button type="button" class="button choir-recorder-stop" disabled>' + (i18n.stopRecording || 'Stop') + '</button>' +
					'<button type="button" class="button button-primary choir-recorder-use" disabled>' + (i18n.useRecording || 'Use recording') + '</button>' +
					'<button type="button" class="button choir-recorder-cancel">' + (i18n.cancelRecording || 'Cancel') + '</button>' +
				'</div>' +
			'</div>'
		);
	}

	function iconButton(className, label, icon, extraAttrs) {
		return (
			'<button type="button" class="choir-icon-btn ' + className + '" title="' + label + '" aria-label="' + label + '"' + (extraAttrs || '') + '>' +
				ICONS[icon] +
			'</button>'
		);
	}

	function createRow(index) {
		const voices = i18n.voices || {};
		let options = '';
		Object.keys(voices).forEach(function (slug) {
			options += '<option value="' + slug + '">' + voices[slug] + '</option>';
		});

		const recordButton = i18n.isPro
			? iconButton('choir-record-audio', i18n.recordAudio || 'Record', 'record')
			: '';
		const playButton = i18n.canPlay
			? iconButton('choir-play-track', i18n.playAudio || 'Play', 'play', ' data-track-url="" data-track-title="" disabled')
			: '';
		const recorderPanel = i18n.isPro ? recorderPanelHtml() : '';

		const html =
			'<li class="choir-track-item choir-track-row">' +
				'<input type="hidden" name="choir_tracks[' + index + '][id]" value="0" />' +
				'<input type="hidden" class="choir-audio-id" name="choir_tracks[' + index + '][audio_id]" value="0" />' +
				'<div class="choir-track-item__main">' +
					'<select class="choir-voice-select choir-track-voice" name="choir_tracks[' + index + '][voice]" aria-label="Voice">' + options + '</select>' +
					'<span class="choir-audio-name screen-reader-text">' + (i18n.noAudio || 'No audio selected') + '</span>' +
				'</div>' +
				'<div class="choir-track-waveform is-empty" data-audio-url="" title="' + (i18n.noAudio || 'No audio selected') + '" aria-hidden="true">' +
					'<canvas class="choir-track-waveform__canvas"></canvas>' +
				'</div>' +
				'<div class="choir-track-item__actions">' +
					iconButton('choir-select-audio', i18n.selectAudio || 'Upload', 'upload') +
					recordButton +
					playButton +
					iconButton('choir-icon-btn--danger choir-remove-track', i18n.removeTrack || 'Remove', 'remove') +
				'</div>' +
				recorderPanel +
			'</li>';

		return $(html);
	}

	function canAddTrack() {
		const maxTracks = parseInt(i18n.maxTracks, 10) || 0;
		if (!maxTracks) {
			return true;
		}
		return $('#choir-tracks-body .choir-track-row').length < maxTracks;
	}

	$(function () {
		$('#choir-tracks-body .choir-track-row').each(function () {
			bindRow($(this));
		});

		$('#title').on('input change', function () {
			$('#choir-tracks-body .choir-track-row').each(function () {
				syncPlayTitle($(this));
			});
		});

		$('#choir-add-track').on('click', function () {
			if (!canAddTrack()) {
				window.alert(i18n.trackLimitMsg || 'Track limit reached.');
				return;
			}
			const $row = createRow(nextIndex());
			$('#choir-tracks-body').append($row);
			bindRow($row);
		});

		$('#choir-select-pdf').on('click', function () {
			const frame = wp.media({
				title: i18n.selectPdf || 'Select PDF',
				button: { text: i18n.usePdf || 'Use this PDF' },
				library: { type: 'application/pdf' },
				multiple: false,
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				const url = attachment.url || '';
				$('#choir-score-pdf-id').val(attachment.id);
				$('#choir-score-pdf-url').val(url);
				$('#choir-score-pdf-name').text(attachment.filename || attachment.title || i18n.noPdf || 'No PDF selected');
				if (i18n.canViewPdf) {
					setEditorPdf(url);
				}
			});

			frame.open();
		});

		$('#choir-remove-pdf').on('click', function () {
			$('#choir-score-pdf-id').val('0');
			$('#choir-score-pdf-url').val('');
			$('#choir-score-pdf-name').text(i18n.noPdf || 'No PDF selected');
			if (i18n.canViewPdf) {
				setEditorPdf('');
			}
		});

		if (i18n.canViewPdf) {
			// Ensure viewer loads after DOM ready from the hidden URL field (canonical),
			// not only data-pdf-url from the early pdf-viewer auto-init.
			window.setTimeout(function () {
				const url = String(
					$('#choir-score-pdf-url').val() ||
						$('#choir-editor-pdf-viewer').attr('data-pdf-url') ||
						''
				);
				if (url) {
					setEditorPdf(url);
				} else {
					getEditorPdfApi();
				}
			}, 0);
		}

		$('#choir-toggle-public').on('click', function () {
			const next = $('#choir-is-public').val() !== '1';
			syncPublicButton(next);
		});

	});
})(jQuery);
