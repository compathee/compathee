(function () {
	'use strict';

	const config = window.choirRehearsalFeedback;
	const form = document.getElementById('choir-feedback-form');
	if (!form || !config || !config.restUrl) {
		return;
	}

	const status = form.querySelector('.choir-feedback__status');
	const button = form.querySelector('.choir-feedback__button');
	const defaultLabel = button ? button.textContent : '';

	function fieldValue(name) {
		const field = form.elements.namedItem(name);
		if (!field || typeof field.value !== 'string') {
			return '';
		}
		return field.value;
	}

	function safeIssueUrl(url) {
		if (typeof url !== 'string' || url === '') {
			return '';
		}
		try {
			const parsed = new URL(url);
			if (parsed.protocol !== 'https:' || parsed.hostname !== 'github.com') {
				return '';
			}
			if (!/^\/[^/]+\/[^/]+\/issues\/\d+\/?$/.test(parsed.pathname)) {
				return '';
			}
			return parsed.toString();
		} catch (error) {
			return '';
		}
	}

	function setStatus(kind, message, url) {
		if (!status) {
			return;
		}
		status.hidden = false;
		status.className = 'choir-feedback__status choir-feedback__status--' + kind;
		status.textContent = '';
		status.appendChild(document.createTextNode(message));
		if (url && kind === 'success') {
			const link = document.createElement('a');
			link.href = url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = (config.i18n && config.i18n.viewIssue) || 'View issue';
			status.appendChild(document.createTextNode(' '));
			status.appendChild(link);
		}
	}

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		if (!button || button.disabled) {
			return;
		}

		button.disabled = true;
		button.textContent = (config.i18n && config.i18n.sending) || 'Sending…';
		if (status) {
			status.hidden = true;
			status.textContent = '';
		}

		const payload = {
			type: fieldValue('type'),
			title: fieldValue('title'),
			description: fieldValue('description'),
			email: fieldValue('email')
		};

		fetch(config.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || ''
			},
			body: JSON.stringify(payload)
		}).then(function (response) {
			return response.json().then(function (data) {
				return { ok: response.ok, data: data };
			}).catch(function () {
				return { ok: false, data: null };
			});
		}).then(function (result) {
			const data = result.data || {};
			const fallback = (config.i18n && config.i18n.genericError) || '';
			const message = typeof data.message === 'string' && data.message !== '' ? data.message : fallback;
			if (result.ok) {
				setStatus('success', message, safeIssueUrl(data.url));
				const title = form.elements.namedItem('title');
				const description = form.elements.namedItem('description');
				if (title) {
					title.value = '';
				}
				if (description) {
					description.value = '';
				}
			} else {
				setStatus('error', message, '');
			}
		}).catch(function () {
			setStatus('error', (config.i18n && config.i18n.genericError) || '', '');
		}).finally(function () {
			button.disabled = false;
			button.textContent = defaultLabel || (config.i18n && config.i18n.send) || 'Send';
		});
	});
})();
