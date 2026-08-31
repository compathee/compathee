(function ($) {
	'use strict';

	function nextIndex() {
		return $('#choir-tracks-body .choir-track-row').length;
	}

	function bindRow($row) {
		$row.find('.choir-select-audio').on('click', function () {
			const frame = wp.media({
				title: choirRehearsalAdmin.selectAudio,
				button: { text: choirRehearsalAdmin.useAudio },
				library: { type: 'audio' },
				multiple: false,
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				$row.find('.choir-audio-id').val(attachment.id);
				$row.find('.choir-audio-name').text(attachment.filename || attachment.title || choirRehearsalAdmin.noAudio);
			});

			frame.open();
		});

		$row.find('.choir-remove-track').on('click', function () {
			if ($('#choir-tracks-body .choir-track-row').length > 1) {
				$row.remove();
			} else {
				$row.find('.choir-audio-id').val('');
				$row.find('.choir-audio-name').text(choirRehearsalAdmin.noAudio);
			}
		});
	}

	function createRow(index) {
		const voices = choirRehearsalAdmin.voices || {};
		let options = '';
		Object.keys(voices).forEach(function (slug) {
			options += '<option value="' + slug + '">' + voices[slug] + '</option>';
		});

		const html =
			'<tr class="choir-track-row">' +
				'<td>' +
					'<input type="hidden" name="choir_tracks[' + index + '][id]" value="0" />' +
					'<select name="choir_tracks[' + index + '][voice]">' + options + '</select>' +
				'</td>' +
				'<td>' +
					'<input type="hidden" class="choir-audio-id" name="choir_tracks[' + index + '][audio_id]" value="0" />' +
					'<span class="choir-audio-name">' + choirRehearsalAdmin.noAudio + '</span> ' +
					'<button type="button" class="button choir-select-audio">' + choirRehearsalAdmin.selectAudio + '</button>' +
				'</td>' +
				'<td><button type="button" class="button-link-delete choir-remove-track">' + choirRehearsalAdmin.removeTrack + '</button></td>' +
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
				title: choirRehearsalAdmin.selectPdf,
				button: { text: choirRehearsalAdmin.usePdf },
				library: { type: 'application/pdf' },
				multiple: false,
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				$('#choir-score-pdf-id').val(attachment.id);
				$('#choir-score-pdf-name').text(attachment.filename || attachment.title || choirRehearsalAdmin.noPdf);
			});

			frame.open();
		});

		$('#choir-remove-pdf').on('click', function () {
			$('#choir-score-pdf-id').val('0');
			$('#choir-score-pdf-name').text(choirRehearsalAdmin.noPdf);
		});
	});
})(jQuery);
