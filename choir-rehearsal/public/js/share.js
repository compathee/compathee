(function () {
	'use strict';

	const i18n = window.choirRehearsalShare || {};
	const HIDE_MS = 2400;

	function closestShare(el) {
		return el && el.closest ? el.closest('.choir-share') : null;
	}

	function fallbackCopy(text) {
		const area = document.createElement('textarea');
		area.value = text;
		area.setAttribute('readonly', '');
		area.style.position = 'fixed';
		area.style.top = '-9999px';
		area.style.left = '-9999px';
		document.body.appendChild(area);
		area.select();
		let ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {
			ok = false;
		}
		document.body.removeChild(area);
		return ok;
	}

	function copyText(text) {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			return navigator.clipboard.writeText(text).then(function () {
				return true;
			}).catch(function () {
				return fallbackCopy(text);
			});
		}
		return Promise.resolve(fallbackCopy(text));
	}

	function clearHideTimer(root) {
		if (root._choirShareHideTimer) {
			window.clearTimeout(root._choirShareHideTimer);
			root._choirShareHideTimer = null;
		}
	}

	function showStatus(root, message, isError) {
		const status = root.querySelector('.choir-share__status');
		const button = root.querySelector('.choir-share__button');
		if (!status) {
			return;
		}
		clearHideTimer(root);
		status.textContent = message;
		status.hidden = false;
		status.classList.toggle('is-error', !!isError);
		if (button) {
			button.setAttribute('aria-describedby', status.id || '');
		}
		root._choirShareHideTimer = window.setTimeout(function () {
			status.textContent = '';
			status.hidden = true;
			status.classList.remove('is-error');
			root._choirShareHideTimer = null;
		}, HIDE_MS);
	}

	document.addEventListener('click', function (event) {
		const target = event.target;
		if (!(target instanceof Element)) {
			return;
		}

		const button = target.closest('.choir-share__button');
		if (!button) {
			return;
		}

		const root = closestShare(button);
		if (!root) {
			return;
		}

		const url = root.getAttribute('data-share-url') || '';
		if (!url) {
			return;
		}

		copyText(url).then(function (ok) {
			showStatus(
				root,
				ok ? (i18n.copied || 'Link copied to clipboard') : (i18n.copyFailed || 'Could not copy the link.'),
				!ok
			);
		});
	});
})();
