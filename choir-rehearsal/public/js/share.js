(function () {
	'use strict';

	const i18n = window.choirRehearsalShare || {};

	function closestShare(el) {
		return el && el.closest ? el.closest('.choir-share') : null;
	}

	function setExpanded(root, open) {
		const toggle = root.querySelector('.choir-share__toggle');
		const menu = root.querySelector('.choir-share__menu');
		if (!toggle || !menu) {
			return;
		}
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) {
			menu.hidden = false;
		} else {
			menu.hidden = true;
			const status = root.querySelector('.choir-share__status');
			if (status) {
				status.textContent = '';
			}
		}
	}

	function closeAll(except) {
		document.querySelectorAll('.choir-share').forEach(function (root) {
			if (except && root === except) {
				return;
			}
			setExpanded(root, false);
		});
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

	function statusMessage(type, isPublic) {
		if (type === 'private') {
			return i18n.copiedPrivate || 'Private link copied';
		}
		if (!isPublic) {
			return i18n.copiedPublicPrivate || 'Link copied. Song is still private for guests.';
		}
		return i18n.copiedPublic || 'Public link copied';
	}

	document.addEventListener('click', function (event) {
		const target = event.target;
		if (!(target instanceof Element)) {
			return;
		}

		const toggle = target.closest('.choir-share__toggle');
		if (toggle) {
			const root = closestShare(toggle);
			if (!root) {
				return;
			}
			const open = toggle.getAttribute('aria-expanded') !== 'true';
			closeAll(root);
			setExpanded(root, open);
			return;
		}

		const option = target.closest('.choir-share__option');
		if (option) {
			const root = closestShare(option);
			if (!root) {
				return;
			}
			const url = root.getAttribute('data-share-url') || '';
			const type = option.getAttribute('data-share-type') || 'public';
			const isPublic = root.getAttribute('data-is-public') === '1';
			const status = root.querySelector('.choir-share__status');
			if (!url) {
				return;
			}
			copyText(url).then(function (ok) {
				if (!status) {
					return;
				}
				status.textContent = ok
					? statusMessage(type, isPublic)
					: (i18n.copyFailed || 'Could not copy the link.');
			});
			return;
		}

		if (!closestShare(target)) {
			closeAll();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeAll();
		}
	});
})();
