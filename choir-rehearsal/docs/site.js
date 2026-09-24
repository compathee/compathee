(function () {
	'use strict';

	var cfg = window.REHEARSAL_PAGE || {};
	var KEY = 'choir-rehearsal-lang';
	var aliases = {
		en: 'en', eng: 'en',
		et: 'et', est: 'et',
		ru: 'ru', rus: 'ru',
		de: 'de', deu: 'de', ger: 'de',
		fr: 'fr', fra: 'fr', fre: 'fr',
		it: 'it', ita: 'it',
		es: 'es', esp: 'es', spa: 'es',
		sv: 'sv', swe: 'sv',
		fi: 'fi', fin: 'fi'
	};

	function normalizeLang(value) {
		var v = String(value || '').toLowerCase().replace('_', '-');
		if (aliases[v]) return aliases[v];
		var primary = v.split('-')[0];
		return aliases[primary] || '';
	}

	function save(code) {
		try { localStorage.setItem(KEY, code); } catch (e) {}
	}

	function stored() {
		try { return normalizeLang(localStorage.getItem(KEY)); }
		catch (e) { return ''; }
	}

	function cookieLang() {
		var match = document.cookie.match(/(?:^|; )choir-rehearsal-lang=([^;]*)/);
		if (!match) return '';
		document.cookie = 'choir-rehearsal-lang=; Max-Age=0; path=/';
		document.cookie = 'choir-rehearsal-lang=; Max-Age=0; path=/; domain=rehearsal.compath.ee';
		return normalizeLang(decodeURIComponent(match[1]));
	}

	var fromCookie = cookieLang();
	if (fromCookie) save(fromCookie);

	var query = normalizeLang(new URLSearchParams(location.search).get('lang'));
	if (query && cfg.urls && cfg.urls[query] && query !== cfg.lang) {
		save(query);
		location.replace(cfg.urls[query] + location.hash);
		return;
	}
	if (query) save(query);

	if (cfg.autoDetect && cfg.urls) {
		var remembered = stored();
		if (remembered === 'en') {
			/* An explicit English choice stays on the home page. */
		} else if (remembered && cfg.urls[remembered]) {
			location.replace(cfg.urls[remembered] + location.hash);
			return;
		} else if (!remembered) {
			var list = navigator.languages || [navigator.language || ''];
			for (var i = 0; i < list.length; i++) {
				var code = normalizeLang(list[i]);
				if (code && code !== 'en' && cfg.urls[code]) {
					save(code);
					location.replace(cfg.urls[code] + location.hash);
					return;
				}
			}
		}
	}

	var select = document.getElementById('lang');
	if (select) {
		select.addEventListener('change', function () {
			var next = normalizeLang(select.value);
			if (!next || !cfg.urls || !cfg.urls[next]) return;
			save(next);
			if (next !== cfg.lang) location.href = cfg.urls[next];
		});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-copy]') : null;
		if (!button) return;
		var value = button.getAttribute('data-copy') || '';
		var label = button.getAttribute('data-label') || button.textContent;
		var copied = button.getAttribute('data-copied') || label;
		var write = navigator.clipboard && navigator.clipboard.writeText
			? navigator.clipboard.writeText(value)
			: fallbackCopy(value);
		write.then(function () {
			button.textContent = copied;
			var status = document.getElementById('copy-status');
			if (status) status.textContent = copied;
			window.setTimeout(function () {
				if (button.textContent === copied) button.textContent = label;
			}, 2000);
		}).catch(function () {});
	});

	function fallbackCopy(value) {
		return new Promise(function (resolve, reject) {
			var area = document.createElement('textarea');
			area.value = value;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.left = '-9999px';
			document.body.appendChild(area);
			area.select();
			try {
				if (!document.execCommand('copy')) throw new Error('copy failed');
				resolve();
			} catch (err) {
				reject(err);
			} finally {
				document.body.removeChild(area);
			}
		});
	}

	var pages = Array.prototype.slice.call(document.querySelectorAll('.changelog-page'));
	function pageCount() { return Math.max(1, pages.length); }
	function showPage(page, opts) {
		opts = opts || {};
		var count = pageCount();
		if (page < 1) page = 1;
		if (page > count) page = count;
		pages.forEach(function (el) {
			var n = parseInt(el.getAttribute('data-page'), 10);
			if (n === page) el.removeAttribute('hidden');
			else el.setAttribute('hidden', '');
		});
		document.querySelectorAll('.pager [data-pager]').forEach(function (button) {
			var kind = button.getAttribute('data-pager');
			if (kind === 'prev') button.disabled = page <= 1;
			else if (kind === 'next') button.disabled = page >= count;
			else if (parseInt(kind, 10) === page) button.setAttribute('aria-current', 'page');
			else button.removeAttribute('aria-current');
		});
		var status = document.getElementById('changelog-status');
		if (status && cfg.pageLabel) status.textContent = cfg.pageLabel.replace('{n}', String(page));
		if (opts.scroll) {
			var section = document.getElementById('changelog');
			if (section) section.scrollIntoView();
		}
		if (opts.focus) {
			var current = document.querySelector('.pager [aria-current="page"]');
			if (current) current.focus();
		}
		if (opts.hash) {
			var hash = page > 1 ? '#changelog-page-' + page : (location.hash.indexOf('#changelog-') === 0 ? location.hash : '');
			if (hash !== location.hash) history.replaceState(null, '', location.pathname + location.search + hash);
		}
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-pager]') : null;
		if (!button || button.disabled) return;
		var kind = button.getAttribute('data-pager');
		var current = 1;
		pages.forEach(function (el) {
			if (!el.hasAttribute('hidden')) current = parseInt(el.getAttribute('data-page'), 10);
		});
		var next = current;
		if (kind === 'prev') next -= 1;
		else if (kind === 'next') next += 1;
		else next = parseInt(kind, 10);
		showPage(next, { scroll: true, hash: true });
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
		if (!event.target.closest || !event.target.closest('.pager')) return;
		event.preventDefault();
		var current = 1;
		pages.forEach(function (el) {
			if (!el.hasAttribute('hidden')) current = parseInt(el.getAttribute('data-page'), 10);
		});
		showPage(current + (event.key === 'ArrowRight' ? 1 : -1), { focus: true, hash: true });
	});

	function revealHash() {
		var hash = location.hash || '';
		var pageMatch = hash.match(/^#changelog-page-(\d+)$/);
		if (pageMatch) {
			showPage(parseInt(pageMatch[1], 10), { scroll: true });
			return;
		}
		if (/^#changelog-.+/.test(hash)) {
			var entry = document.getElementById('changelog-' + decodeURIComponent(hash.slice('#changelog-'.length)));
			if (!entry) return;
			var page = entry.closest ? entry.closest('.changelog-page') : null;
			if (page) showPage(parseInt(page.getAttribute('data-page'), 10), {});
			if (entry.tagName === 'DETAILS') entry.open = true;
			entry.scrollIntoView();
		}
	}
	revealHash();
	window.addEventListener('hashchange', revealHash);

	var badge = document.querySelector('[data-lite-version]');
	if (badge && cfg.updateUrl) {
		fetch(cfg.updateUrl, { cache: 'no-store' }).then(function (response) {
			return response.ok ? response.json() : null;
		}).then(function (data) {
			if (!data || !data.version) return;
			var current = badge.getAttribute('data-lite-version') || '';
			if (compareVersions(String(data.version), current) > 0) {
				badge.textContent = badge.textContent.replace(current, String(data.version));
				badge.setAttribute('data-lite-version', String(data.version));
			}
		}).catch(function () {});
	}

	function compareVersions(a, b) {
		var pa = String(a || '0').split('.').map(function (n) { return parseInt(n, 10) || 0; });
		var pb = String(b || '0').split('.').map(function (n) { return parseInt(n, 10) || 0; });
		for (var i = 0; i < Math.max(pa.length, pb.length); i++) {
			var na = pa[i] || 0;
			var nb = pb[i] || 0;
			if (na !== nb) return na > nb ? 1 : -1;
		}
		return 0;
	}
})();
