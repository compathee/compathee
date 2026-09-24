#!/usr/bin/env node
/**
 * Build static language pages for rehearsal.compath.ee from product-data.json.
 * Output: choir-rehearsal/docs/dist/
 */
import { readFileSync, writeFileSync, mkdirSync, rmSync, copyFileSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const dist = join(root, 'dist');
const ORIGIN = 'https://rehearsal.compath.ee';
const SHOP = 'https://shop.compath.ee/products/choir-rehearsal-pro/';
const WP = 'https://wordpress.org/plugins/compath-choir-rehearsal/';
const DEMO = 'https://demo.rehearsal.compath.ee/rehearsal';
const UPDATE = 'https://cdn.jsdelivr.net/gh/compathee/compathee@cursor/rehearsal-copy-main-abc2/choir-rehearsal/update.json';
const PAGE_SIZE = 8;
const LANGS = [
  ['en', '/', 'ENG', 'English', 'en_US'],
  ['et', '/et/', 'EST', 'Eesti', 'et_EE'],
  ['ru', '/ru/', 'RUS', 'Русский', 'ru_RU'],
  ['de', '/de/', 'DEU', 'Deutsch', 'de_DE'],
  ['fr', '/fr/', 'FRA', 'Français', 'fr_FR'],
  ['it', '/it/', 'ITA', 'Italiano', 'it_IT'],
  ['es', '/es/', 'ESP', 'Español', 'es_ES'],
  ['sv', '/sv/', 'SWE', 'Svenska', 'sv_SE'],
  ['fi', '/fi/', 'FIN', 'Suomi', 'fi_FI']
];
const CHROME = {
  en: { skip: 'Skip to content', onPage: 'On this page' },
  et: { skip: 'Sisu juurde', onPage: 'Lehel' },
  ru: { skip: 'К содержанию', onPage: 'На странице' },
  de: { skip: 'Zum Inhalt', onPage: 'Auf dieser Seite' },
  fr: { skip: 'Aller au contenu', onPage: 'Sur cette page' },
  it: { skip: 'Vai al contenuto', onPage: 'In questa pagina' },
  es: { skip: 'Saltar al contenido', onPage: 'En esta página' },
  sv: { skip: 'Hoppa till innehållet', onPage: 'På sidan' },
  fi: { skip: 'Siirry sisältöön', onPage: 'Tällä sivulla' }
};
const FORBIDDEN = ['Sänger', 'Sängerin', 'Chorleiter', 'Chanteur', 'Chanteuse', 'Administrateur', 'Cantante', 'Amministratore', 'Ospite', 'Invitado', 'Administrador', 'Sångare', 'Administratör', 'Laulja', 'Ylläpitäjä', 'Vieras', 'Gast', 'Gäst', 'Invité', 'Певец', 'Регент', 'Гость', 'Администратор', 'Külaline'];
const ICONS = {
  library: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 7h8M8 11h8"/></svg>',
  pdf: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>',
  voices: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
  player: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z" fill="currentColor" stroke="none"/></svg>',
  youtube: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="3"/><path d="M10 9l6 3-6 3V9z" fill="currentColor" stroke="none"/></svg>',
  lock: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  users: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  lite: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  pro: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15 8.5 22 9.3 17 14.1 18.2 21 12 17.8 5.8 21 7 14.1 2 9.3 9 8.5 12 2"/></svg>'
};

const data = JSON.parse(readFileSync(join(root, 'product-data.json'), 'utf8'));
const seo = JSON.parse(readFileSync(join(root, 'seo-copy.json'), 'utf8'));
const css = readFileSync(join(root, 'site.css'), 'utf8');
const client = readFileSync(join(root, 'site.js'), 'utf8');
const lastmod = statSync(join(root, 'product-data.json')).mtime.toISOString().slice(0, 10);

function abs(path) {
  return path === '/' ? ORIGIN + '/' : ORIGIN + path;
}
function esc(text) {
  return String(text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function linkify(text) {
  return esc(text)
    .replace(/https:\/\/shop\.compath\.ee\/products\/choir-rehearsal-pro\//g, `<a href="${SHOP}">${SHOP}</a>`)
    .replace(/order@compath\.ee/g, '<a href="mailto:order@compath.ee">order@compath.ee</a>')
    .replace(/\+372 55520482/g, '<a href="tel:+37255520482">+372 55520482</a>')
    .replace(/WordPress\.org/g, `<a href="${WP}">WordPress.org</a>`)
    .replace(/demo\.rehearsal\.compath\.ee/g, '<a href="https://demo.rehearsal.compath.ee/rehearsal">demo.rehearsal.compath.ee</a>');
}
function badge(pack) {
  return String(pack.badge || '').replace('{lite}', data.liteVersion).replace('{pro}', data.proVersion);
}
function faqsFor(code, pack) {
  return [...(seo[code].faq || []), ...(pack.faq || [])];
}

function jsonLd(code, pack, faqs) {
  const page = abs(LANGS.find((row) => row[0] === code)[1]);
  const features = (pack.featureCards || []).map((card) => card.title).filter(Boolean);
  const graph = {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'Organization',
        '@id': 'https://compath.ee/#organization',
        name: 'Compath OÜ',
        url: 'https://compath.ee',
        telephone: '+372 55520482',
        email: 'order@compath.ee',
        address: {
          '@type': 'PostalAddress',
          streetAddress: 'Ahtri 12',
          addressLocality: 'Tallinn',
          addressCountry: 'EE'
        },
        logo: {
          '@type': 'ImageObject',
          url: ORIGIN + '/favicon-512x512.png',
          width: 512,
          height: 512
        },
        sameAs: [WP, SHOP, 'https://compath.ee', 'https://github.com/compathee/compathee']
      },
      {
        '@type': 'WebSite',
        '@id': ORIGIN + '/#website',
        name: 'Choir Rehearsal',
        url: ORIGIN + '/',
        inLanguage: LANGS.map((row) => row[0]),
        publisher: { '@id': 'https://compath.ee/#organization' }
      },
      {
        '@type': 'SoftwareApplication',
        '@id': ORIGIN + '/#app',
        name: 'Compath Choir Rehearsal',
        applicationCategory: 'MultimediaApplication',
        operatingSystem: 'WordPress',
        softwareVersion: String(data.proVersion),
        description: seo[code].whatIs,
        downloadUrl: WP,
        installUrl: WP,
        screenshot: {
          '@type': 'ImageObject',
          url: ORIGIN + '/og-image.png',
          width: 1200,
          height: 630
        },
        featureList: features,
        softwareRequirements: pack.requirements,
        offers: [
          {
            '@type': 'Offer',
            name: 'Lite',
            price: '0',
            priceCurrency: 'EUR',
            url: WP,
            availability: 'https://schema.org/InStock',
            description: 'Free Lite plugin on WordPress.org'
          },
          {
            '@type': 'Offer',
            name: 'Pro',
            price: '49',
            priceCurrency: 'EUR',
            url: SHOP,
            availability: 'https://schema.org/InStock',
            description: '49 EUR per year for one choir website'
          }
        ],
        provider: { '@id': 'https://compath.ee/#organization' }
      },
      {
        '@type': 'FAQPage',
        '@id': page + '#faq',
        inLanguage: code,
        url: page + '#faq',
        mainEntity: faqs.map((item) => ({
          '@type': 'Question',
          name: item.q,
          acceptedAnswer: { '@type': 'Answer', text: item.a }
        }))
      }
    ]
  };
  return JSON.stringify(graph);
}

function renderPage(code) {
  const row = LANGS.find((item) => item[0] === code);
  const pack = data.locales[code];
  const extra = seo[code];
  const chrome = CHROME[code];
  const faqs = faqsFor(code, pack);
  const pagePath = row[1];
  const pageUrl = abs(pagePath);
  const entries = data.changelog || [];
  const pageCount = Math.max(1, Math.ceil(entries.length / PAGE_SIZE));
  const urls = Object.fromEntries(LANGS.map((item) => [item[0], item[1]]));
  const customer = data.firstCustomer || { name: 'Cappella Veneta', url: 'https://veneta.ee' };
  const hreflang = LANGS.map((item) => `<link rel="alternate" hreflang="${item[0]}" href="${abs(item[1])}" />`).join('\n\t')
    + `\n\t<link rel="alternate" hreflang="x-default" href="${ORIGIN}/" />`;
  const ogAlt = LANGS.filter((item) => item[0] !== code).map((item) => `<meta property="og:locale:alternate" content="${item[4]}" />`).join('\n\t');
  const options = LANGS.map((item) => `<option value="${item[0]}" lang="${item[0]}"${item[0] === code ? ' selected' : ''} aria-label="${esc(item[3])}">${item[2]}</option>`).join('\n\t\t\t');
  const footerLangs = LANGS.map((item) => `<a href="${item[1]}" hreflang="${item[0]}" lang="${item[0]}"${item[0] === code ? ' aria-current="true"' : ''}>${esc(item[3])}</a>`).join('\n\t\t\t');
  const features = (pack.featureCards || []).map((card) => `<li><span class="features__icon">${ICONS[card.icon] || ICONS.library}</span><h3 class="features__title">${esc(card.title)}</h3><p class="features__text">${esc(card.text)}</p></li>`).join('');
  const plans = (pack.pricing || []).map((plan) => {
    const items = (plan.items || []).map((item) => `<li>${esc(item)}</li>`).join('');
    const cta = plan.ctaUrl ? `<a class="plan__cta" href="${esc(plan.ctaUrl)}">${esc(plan.ctaLabel || pack.orderPro)}</a>` : '';
    return `<div class="${plan.featured ? 'plan plan--featured' : 'plan'}"><div class="plan__name">${esc(plan.name)}</div><div class="plan__price">${esc(plan.price)}</div><ul>${items}</ul>${cta}</div>`;
  }).join('');
  const accounts = [
    ['Singer', 'demosinger', 'DMo5ingERzz26', pack.demoSingerText],
    ['Voice Leader', 'demoleader', 'DMoLiidERzz26', pack.demoLeaderText]
  ].map(([role, user, pass, blurb]) => `<article class="demo-account"><h3>${role}</h3><p class="meta">${esc(blurb)}</p><div class="copy-row"><span class="copy-label">${esc(pack.demoUser)}</span><code>${user}</code><button type="button" class="copy-btn" data-copy="${user}" data-label="${esc(pack.demoCopy)}" data-copied="${esc(pack.demoCopied)}">${esc(pack.demoCopy)}</button></div><div class="copy-row"><span class="copy-label">${esc(pack.demoPassword)}</span><code>${pass}</code><button type="button" class="copy-btn" data-copy="${pass}" data-label="${esc(pack.demoCopy)}" data-copied="${esc(pack.demoCopied)}">${esc(pack.demoCopy)}</button></div></article>`).join('');
  const changelog = Array.from({ length: pageCount }, (_, index) => {
    const slice = entries.slice(index * PAGE_SIZE, (index + 1) * PAGE_SIZE);
    const body = slice.map((entry) => `<details id="changelog-${esc(entry.version)}"${entry.open ? ' open' : ''}><summary>${esc(entry.version)} — ${esc(entry.summary)}</summary><ul>${(entry.items || []).map((item) => `<li>${esc(item)}</li>`).join('')}</ul></details>`).join('');
    return `<div class="changelog-page" data-page="${index + 1}" id="changelog-page-${index + 1}"${index === 0 ? '' : ' hidden'}>${body}</div>`;
  }).join('');
  const pagerButtons = [`<button type="button" data-pager="prev" disabled>${esc(pack.ui.prev)}</button>`];
  for (let n = 1; n <= pageCount; n += 1) {
    pagerButtons.push(`<button type="button" data-pager="${n}"${n === 1 ? ' aria-current="page"' : ''} aria-label="${esc(pack.ui.page.replace('{n}', String(n)))}">${n}</button>`);
  }
  pagerButtons.push(`<button type="button" data-pager="next"${pageCount <= 1 ? ' disabled' : ''}>${esc(pack.ui.next)}</button>`);
  const cfg = {
    lang: code,
    autoDetect: code === 'en',
    urls,
    pageLabel: pack.ui.page,
    updateUrl: UPDATE
  };
  return `<!DOCTYPE html>
<html lang="${code}">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>${esc(extra.seoTitle)}</title>
	<meta name="description" content="${esc(extra.seoDescription)}" />
	<meta name="robots" content="index, follow" />
	<meta name="theme-color" content="#1f4fd8" />
	<link rel="canonical" href="${pageUrl}" />
	${hreflang}
	<meta property="og:type" content="website" />
	<meta property="og:site_name" content="Choir Rehearsal" />
	<meta property="og:title" content="${esc(extra.seoTitle)}" />
	<meta property="og:description" content="${esc(extra.seoDescription)}" />
	<meta property="og:url" content="${pageUrl}" />
	<meta property="og:image" content="${ORIGIN}/og-image.png" />
	<meta property="og:image:width" content="1200" />
	<meta property="og:image:height" content="630" />
	<meta property="og:image:type" content="image/png" />
	<meta property="og:image:alt" content="${esc(extra.imageAlt)}" />
	<meta property="og:locale" content="${row[4]}" />
	${ogAlt}
	<meta name="twitter:card" content="summary_large_image" />
	<meta name="twitter:title" content="${esc(extra.seoTitle)}" />
	<meta name="twitter:description" content="${esc(extra.seoDescription)}" />
	<meta name="twitter:image" content="${ORIGIN}/og-image.png" />
	<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />
	<link rel="icon" href="/favicon.ico" sizes="any" />
	<link rel="icon" href="/favicon.svg" type="image/svg+xml" />
	<link rel="icon" href="/favicon-192x192.png" type="image/png" sizes="192x192" />
	<link rel="icon" href="/favicon-512x512.png" type="image/png" sizes="512x512" />
	<link rel="apple-touch-icon" href="/apple-touch-icon.png" />
	<link rel="manifest" href="/site.webmanifest" />
	<script type="application/ld+json">${jsonLd(code, pack, faqs)}</script>
	<style>${css}</style>
</head>
<body>
	<a class="skip" href="#what">${esc(chrome.skip)}</a>
	<div class="langbar">
		<div class="langbar__inner">
		<a class="langbar__brand" href="https://compath.ee/"><img src="/compath-logo.png" width="118" height="32" alt="Compath" title="Compath OÜ" /></a>
		<label class="visually-hidden" for="lang">${esc(extra.switchLabel)}</label>
		<select id="lang" name="lang">
			${options}
		</select>
		</div>
	</div>
	<header class="hero"><div class="hero__inner">
		<span class="hero__badge" data-lite-version="${esc(data.liteVersion)}">${esc(badge(pack))}</span>
		<h1>${esc(pack.title)}</h1>
		<p>${esc(pack.tagline)}</p>
		<div class="hero__cta">
			<a class="btn btn--primary" href="${WP}">${esc(pack.getLite)}</a>
			<a class="btn btn--primary" href="${DEMO}">${esc(pack.tryDemo)}</a>
			<a class="btn btn--ghost" href="${SHOP}">${esc(pack.orderPro)}</a>
		</div>
		<nav class="hero__nav" aria-label="${esc(chrome.onPage)}">
			<a href="#what">${esc(extra.whatIsTitle)}</a>
			<a href="#about">${esc(pack.overviewTitle)}</a>
			<a href="#demo">${esc(pack.demoTitle)}</a>
			<a href="#order">${esc(pack.orderTitle)}</a>
			<a href="#install">${esc(pack.installTitle)}</a>
			<a href="#faq">${esc(pack.faqTitle)}</a>
			<a href="#changelog">${esc(pack.changelogTitle)}</a>
		</nav>
	</div></header>
	<main class="page">
		<section class="card" id="what"><h2>${esc(extra.whatIsTitle)}</h2><p class="answer">${esc(extra.whatIs)}</p></section>
		<section class="card" id="about"><h2>${esc(pack.overviewTitle)}</h2><p>${linkify(pack.overview)}</p><ul class="features">${features}</ul><p class="meta">${esc(pack.requirementsLabel)}: ${esc(pack.requirements)}. ${esc(pack.ownerLabel)}: <strong>${esc(data.owner)}</strong>. ${esc(pack.firstCustomerLabel)} — <a href="${esc(customer.url)}">${esc(customer.name)}</a>.</p></section>
		<section class="card" id="demo"><h2>${esc(pack.demoTitle)}</h2><p>${esc(pack.demoIntro)}</p><p><a class="btn btn--accent" href="${DEMO}">${esc(pack.tryDemo)}</a></p><div class="demo-grid">${accounts}</div><p class="meta">${esc(pack.demoShared)}</p><p id="copy-status" class="visually-hidden" aria-live="polite"></p></section>
		<section class="card" id="order"><h2>${esc(pack.orderTitle)}</h2><p>${linkify(pack.orderIntro)}</p><div class="pricing">${plans}</div><div class="order-cta"><a class="btn btn--accent" href="${SHOP}">${esc(pack.orderCta)}</a><span class="meta">${esc(pack.orderCtaMeta)}</span></div><h3>${esc(pack.howToOrder)}</h3><ol class="steps">${(pack.orderSteps || []).map((step) => `<li>${linkify(step)}</li>`).join('')}</ol><p class="meta">${esc(pack.discountNote)}</p></section>
		<section class="card" id="install"><h2>${esc(pack.installTitle)}</h2><h3>${esc(pack.selfService)}</h3><ol class="steps">${(pack.installSteps || []).map((step) => `<li>${linkify(step)}</li>`).join('')}</ol><h3>${esc(pack.updatesTitle)}</h3><ul>${(pack.updates || []).map((item) => item.label ? `<li><strong>${esc(item.label)}:</strong> ${linkify(item.text)}</li>` : `<li>${linkify(item.text)}</li>`).join('')}</ul><h3>${esc(pack.rolesTitle)}</h3><ul>${(pack.roles || []).map((role) => `<li><strong>${esc(role.name)}</strong> — ${esc(role.text)}</li>`).join('')}</ul></section>
		<section class="card" id="faq"><h2>${esc(pack.faqTitle)}</h2><div class="faq">${faqs.map((item) => `<details><summary>${esc(item.q)}</summary><p>${linkify(item.a)}</p></details>`).join('')}</div></section>
		<section class="card changelog" id="changelog"><h2>${esc(pack.changelogTitle)}</h2><p class="meta">${esc(pack.changelogMeta)}</p>${changelog}<nav class="pager" aria-label="${esc(pack.ui.pagination)}">${pagerButtons.join('')}</nav><p id="changelog-status" class="visually-hidden" aria-live="polite">${esc(pack.ui.page.replace('{n}', '1'))}</p></section>
	</main>
	<footer class="footer">
		<nav aria-label="${esc(extra.languagesLabel)}">${footerLangs}</nav>
		<p>Choir Rehearsal · <a href="${ORIGIN}/">rehearsal.compath.ee</a> · <a href="https://compath.ee">compath.ee</a> · <a href="https://github.com/compathee/compathee">GitHub</a> · <a href="mailto:order@compath.ee">order@compath.ee</a></p>
		<p>© Compath OÜ, Ahtri 12, Tallinn</p><p>${esc(pack.licenseLine || '')}</p>
	</footer>
	<script>window.REHEARSAL_PAGE = ${JSON.stringify(cfg)};</script>
	<script>${client}</script>
</body>
</html>
`;
}

function robots() {
  const bots = ['*', 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'PerplexityBot', 'Google-Extended', 'Bingbot', 'Applebot-Extended'];
  return bots.map((bot) => `User-agent: ${bot}\nAllow: /\nDisallow: /api/\n`).join('\n') + `\nSitemap: ${ORIGIN}/sitemap.xml\n`;
}

function sitemap() {
  const links = LANGS.map((item) => `    <xhtml:link rel="alternate" hreflang="${item[0]}" href="${abs(item[1])}" />`).join('\n')
    + `\n    <xhtml:link rel="alternate" hreflang="x-default" href="${ORIGIN}/" />`;
  const urls = LANGS.map((item) => `  <url>\n    <loc>${abs(item[1])}</loc>\n    <lastmod>${lastmod}</lastmod>\n${links}\n  </url>`).join('\n');
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${urls}\n</urlset>\n`;
}

function llms() {
  const en = data.locales.en;
  const extra = seo.en;
  const lines = [];
  lines.push('# Choir Rehearsal');
  lines.push('');
  lines.push(`> ${extra.seoDescription}`);
  lines.push('');
  lines.push(extra.whatIs);
  lines.push('');
  lines.push('## Who it is for');
  lines.push('');
  lines.push('Choir singers and the people who run rehearsals. Inside WordPress the role names stay English: Singer, Voice Leader, Administrator, and Guest.');
  lines.push('');
  lines.push('## Lite and Pro');
  lines.push('');
  for (const plan of en.pricing) {
    lines.push(`- ${plan.name}: ${plan.price}. ${plan.items.join(' ')}`);
  }
  lines.push('');
  lines.push('## Install');
  lines.push('');
  en.installSteps.forEach((step, index) => lines.push(`${index + 1}. ${step}`));
  lines.push('');
  lines.push('## Demo');
  lines.push('');
  lines.push(`Shared demo: ${DEMO}`);
  lines.push('Singer login: demosinger / DMo5ingERzz26');
  lines.push('Voice Leader login: demoleader / DMoLiidERzz26');
  lines.push(en.demoShared);
  lines.push('');
  lines.push('## Support');
  lines.push('');
  lines.push('Compath OÜ, Ahtri 12, Tallinn, Estonia');
  lines.push('order@compath.ee');
  lines.push('+372 55520482');
  lines.push('');
  lines.push('## Languages');
  lines.push('');
  for (const item of LANGS) lines.push(`- ${item[3]}: ${abs(item[1])}`);
  lines.push('');
  lines.push('## Key URLs');
  lines.push('');
  lines.push(`- Product site: ${ORIGIN}/`);
  lines.push(`- Lite on WordPress.org: ${WP}`);
  lines.push(`- Pro shop: ${SHOP}`);
  lines.push('- Company: https://compath.ee');
  lines.push('- Source: https://github.com/compathee/compathee');
  lines.push(`- Sitemap: ${ORIGIN}/sitemap.xml`);
  lines.push('');
  return lines.join('\n');
}

function llmsFull() {
  const en = data.locales.en;
  const lines = [llms().trimEnd(), '', '## FAQ', ''];
  for (const item of faqsFor('en', en)) {
    lines.push(`### ${item.q}`);
    lines.push('');
    lines.push(item.a);
    lines.push('');
  }
  lines.push('## Updates');
  lines.push('');
  for (const item of en.updates || []) {
    lines.push(item.label ? `- ${item.label}: ${item.text}` : `- ${item.text}`);
  }
  lines.push('');
  lines.push(`Lite ${data.liteVersion} updates from WordPress.org. Pro ${data.proVersion} updates automatically while the license is active. An older Pro plugin needs one manual replacement.`);
  lines.push('');
  return lines.join('\n');
}

function validate(html, code) {
  const errors = [];
  const extra = seo[code];
  if ([...html.matchAll(/<h1[\s>]/g)].length !== 1) errors.push('h1 count');
  if (!html.includes('href="https://compath.ee/"') || !html.includes('alt="Compath"') || !html.includes('title="Compath OÜ"') || !html.includes('/compath-logo.png')) errors.push('logo');
  if (!html.includes(extra.whatIsTitle) || !html.includes(extra.whatIs)) errors.push('missing what-is');
  if (extra.seoTitle.length > 60) errors.push(`title ${extra.seoTitle.length}`);
  if (extra.seoDescription.length < 140 || extra.seoDescription.length > 165) errors.push(`description ${extra.seoDescription.length}`);
  if (!html.includes(`<link rel="canonical" href="${abs(LANGS.find((row) => row[0] === code)[1])}"`)) errors.push('canonical');
  if ([...html.matchAll(/hreflang="/g)].length < 10) errors.push('hreflang');
  const ld = html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/);
  if (!ld) errors.push('json-ld missing');
  else {
    const graph = JSON.parse(ld[1]);
    const types = graph['@graph'].map((node) => node['@type']);
    for (const type of ['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage']) {
      if (!types.includes(type)) errors.push('missing ' + type);
    }
    if (/aggregateRating|Review/.test(ld[1])) errors.push('unexpected rating');
    const app = graph['@graph'].find((node) => node['@type'] === 'SoftwareApplication');
    if (app.operatingSystem !== 'WordPress' || app.downloadUrl !== WP) errors.push('app fields');
    if (!app.offers.some((offer) => offer.price === '0') || !app.offers.some((offer) => offer.price === '49' && offer.url === SHOP)) errors.push('offers');
    const faq = graph['@graph'].find((node) => node['@type'] === 'FAQPage');
    const visible = [...html.matchAll(/<summary>(.*?)<\/summary>/g)].map((match) => match[1]);
    for (const question of faq.mainEntity) {
      if (!visible.includes(question.name.replace(/&/g, '&amp;').replace(/</g, '&lt;'))) {
        if (!html.includes(esc(question.name))) errors.push('faq not visible: ' + question.name);
      }
    }
  }
  for (const word of FORBIDDEN) {
    if (html.includes(word)) errors.push('forbidden ' + word);
  }
  for (const role of ['Singer', 'Voice Leader', 'Administrator', 'Guest']) {
    if (!html.includes(role)) errors.push('missing role ' + role);
  }
  if (errors.length) throw new Error(code + ': ' + errors.join(', '));
}

rmSync(dist, { recursive: true, force: true });
mkdirSync(dist, { recursive: true });
const manifest = [];
for (const [code, path] of LANGS) {
  if (!data.locales[code] || !seo[code]) throw new Error('missing locale ' + code);
  const html = renderPage(code);
  validate(html, code);
  const dir = path === '/' ? dist : join(dist, code);
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'index.html'), html);
  manifest.push(path === '/' ? '/index.html' : `${path}index.html`);
  console.log(code, 'title', seo[code].seoTitle.length, 'description', seo[code].seoDescription.length, 'bytes', Buffer.byteLength(html));
}
writeFileSync(join(dist, 'robots.txt'), robots());
writeFileSync(join(dist, 'sitemap.xml'), sitemap());
writeFileSync(join(dist, 'llms.txt'), llms());
writeFileSync(join(dist, 'llms-full.txt'), llmsFull());
for (const name of ['.htaccess', 'health.txt', 'favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'favicon-192x192.png', 'favicon-512x512.png', 'site.webmanifest', 'og-image.png', 'compath-logo.png']) {
  copyFileSync(join(root, 'deploy', name), join(dist, name));
}
const robotsText = readFileSync(join(dist, 'robots.txt'), 'utf8');
for (const token of ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'PerplexityBot', 'Google-Extended', 'Bingbot', 'Applebot-Extended', 'Disallow: /api/', 'Sitemap:']) {
  if (!robotsText.includes(token)) throw new Error('robots missing ' + token);
}
const map = readFileSync(join(dist, 'sitemap.xml'), 'utf8');
if ([...map.matchAll(/<loc>/g)].length !== 9) throw new Error('sitemap locs');
if (!map.includes('xhtml:link') || !map.includes('x-default') || !map.includes(`<lastmod>${lastmod}</lastmod>`)) throw new Error('sitemap alternates');
console.log('built', manifest.join(' '));
console.log('lastmod', lastmod);
