(function ($) {
	'use strict';

	var cfg = window.choirRehearsalProBackup || {};
	var CHUNK = Math.max(256 * 1024, parseInt(cfg.chunkBytes, 10) || 2 * 1024 * 1024);

	function formatBytes(n) {
		if (n >= 1048576) {
			return (n / 1048576).toFixed(1) + ' MB';
		}
		if (n >= 1024) {
			return Math.round(n / 1024) + ' KB';
		}
		return n + ' B';
	}

	function randomId() {
		var bytes = new Uint8Array(16);
		if (window.crypto && window.crypto.getRandomValues) {
			window.crypto.getRandomValues(bytes);
		} else {
			for (var i = 0; i < bytes.length; i++) {
				bytes[i] = Math.floor(Math.random() * 256);
			}
		}
		return Array.prototype.map
			.call(bytes, function (b) {
				return ('0' + b.toString(16)).slice(-2);
			})
			.join('');
	}

	function setStatus($el, text, isError) {
		if (!$el.length) {
			return;
		}
		$el.text(text || '').toggleClass('notice-error', !!isError).toggleClass('notice-info', !isError && !!text);
		$el.toggle(!!text);
	}

	function postForm(data) {
		return $.ajax({
			url: cfg.ajaxUrl,
			method: 'POST',
			data: data,
			processData: false,
			contentType: false,
		});
	}

	function uploadChunks(file, matchMode, $status, $btn) {
		var uploadId = randomId();
		var total = Math.max(1, Math.ceil(file.size / CHUNK));
		var index = 0;

		function next() {
			if (index >= total) {
				setStatus($status, cfg.i18n.importing || 'Importing…', false);
				var finish = new FormData();
				finish.append('action', 'choir_rehearsal_pro_import_finish');
				finish.append('nonce', cfg.nonce);
				finish.append('upload_id', uploadId);
				finish.append('match_mode', matchMode);
				finish.append('filename', file.name || 'backup.zip');
				return postForm(finish).then(function (res) {
					if (!res || !res.success) {
						throw new Error((res && res.data && res.data.message) || cfg.i18n.failed || 'Import failed.');
					}
					if (res.data && res.data.redirect) {
						window.location.href = res.data.redirect;
						return;
					}
					window.location.reload();
				});
			}

			var start = index * CHUNK;
			var blob = file.slice(start, Math.min(file.size, start + CHUNK));
			var fd = new FormData();
			fd.append('action', 'choir_rehearsal_pro_import_chunk');
			fd.append('nonce', cfg.nonce);
			fd.append('upload_id', uploadId);
			fd.append('index', String(index));
			fd.append('total', String(total));
			fd.append('chunk', blob, 'chunk.bin');

			setStatus(
				$status,
				(cfg.i18n.uploading || 'Uploading…') +
					' ' +
					(index + 1) +
					'/' +
					total +
					' (' +
					formatBytes(Math.min(file.size, start + CHUNK)) +
					' / ' +
					formatBytes(file.size) +
					')',
				false
			);

			return postForm(fd).then(function (res) {
				if (!res || !res.success) {
					throw new Error((res && res.data && res.data.message) || cfg.i18n.failed || 'Upload failed.');
				}
				index += 1;
				return next();
			});
		}

		$btn.prop('disabled', true);
		return $.when(next()).always(function () {
			$btn.prop('disabled', false);
		});
	}

	$(function () {
		var $form = $('#choir-rehearsal-pro-import-form');
		if (!$form.length || !cfg.ajaxUrl) {
			return;
		}

		var $file = $('#choir-rehearsal-pro-import-file');
		var $btn = $form.find('button[type="submit"]');
		var $status = $('#choir-rehearsal-pro-import-status');

		$form.on('submit', function (event) {
			event.preventDefault();
			var input = $file.get(0);
			var file = input && input.files && input.files[0] ? input.files[0] : null;
			if (!file) {
				setStatus($status, cfg.i18n.noFile || 'Choose a backup .zip file.', true);
				return;
			}

			var matchMode = String($form.find('input[name="match_mode"]:checked').val() || 'skip');
			uploadChunks(file, matchMode, $status, $btn).then(null, function (err) {
				var msg = cfg.i18n.failed || 'Import failed.';
				if (err && err.responseJSON && err.responseJSON.data && err.responseJSON.data.message) {
					msg = err.responseJSON.data.message;
				} else if (err && err.message) {
					msg = err.message;
				}
				setStatus($status, msg, true);
			});
		});
	});
})(jQuery);
