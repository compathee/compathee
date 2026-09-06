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
		play: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 5v14l11-7L8 5z"/></svg>',
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
		$row.find('.choir-audio-id').val(audioId || '');
		$row.find('.choir-audio-name').text(filename || i18n.noAudio || 'No audio selected');
		updatePlayButton($row, url || '');
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
		this.stream = null;
		this.mediaRecorder = null;
		this.chunks = [];
		this.blob = null;
		this.mimeType = getSupportedMimeType();
		this.timerId = null;
		this.startedAt = 0;
	}

	Recorder.prototype.resetState = function () {
		this.chunks = [];
		this.blob = null;
		this.startedAt = 0;
		if (this.timerId) {
			window.clearInterval(this.timerId);
			this.timerId = null;
		}
		this.$timer.text('00:00');
		this.$preview.prop('hidden', true).removeAttr('src');
		this.$start.prop('disabled', false);
		this.$stop.prop('disabled', true);
		this.$use.prop('disabled', true).text(i18n.useRecording || 'Use recording');
		this.$status.text(i18n.readyToRecord || 'Click start and sing your voice part.');
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
			this.mediaRecorder.stop();
		}
		this.stopStream();
		this.resetState();
		this.$panel.addClass('is-hidden').attr('aria-hidden', 'true');
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
	};

	Recorder.prototype.start = function () {
		const self = this;

		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
			self.stream = stream;
			self.chunks = [];
			self.blob = null;
			self.mediaRecorder = new MediaRecorder(stream, { mimeType: self.mimeType });
			self.mediaRecorder.ondataavailable = function (event) {
				if (event.data && event.data.size > 0) {
					self.chunks.push(event.data);
				}
			};
			self.mediaRecorder.onstop = function () {
				self.blob = new Blob(self.chunks, { type: self.mimeType });
				const url = URL.createObjectURL(self.blob);
				self.$preview.attr('src', url).prop('hidden', false);
				self.$use.prop('disabled', false);
				self.$status.text(i18n.useRecording || 'Use recording');
				self.stopStream();
			};
			self.mediaRecorder.start();
			self.startedAt = Date.now();
			self.$status.text(i18n.recording || 'Recording…');
			self.$start.prop('disabled', true);
			self.$stop.prop('disabled', false);
			self.$use.prop('disabled', true);
			self.timerId = window.setInterval(function () {
				const elapsed = Math.floor((Date.now() - self.startedAt) / 1000);
				self.$timer.text(formatTime(elapsed));
			}, 250);
		}).catch(function () {
			window.alert(i18n.micDenied || 'Microphone access was denied.');
		});
	};

	Recorder.prototype.stop = function () {
		if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
			this.mediaRecorder.stop();
		}
		if (this.timerId) {
			window.clearInterval(this.timerId);
			this.timerId = null;
		}
		this.$start.prop('disabled', false);
		this.$stop.prop('disabled', true);
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
			updatePlayButton(self.$row, response.data.url || '');
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
		return (
			'<div class="choir-recorder-panel is-hidden" aria-hidden="true">' +
				'<p class="choir-recorder-panel__status">' + (i18n.readyToRecord || 'Click start and sing your voice part.') + '</p>' +
				'<p class="choir-recorder-panel__timer">00:00</p>' +
				'<audio class="choir-recorder-panel__preview" controls hidden></audio>' +
				'<div class="choir-recorder-panel__actions">' +
					'<button type="button" class="button button-primary choir-recorder-start">' + (i18n.startRecording || 'Start recording') + '</button>' +
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
					'<span class="choir-audio-name">' + (i18n.noAudio || 'No audio selected') + '</span>' +
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
			// Ensure viewer is ready after pdf.js loads (script order: pdf then admin).
			window.setTimeout(function () {
				getEditorPdfApi();
			}, 0);
		}

		$('#choir-toggle-public').on('click', function () {
			const next = $('#choir-is-public').val() !== '1';
			syncPublicButton(next);
		});

	});
})(jQuery);
