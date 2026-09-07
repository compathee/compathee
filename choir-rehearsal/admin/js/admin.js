(function ($) {
	'use strict';

	const i18n = choirRehearsalAdmin || {};

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
		const recorder = new Recorder($row);
		$row.data('recorder', recorder);
		recorder.bind();

		$row.find('.choir-select-audio').on('click', function () {
			const frame = wp.media({
				title: i18n.selectAudio || 'Upload / Select',
				button: { text: i18n.useAudio || 'Use this audio' },
				library: { type: 'audio' },
				multiple: false,
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				$row.find('.choir-audio-id').val(attachment.id);
				$row.find('.choir-audio-name').text(attachment.filename || attachment.title || i18n.noAudio || 'No audio selected');
			});

			frame.open();
		});

		$row.find('.choir-remove-track').on('click', function () {
			if (recorder) {
				recorder.close();
			}

			if ($('#choir-tracks-body .choir-track-row').length > 1) {
				$row.remove();
			} else {
				$row.find('.choir-audio-id').val('');
				$row.find('.choir-audio-name').text(i18n.noAudio || 'No audio selected');
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

	function createRow(index) {
		const voices = i18n.voices || {};
		let options = '';
		Object.keys(voices).forEach(function (slug) {
			options += '<option value="' + slug + '">' + voices[slug] + '</option>';
		});

		const html =
			'<tr class="choir-track-row">' +
				'<td>' +
					'<input type="hidden" name="choir_tracks[' + index + '][id]" value="0" />' +
					'<select class="choir-voice-select" name="choir_tracks[' + index + '][voice]">' + options + '</select>' +
				'</td>' +
				'<td class="choir-track-audio-cell">' +
					'<input type="hidden" class="choir-audio-id" name="choir_tracks[' + index + '][audio_id]" value="0" />' +
					'<div class="choir-track-audio-controls">' +
						'<span class="choir-audio-name">' + (i18n.noAudio || 'No audio selected') + '</span> ' +
						'<button type="button" class="button button-small choir-select-audio">' + (i18n.selectAudio || 'Upload') + '</button> ' +
						'<button type="button" class="button button-small choir-record-audio">' + (i18n.recordAudio || 'Record') + '</button>' +
					'</div>' +
					recorderPanelHtml() +
				'</td>' +
				'<td><button type="button" class="button-link-delete choir-remove-track">' + (i18n.removeTrack || 'Remove') + '</button></td>' +
			'</tr>';

		return $(html);
	}

	$(function () {
		$('#choir-tracks-body .choir-track-row').each(function () {
			bindRow($(this));
		});

		$('#choir-add-track').on('click', function () {
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
				$('#choir-score-pdf-id').val(attachment.id);
				$('#choir-score-pdf-name').text(attachment.filename || attachment.title || i18n.noPdf || 'No PDF selected');
			});

			frame.open();
		});

		$('#choir-remove-pdf').on('click', function () {
			$('#choir-score-pdf-id').val('0');
			$('#choir-score-pdf-name').text(i18n.noPdf || 'No PDF selected');
		});
	});
})(jQuery);
