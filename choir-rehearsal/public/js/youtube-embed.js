(function () {
	'use strict';

	const i18n = window.choirRehearsalYoutube || {};

	function pauseStickyAudio() {
		const audio = document.querySelector('.choir-sticky-player__audio');
		if (audio && !audio.paused) {
			audio.pause();
		}
	}

	function stopEmbed(mount) {
		if (!mount) {
			return;
		}
		mount.innerHTML = '';
		mount.hidden = true;
		mount.classList.add('is-hidden');
	}

	function buildIframe(embedUrl) {
		const iframe = document.createElement('iframe');
		const sep = embedUrl.indexOf('?') === -1 ? '?' : '&';
		iframe.src = embedUrl + sep + 'rel=0';
		iframe.title = 'YouTube video player';
		iframe.width = '560';
		iframe.height = '315';
		iframe.allow =
			'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		iframe.allowFullscreen = true;
		iframe.referrerPolicy = 'strict-origin-when-cross-origin';
		iframe.setAttribute('loading', 'lazy');
		return iframe;
	}

	function setExpanded(button, mount, open) {
		const openLabel = i18n.open || button.getAttribute('data-open-label') || 'Watch reference video';
		const closeLabel = i18n.close || 'Hide video';
		button.setAttribute('aria-expanded', open ? 'true' : 'false');
		button.textContent = open ? closeLabel : openLabel;

		if (open) {
			pauseStickyAudio();
			const embedUrl = button.getAttribute('data-embed-url') || '';
			if (!embedUrl) {
				return;
			}
			mount.innerHTML = '';
			mount.appendChild(buildIframe(embedUrl));
			mount.hidden = false;
			mount.classList.remove('is-hidden');
			return;
		}

		stopEmbed(mount);
	}

	function collapseOthers(exceptButton) {
		document.querySelectorAll('.choir-youtube-toggle[aria-expanded="true"]').forEach(function (button) {
			if (button === exceptButton) {
				return;
			}
			const controlsId = button.getAttribute('aria-controls');
			const mount = controlsId ? document.getElementById(controlsId) : null;
			if (mount) {
				setExpanded(button, mount, false);
			}
		});
	}

	document.addEventListener('click', function (event) {
		const button = event.target && event.target.closest
			? event.target.closest('.choir-youtube-toggle')
			: null;
		if (!button || button.disabled) {
			return;
		}
		const controlsId = button.getAttribute('aria-controls');
		const mount = controlsId ? document.getElementById(controlsId) : null;
		if (!mount) {
			return;
		}
		const open = button.getAttribute('aria-expanded') !== 'true';
		if (open) {
			collapseOthers(button);
		}
		setExpanded(button, mount, open);
	});

	const stickyAudio = document.querySelector('.choir-sticky-player__audio');
	if (stickyAudio) {
		stickyAudio.addEventListener('play', function () {
			collapseOthers(null);
		});
	}
})();
