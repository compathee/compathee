import fs from "node:fs";
import path from "node:path";
import { validate } from "./check.mjs";

const root = import.meta.dirname;
const preview = process.argv.includes("--preview");
const basePath = preview ? "/preview-2026" : "";
const dist = path.join(root, preview ? "dist-preview" : "dist");
const origin = "https://compath.ee";
const updated = "2026-09-24";
const langs = ["en", "et", "ru"].map((code) =>
  JSON.parse(fs.readFileSync(path.join(root, "content", `${code}.json`), "utf8"))
);
const byCode = Object.fromEntries(langs.map((lang) => [lang.code, lang]));
const redirects = JSON.parse(fs.readFileSync(path.join(root, "redirects.json"), "utf8"));
const logoMeta = JSON.parse(fs.readFileSync(path.join(root, "assets", "brand", "logo-meta.json"), "utf8"));
const product = {
  bare: "https://rehearsal.compath.ee/",
  localized: {
    en: "https://rehearsal.compath.ee/?lang=en",
    et: "https://rehearsal.compath.ee/?lang=et",
    ru: "https://rehearsal.compath.ee/?lang=ru",
  },
  demo: "https://demo.rehearsal.compath.ee/rehearsal",
  wporg: "https://wordpress.org/plugins/compath-choir-rehearsal/",
  shop: "https://shop.compath.ee/products/choir-rehearsal-pro/",
};
const mapUrl = "https://www.openstreetmap.org/search?query=Ahtri%2012%2C%2010151%20Tallinn";
const pageIds = ["home", "services", "it", "plugins", "amazon", "choir", "contact", "privacy", "thanks", "formError"];
const sitemapIds = ["home", "services", "it", "plugins", "amazon", "choir", "contact", "privacy"];
const noindexIds = new Set(["thanks", "formError"]);

function esc(value) {
  return String(value).replace(/[&<>"]/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
  }[char]));
}

function inline(src) {
  const re = /\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)|\*\*([^*]+)\*\*/g;
  let html = "";
  let index = 0;
  for (const match of String(src).matchAll(re)) {
    html += esc(src.slice(index, match.index));
    if (match[1]) {
      const href = match[2].startsWith("/") ? pub(match[2]) : match[2];
      const external = href.startsWith("http") && !href.startsWith(origin);
      html += `<a href="${esc(href)}"${external ? ' rel="noopener noreferrer"' : ""}>${esc(match[1])}</a>`;
    } else {
      html += `<strong>${esc(match[3])}</strong>`;
    }
    index = match.index + match[0].length;
  }
  html += esc(src.slice(index));
  return html;
}

function urlFor(lang, id) {
  if (id === "home") return lang.prefix ? `${lang.prefix}/` : "/";
  const slug = lang.slugs[id];
  if (!slug) throw new Error(`Missing slug ${lang.code}:${id}`);
  return `${lang.prefix}/${slug}/`;
}

function abs(urlPath) {
  return origin + urlPath;
}

function pub(sitePath) {
  if (!basePath || !sitePath.startsWith("/")) return sitePath;
  return sitePath === "/" ? `${basePath}/` : `${basePath}${sitePath}`;
}

function serviceById(lang, id) {
  return lang.services.find((item) => item.id === id);
}

function hreflang(id) {
  const links = langs.map((lang) => {
    const href = abs(urlFor(lang, id));
    return `<link rel="alternate" hreflang="${lang.code}" href="${href}" />`;
  });
  links.push(`<link rel="alternate" hreflang="x-default" href="${abs(urlFor(byCode.et, id))}" />`);
  return links.join("\n");
}

function organization() {
  return {
    "@type": ["Organization", "LocalBusiness"],
    "@id": `${origin}/#organization`,
    name: "Compath OÜ",
    legalName: "Compath OÜ",
    url: `${origin}/`,
    image: `${origin}/assets/og-en.webp`,
    logo: `${origin}/assets/logo.png`,
    telephone: "+37255520482",
    vatID: "EE101244231",
    identifier: {
      "@type": "PropertyValue",
      propertyID: "Estonian registry code",
      value: "11502963",
    },
    address: {
      "@type": "PostalAddress",
      streetAddress: "Ahtri 12",
      addressLocality: "Tallinn",
      postalCode: "10151",
      addressCountry: "EE",
    },
    geo: {
      "@type": "GeoCoordinates",
      latitude: 59.4391458,
      longitude: 24.7633128,
    },
    hasMap: mapUrl,
    openingHours: "Mo-Fr 09:00-17:00",
    openingHoursSpecification: [{
      "@type": "OpeningHoursSpecification",
      dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
      opens: "09:00",
      closes: "17:00",
    }],
    areaServed: { "@type": "City", name: "Tallinn" },
    sameAs: [product.bare, "https://shop.compath.ee/", product.wporg],
  };
}

function software(lang) {
  const copy = lang.product;
  return {
    "@type": "SoftwareApplication",
    "@id": `${abs(urlFor(lang, "choir"))}#software`,
    name: "Choir Rehearsal",
    applicationCategory: "BusinessApplication",
    operatingSystem: "WordPress",
    description: copy.description,
    inLanguage: lang.code,
    url: product.localized[lang.code],
    downloadUrl: product.wporg,
    installUrl: product.wporg,
    featureList: copy.features.map((item) => item.title),
    publisher: { "@id": `${origin}/#organization` },
    sameAs: [product.bare, product.wporg, product.shop, product.demo],
    offers: [
      {
        "@type": "Offer",
        name: "Lite",
        price: "0",
        priceCurrency: "EUR",
        url: product.wporg,
        availability: "https://schema.org/InStock",
      },
      {
        "@type": "Offer",
        name: "Pro",
        price: "49",
        priceCurrency: "EUR",
        url: product.shop,
        availability: "https://schema.org/InStock",
        priceSpecification: {
          "@type": "UnitPriceSpecification",
          price: "49",
          priceCurrency: "EUR",
          unitText: "YEAR",
          billingDuration: "P1Y",
        },
      },
    ],
  };
}

function serviceNode(lang, service) {
  return {
    "@type": "Service",
    "@id": `${abs(urlFor(lang, service.id))}#service`,
    name: service.h1,
    serviceType: service.serviceType,
    description: service.description,
    url: abs(urlFor(lang, service.id)),
    provider: { "@id": `${origin}/#organization` },
    areaServed: { "@type": "City", name: "Tallinn" },
  };
}

function breadcrumb(items) {
  return {
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

function ld(graph) {
  const json = JSON.stringify({ "@context": "https://schema.org", "@graph": graph }).replace(/</g, "\\u003c");
  return `<script type="application/ld+json">${json}</script>`;
}

function layout(lang, id, main, graph) {
  const page = id === "choir" ? lang.product : lang.pages[id] || serviceById(lang, id);
  const title = page.title;
  const description = page.description;
  const canonical = abs(urlFor(lang, id));
  const alternates = Object.fromEntries(langs.map((item) => [item.code, pub(urlFor(item, id))]));
  const og = `${origin}${pub(`/assets/og-${lang.code}.webp`)}`;
  const robots = basePath ? "noindex, nofollow" : (noindexIds.has(id) ? "noindex, follow" : "index, follow");
  return `<!DOCTYPE html>
<html lang="${lang.htmlLang}" data-lang="${lang.code}"${basePath ? ` data-base="${basePath}"` : ""}>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>${esc(title)}</title>
<meta name="description" content="${esc(description)}" />
<link rel="canonical" href="${canonical}" />
${hreflang(id)}
<meta name="robots" content="${robots}" />
<meta name="theme-color" content="#e85d04" />
<meta property="og:type" content="website" />
<meta property="og:site_name" content="Compath OÜ" />
<meta property="og:locale" content="${lang.ogLocale}" />
${langs.filter((item) => item.code !== lang.code).map((item) => `<meta property="og:locale:alternate" content="${item.ogLocale}" />`).join("\n")}
<meta property="og:title" content="${esc(title)}" />
<meta property="og:description" content="${esc(description)}" />
<meta property="og:url" content="${canonical}" />
<meta property="og:image" content="${og}" />
<meta property="og:image:type" content="image/webp" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta property="og:image:alt" content="${esc(title)}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="${esc(title)}" />
<meta name="twitter:description" content="${esc(description)}" />
<meta name="twitter:image" content="${og}" />
<meta name="twitter:image:alt" content="${esc(title)}" />
<link rel="icon" href="${pub("/favicon.svg")}" type="image/svg+xml" />
<link rel="icon" href="${pub("/favicon.ico")}" sizes="any" />
<link rel="icon" href="${pub("/favicon-32.png")}" type="image/png" sizes="32x32" />
<link rel="icon" href="${pub("/favicon-192.png")}" type="image/png" sizes="192x192" />
<link rel="apple-touch-icon" href="${pub("/apple-touch-icon.png")}" />
<link rel="manifest" href="${pub("/site.webmanifest")}" />
<link rel="stylesheet" href="${pub("/assets/site.css")}" />
${ld(graph)}
</head>
<body>
<a class="skip" href="#main">${esc(lang.ui.skip)}</a>
<div class="accent"></div>
<header class="site-header">${basePath ? `\n  <p class="preview-badge">${esc(lang.ui.previewBadge)}</p>` : ""}
  <div class="wrap header__bar">
    <a class="brand" href="${pub(urlFor(lang, "home"))}"><img class="brand__logo" src="${pub("/assets/logo.png")}" alt="Compath" width="${logoMeta.width}" height="${logoMeta.height}" /></a>
    <nav class="nav" aria-label="${esc(lang.ui.navLabel)}">
      ${navLink(lang, id, "home")}
      ${navLink(lang, id, "services")}
      ${navLink(lang, id, "contact")}
    </nav>
    <div class="lang">
      <label for="lang">${esc(lang.ui.langLabel)}</label>
      <select id="lang">
        ${langs.map((item) => `<option value="${item.code}" lang="${item.htmlLang}"${item.code === lang.code ? " selected" : ""}>${item.label}</option>`).join("")}
      </select>
    </div>
  </div>
</header>
<main id="main">
${main}
</main>
${footer(lang)}
<script type="application/json" id="lang-map">${JSON.stringify(alternates)}</script>
<script src="${pub("/assets/site.js")}" defer></script>
</body>
</html>
`;
}

function navLink(lang, current, id) {
  const active = id === "services"
    ? ["services", "it", "plugins", "amazon"].includes(current)
    : current === id;
  return `<a href="${pub(urlFor(lang, id))}"${active ? ' aria-current="page"' : ""}>${esc(lang.ui[id])}</a>`;
}

function footer(lang) {
  const links = [
    ["services", lang.ui.services],
    ...lang.services.map((item) => [item.id, item.name]),
    ["choir", "Choir Rehearsal"],
    ["contact", lang.ui.contact],
    ["privacy", lang.ui.privacy],
  ];
  return `<footer class="site-footer">
  <div class="wrap footer__grid">
    <div>
      <strong>Compath OÜ</strong>
      <address>
        Ahtri 12<br />
        Tallinn 10151<br />
        Estonia
      </address>
      <p><a href="${mapUrl}" rel="noopener noreferrer">${esc(lang.ui.map)}</a></p>
    </div>
    <nav class="footer-nav" aria-label="${esc(lang.ui.footerNav)}">
      ${links.map(([id, name]) => `<a href="${pub(urlFor(lang, id))}">${esc(name)}</a>`).join("")}
    </nav>
    <div>
      <p><span class="meta">${esc(lang.ui.phoneLabel)}</span><br /><a href="tel:+37255520482">+372 55520482</a></p>
      <p><span class="meta">${esc(lang.ui.hoursLabel)}</span><br />${esc(lang.ui.hours)}</p>
      <p><span class="meta">${esc(lang.ui.regLabel)}</span><br />11502963<br />${esc(lang.ui.vatLabel)} EE101244231</p>
    </div>
  </div>
  <div class="wrap legal">${esc(lang.ui.rights)}</div>
</footer>`;
}

function crumbs(lang, items) {
  const html = `<nav aria-label="Breadcrumb"><ol class="crumbs">${items.map((item, index) => {
    const last = index === items.length - 1;
    return `<li>${last ? esc(item.name) : `<a href="${pub(item.path)}">${esc(item.name)}</a>`}</li>`;
  }).join("")}</ol></nav>`;
  const data = breadcrumb(items.map((item) => ({ name: item.name, url: abs(item.path) })));
  return { html, data };
}

function actions(links) {
  return `<div class="actions">${links.map((link) => `<a class="btn ${link.primary ? "btn--primary" : "btn--ghost"}" href="${esc(link.href)}"${link.external ? ' rel="noopener noreferrer"' : ""}>${esc(link.label)}</a>`).join("")}</div>`;
}

function productLinks(lang, includePage) {
  const links = [
    { href: product.localized[lang.code], label: lang.product.ctaProduct, primary: true, external: true },
    { href: product.bare, label: "rehearsal.compath.ee", external: true },
    { href: product.demo, label: lang.product.ctaDemo, external: true },
    { href: product.wporg, label: lang.product.ctaLite, external: true },
    { href: product.shop, label: lang.product.ctaPro, primary: true, external: true },
  ];
  if (includePage) links.splice(1, 0, { href: pub(urlFor(lang, "choir")), label: lang.product.ctaPage });
  return actions(links);
}

function figure(lang, eager) {
  return `<figure class="frame">
  <img src="${pub("/assets/choir-rehearsal.webp")}" width="1200" height="800" alt="${esc(lang.product.imageAlt)}"${eager ? ' decoding="async"' : ' loading="lazy" decoding="async"'} />
  <figcaption>${esc(lang.product.imageCaption)}</figcaption>
</figure>`;
}

function home(lang) {
  const cards = lang.services.map((service, index) => `<li><a href="${pub(urlFor(lang, service.id))}"><span class="num">${String(index + 1).padStart(2, "0")}</span><h3>${esc(service.name)}</h3><p>${esc(service.summary)}</p></a></li>`).join("");
  const why = lang.pages.home.why.map((item) => `<article><h3>${esc(item.title)}</h3><p>${esc(item.text)}</p></article>`).join("");
  const features = lang.product.features.map((item) => `<article class="feature"><h3>${esc(item.title)}</h3><p>${esc(item.text)}</p></article>`).join("");
  const main = `<section class="hero"><div class="wrap">
    <p class="eyebrow">${esc(lang.pages.home.eyebrow)}</p>
    <h1>${esc(lang.pages.home.h1)}</h1>
    <p class="lead">${esc(lang.pages.home.lead)}</p>
    ${actions([
      { href: pub(urlFor(lang, "services")), label: lang.pages.home.ctaServices, primary: true },
      { href: pub(urlFor(lang, "contact")), label: lang.pages.home.ctaContact },
    ])}
  </div></section>
  <section class="section"><div class="wrap">
    <h2>${esc(lang.pages.home.servicesTitle)}</h2>
    <p class="lead">${esc(lang.pages.home.servicesLead)}</p>
    <ol class="cards">${cards}</ol>
  </div></section>
  <section class="product-band" id="choir-rehearsal"><div class="wrap">
    <p class="eyebrow">${esc(lang.product.kicker)}</p>
    <div class="product-grid">
      <div>
        <h2>Choir Rehearsal</h2>
        ${lang.product.homeLead.map((paragraph) => `<p>${inline(paragraph)}</p>`).join("")}
        ${productLinks(lang, true)}
      </div>
      ${figure(lang, false)}
    </div>
    <div class="feature-grid">${features}</div>
  </div></section>
  <section class="section"><div class="wrap">
    <h2>${esc(lang.pages.home.whyTitle)}</h2>
    <div class="why">${why}</div>
  </div></section>
  <section class="cta-band"><div class="wrap">
    <h2>${esc(lang.pages.home.ctaTitle)}</h2>
    <p>${esc(lang.pages.home.ctaText)}</p>
    ${actions([{ href: pub(urlFor(lang, "contact")), label: lang.ui.contact, primary: true }])}
  </div></section>`;
  const graph = [
    organization(),
    {
      "@type": "WebSite",
      "@id": `${origin}/#website`,
      url: `${origin}/`,
      name: "Compath OÜ",
      publisher: { "@id": `${origin}/#organization` },
      inLanguage: ["et", "en", "ru"],
    },
    ...lang.services.map((service) => serviceNode(lang, service)),
    software(lang),
  ];
  return layout(lang, "home", main, graph);
}

function servicesPage(lang) {
  const trail = crumbs(lang, [
    { name: lang.ui.home, path: urlFor(lang, "home") },
    { name: lang.ui.services, path: urlFor(lang, "services") },
  ]);
  const sections = lang.services.map((service) => `<section id="${esc(service.id)}">
    <h2>${esc(service.name)}</h2>
    <p>${esc(service.summary)}</p>
    <p><a href="${pub(urlFor(lang, service.id))}">${esc(lang.ui.more)}</a></p>
  </section>`).join("");
  const main = `<header class="page-head"><div class="wrap">
    ${trail.html}
    <h1>${esc(lang.pages.services.h1)}</h1>
    <p class="lead">${esc(lang.pages.services.lead)}</p>
  </div></header>
  <div class="section"><div class="wrap prose">${sections}</div></div>`;
  return layout(lang, "services", main, [organization(), ...lang.services.map((service) => serviceNode(lang, service)), trail.data]);
}

function servicePage(lang, id) {
  const service = serviceById(lang, id);
  const trail = crumbs(lang, [
    { name: lang.ui.home, path: urlFor(lang, "home") },
    { name: lang.ui.services, path: urlFor(lang, "services") },
    { name: service.name, path: urlFor(lang, id) },
  ]);
  const body = service.blocks.map((block) => {
    if (block.h2) return `<h2>${inline(block.h2)}</h2>`;
    if (block.p) return `<p>${inline(block.p)}</p>`;
    if (block.ul) return `<ul>${block.ul.map((item) => `<li>${inline(item)}</li>`).join("")}</ul>`;
    return "";
  }).join("\n");
  const others = lang.services.filter((item) => item.id !== id).map((item) => `<li><a href="${pub(urlFor(lang, item.id))}">${esc(item.name)}</a></li>`).join("");
  const main = `<header class="page-head"><div class="wrap">
    ${trail.html}
    <h1>${esc(service.h1)}</h1>
    <p class="lead">${esc(service.lead)}</p>
  </div></header>
  <div class="section"><div class="wrap prose">
    ${body}
    <h2>${esc(lang.ui.other)}</h2>
    <ul>${others}</ul>
  </div></div>`;
  return layout(lang, id, main, [organization(), serviceNode(lang, service), trail.data]);
}

function choirPage(lang) {
  const copy = lang.product;
  const trail = crumbs(lang, [
    { name: lang.ui.home, path: urlFor(lang, "home") },
    { name: "Choir Rehearsal", path: urlFor(lang, "choir") },
  ]);
  const features = copy.features.map((item) => `<article class="feature"><h3>${esc(item.title)}</h3><p>${esc(item.text)}</p></article>`).join("");
  const list = (items) => `<ul>${items.map((item) => `<li>${esc(item)}</li>`).join("")}</ul>`;
  const main = `<header class="page-head"><div class="wrap">
    ${trail.html}
    <p class="eyebrow">${esc(copy.kicker)}</p>
    <h1>${esc(copy.h1)}</h1>
    <p class="lead">${esc(copy.lead)}</p>
    ${productLinks(lang, false)}
  </div></header>
  <div class="section"><div class="wrap prose">
    <div class="product-grid">
      <div>
        <h2>${esc(copy.whatTitle)}</h2>
        ${copy.what.map((paragraph) => `<p>${inline(paragraph)}</p>`).join("")}
        <h2>${esc(copy.whoTitle)}</h2>
        ${list(copy.who)}
      </div>
      ${figure(lang, true)}
    </div>
    <h2>${esc(copy.featuresTitle)}</h2>
    <div class="feature-grid">${features}</div>
    <h2>${esc(copy.editionsTitle)}</h2>
    <div class="editions">
      <article class="edition"><h3>${esc(copy.liteTitle)}</h3><p class="price">${esc(copy.litePrice)}</p>${list(copy.lite)}</article>
      <article class="edition edition--pro"><h3>${esc(copy.proTitle)}</h3><p class="price">${esc(copy.proPrice)}</p>${list(copy.pro)}</article>
    </div>
    <h2>${esc(copy.demoTitle)}</h2>
    <p>${inline(copy.demo)} <a href="${product.demo}" rel="noopener noreferrer">${esc(product.demo)}</a></p>
    <h2>${esc(copy.langTitle)}</h2>
    <p>${esc(copy.languages)}</p>
    ${productLinks(lang, false)}
    <p><a href="${pub(urlFor(lang, "plugins"))}">${esc(copy.pluginLink)}</a></p>
  </div></div>`;
  const app = software(lang);
  return layout(lang, "choir", main, [organization(), app, { "@type": "WebPage", "@id": `${abs(urlFor(lang, "choir"))}#webpage`, url: abs(urlFor(lang, "choir")), name: copy.title, inLanguage: lang.code, mainEntity: { "@id": app["@id"] } }, trail.data]);
}

function contactPage(lang) {
  const trail = crumbs(lang, [
    { name: lang.ui.home, path: urlFor(lang, "home") },
    { name: lang.ui.contact, path: urlFor(lang, "contact") },
  ]);
  const form = lang.form;
  const main = `<header class="page-head"><div class="wrap">
    ${trail.html}
    <h1>${esc(lang.pages.contact.h1)}</h1>
    <p class="lead">${esc(lang.pages.contact.lead)}</p>
  </div></header>
  <div class="section"><div class="wrap split">
    <div>
    <dl class="facts">
      <div><dt>${esc(lang.ui.addressLabel)}</dt><dd>Compath OÜ<br />Ahtri 12<br />Tallinn 10151<br />Estonia</dd></div>
      <div><dt>${esc(lang.ui.phoneLabel)}</dt><dd><a href="tel:+37255520482">+372 55520482</a></dd></div>
      <div><dt>${esc(lang.ui.hoursLabel)}</dt><dd>${esc(lang.ui.hours)}</dd></div>
      <div><dt>${esc(lang.ui.regLabel)}</dt><dd>11502963</dd></div>
      <div><dt>${esc(lang.ui.vatLabel)}</dt><dd>EE101244231</dd></div>
    </dl>
    <p><a href="${mapUrl}" rel="noopener noreferrer">${esc(lang.ui.map)}</a></p>
    <p id="mail-slot" data-u="support" data-h="compath.ee"></p>
    </div>
    ${basePath ? `<div class="preview-note" role="note"><p>${esc(lang.ui.previewForm)}</p></div>` : `<form id="contact-form" class="form" method="post" action="/api/contact.php" data-sending="${esc(form.sending)}" data-success="${esc(form.success)}" data-invalid="${esc(form.invalid)}" data-error="${esc(form.error)}">
      <p class="meta">${esc(lang.ui.required)} ${esc(lang.ui.privacyNote)} <a href="${pub(urlFor(lang, "privacy"))}">${esc(lang.ui.privacy)}</a></p>
      <div class="hp" aria-hidden="true"><label for="company">${esc(form.hp)}</label><input id="company" name="company" type="text" tabindex="-1" autocomplete="off" value="" /></div>
      <input type="hidden" name="locale" value="${lang.code}" />
      <label for="name">${esc(form.name)} <span aria-hidden="true">*</span><input id="name" name="name" type="text" required maxlength="120" autocomplete="name" /></label>
      <label for="email">${esc(form.email)} <span aria-hidden="true">*</span><input id="email" name="email" type="email" required maxlength="200" autocomplete="email" inputmode="email" /></label>
      <label for="phone">${esc(form.phone)} <span class="hint">(${esc(form.optional)})</span><input id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" /></label>
      <label for="message">${esc(form.message)} <span aria-hidden="true">*</span><textarea id="message" name="message" required minlength="10" maxlength="5000"></textarea></label>
      <button class="btn btn--primary" type="submit">${esc(form.submit)}</button>
      <p id="form-status" class="status" role="status"></p>
    </form>`}
  </div></div>`;
  return layout(lang, "contact", main, [
    organization(),
    { "@type": "ContactPage", "@id": `${abs(urlFor(lang, "contact"))}#contact`, url: abs(urlFor(lang, "contact")), name: lang.pages.contact.title, inLanguage: lang.code },
    trail.data,
  ]);
}

function prosePage(lang, id) {
  const page = lang.pages[id];
  const trail = crumbs(lang, [
    { name: lang.ui.home, path: urlFor(lang, "home") },
    { name: id === "privacy" ? lang.ui.privacy : lang.ui.contact, path: urlFor(lang, id === "privacy" ? "privacy" : "contact") },
    ...(id === "privacy" ? [] : [{ name: page.h1, path: urlFor(lang, id) }]),
  ]);
  const body = page.sections
    ? `<p class="meta">${esc(page.updated)}</p>${page.sections.map((section) => `<h2>${esc(section.h2)}</h2>${section.paragraphs.map((paragraph) => `<p>${inline(paragraph)}</p>`).join("")}`).join("")}`
    : `<p>${esc(page.text)}</p><p><a href="${pub(urlFor(lang, "contact"))}">${esc(lang.ui.contact)}</a></p>`;
  const main = `<header class="page-head"><div class="wrap">${trail.html}<h1>${esc(page.h1)}</h1></div></header>
  <div class="section"><div class="wrap prose">${body}</div></div>`;
  const graph = [organization(), trail.data];
  return layout(lang, id, main, graph);
}

function writePage(urlPath, html) {
  const relative = urlPath === "/" ? "index.html" : `${urlPath.replace(/^\//, "").replace(/\/$/, "")}/index.html`;
  const dest = path.join(dist, relative);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, html);
}

function copyAssets() {
  const assets = path.join(dist, "assets");
  fs.mkdirSync(assets, { recursive: true });
  for (const name of ["site.css", "site.js"]) {
    fs.copyFileSync(path.join(root, "assets", name), path.join(assets, name));
  }
  const brand = path.join(root, "assets", "brand");
  const skip = new Set(["images.json", "choir-demo.webp", "compath-logo-source.png", "logo-meta.json", "logo-dots.png"]);
  for (const name of fs.readdirSync(brand)) {
    if (skip.has(name)) continue;
    const inAssets = name.startsWith("og-") || name.startsWith("choir-") || name === "logo.png";
    const target = inAssets ? path.join(assets, name) : path.join(dist, name);
    fs.copyFileSync(path.join(brand, name), target);
  }
  fs.writeFileSync(path.join(dist, "site.webmanifest"), `${JSON.stringify({
    name: "Compath OÜ",
    short_name: "Compath",
    icons: [
      { src: pub("/favicon-192.png"), sizes: "192x192", type: "image/png" },
      { src: pub("/favicon-512.png"), sizes: "512x512", type: "image/png" },
    ],
    theme_color: "#e85d04",
    background_color: "#ffffff",
    display: "standalone",
  }, null, 2)}\n`);
}

function robots() {
  const groups = ["*", "Googlebot", "Bingbot", "GPTBot", "OAI-SearchBot", "ClaudeBot", "PerplexityBot", "Google-Extended"];
  const lines = [];
  for (const agent of groups) {
    lines.push(`User-agent: ${agent}`, "Allow: /", "Disallow: /api/", "");
  }
  lines.push(`Sitemap: ${origin}/sitemap.xml`, "");
  fs.writeFileSync(path.join(dist, "robots.txt"), lines.join("\n"));
}

function sitemap() {
  const xml = (value) => String(value).replace(/[&<>"']/g, (char) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&apos;",
  }[char]));
  const urls = [];
  for (const id of sitemapIds) {
    for (const lang of langs) {
      const loc = abs(urlFor(lang, id));
      const alternates = langs.map((item) => `    <xhtml:link rel="alternate" hreflang="${item.code}" href="${xml(abs(urlFor(item, id)))}" />`).join("\n");
      const xdefault = `    <xhtml:link rel="alternate" hreflang="x-default" href="${xml(abs(urlFor(byCode.et, id)))}" />`;
      urls.push(`  <url>\n    <loc>${xml(loc)}</loc>\n    <lastmod>${updated}</lastmod>\n${alternates}\n${xdefault}\n  </url>`);
    }
  }
  fs.writeFileSync(path.join(dist, "sitemap.xml"), `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${urls.join("\n")}\n</urlset>\n`);
}

function llms() {
  const en = byCode.en;
  const lines = [
    "# Compath OÜ",
    "",
    `> ${en.pages.home.description}`,
    "",
    "Compath OÜ is a company in Tallinn, Estonia (Ahtri 12, 10151). Registry code 11502963. VAT EE101244231. Phone +372 55520482. Hours Monday–Friday 09:00–17:00.",
    "",
    "The company has three services. It does not offer translation or video production.",
    "",
    "## Services",
    "",
  ];
  for (const lang of langs) {
    lines.push(`### ${lang.labelName}`, "");
    for (const id of ["services", "it", "plugins", "amazon"]) {
      const page = id === "services" ? lang.pages.services : serviceById(lang, id);
      lines.push(`- [${page.h1}](${abs(urlFor(lang, id))}): ${page.description}`);
    }
    lines.push("");
  }
  lines.push("## Choir Rehearsal", "", "Choir Rehearsal is Compath’s own WordPress plugin: a private rehearsal library for choirs (songs, per-voice tracks, PDF scores, YouTube embeds). Lite is free. Pro is €49/year. Role names Singer, Voice Leader and Administrator are never translated.", "");
  for (const lang of langs) {
    lines.push(`- [${lang.labelName}](${abs(urlFor(lang, "choir"))})`);
  }
  lines.push(
    `- Product site: ${product.bare}`,
    `- Estonian product site: ${product.localized.et}`,
    `- Russian product site: ${product.localized.ru}`,
    `- English product site: ${product.localized.en}`,
    `- Live demo: ${product.demo}`,
    `- Lite: ${product.wporg}`,
    `- Pro: ${product.shop}`,
    "",
    "## Contact",
    "",
    `- English: ${abs(urlFor(byCode.en, "contact"))}`,
    `- Estonian: ${abs(urlFor(byCode.et, "contact"))}`,
    `- Russian: ${abs(urlFor(byCode.ru, "contact"))}`,
    "- Map: " + mapUrl,
    "",
  );
  fs.writeFileSync(path.join(dist, "llms.txt"), lines.join("\n"));
}

function htaccess() {
  const rules = redirects.map((rule) => `RewriteRule ^${rule.from}$ ${rule.to} [R=301,L]`).join("\n");
  const text = `# Compath.ee static site rules.
# MERGE this into the existing /public_html/.htaccess. Do not replace that
# file blindly: keep every hosting PHP handler line already there
# (AddHandler, AddType, suPHP_ConfigPath, php_flag, php_value, and any SSL
# or auth block the host added).
# If RewriteEngine On is already present, paste only the RewriteRule lines.

DirectoryIndex index.html index.php
Options -Indexes +FollowSymLinks

<IfModule mod_rewrite.c>
RewriteEngine On
${rules}
RewriteCond %{THE_REQUEST} \\s/+(.*/)?index\\.html[\\s?] [NC]
RewriteRule ^(.*/)?index\\.html$ /$1 [R=301,L]
</IfModule>

<IfModule mod_headers.c>
Header set X-Content-Type-Options "nosniff"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set X-Frame-Options "SAMEORIGIN"
Header set Content-Security-Policy "default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'"
</IfModule>

ErrorDocument 404 /404.html
`;
  fs.writeFileSync(path.join(dist, "htaccess.snippet"), text);
  fs.writeFileSync(path.join(root, "htaccess.snippet"), text);
}

function notFound() {
  const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Page not found | Compath OÜ</title>
<meta name="robots" content="${basePath ? "noindex, nofollow" : "noindex"}" />
<link rel="canonical" href="${origin}/404.html" />
<link rel="stylesheet" href="${pub("/assets/site.css")}" />
<link rel="icon" href="${pub("/favicon.svg")}" type="image/svg+xml" />
</head>
<body>
<div class="accent"></div>${basePath ? `\n<p class="preview-badge">Preview</p>` : ""}
<main id="main" class="section"><div class="wrap">
<h1>Page not found</h1>
<p>Lehte ei leitud. Страница не найдена.</p>
<ul>
<li><a href="${pub("/")}">English</a></li>
<li><a href="${pub("/et/")}">Eesti</a></li>
<li><a href="${pub("/ru/")}">Русский</a></li>
</ul>
</div></main>
</body>
</html>
`;
  fs.writeFileSync(path.join(dist, "404.html"), html);
}

function previewHtaccess() {
  const text = `# Folder rules for /public_html/preview-2026/ only.
# Apache reads this file for this directory and its children.
# It does not replace /public_html/.htaccess and does not change
# the Sitebuilder site at the web root.
# These lines do not redirect any URL, so they cannot change the
# Sitebuilder pages outside this folder. noindex does not depend
# on a robots.txt in this folder or at the web root.

DirectoryIndex index.html
Options -Indexes

<IfModule mod_headers.c>
Header always set X-Robots-Tag "noindex, nofollow"
</IfModule>

ErrorDocument 404 /preview-2026/404.html
`;
  fs.writeFileSync(path.join(dist, ".htaccess"), text);
}

function previewFtpList() {
  const files = [];
  function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(full);
      else files.push("/public_html/preview-2026/" + path.relative(dist, full).split(path.sep).join("/"));
    }
  }
  walk(dist);
  files.sort();
  const text = [
    "Preview files uploaded from compath-site/dist-preview/ to /public_html/preview-2026/.",
    "This list is separate from the production upload in FTP-PATHS.txt.",
    "The folder .htaccess is part of this upload. It does not replace the web-root .htaccess.",
    "",
    ...files,
    "",
  ].join("\n");
  fs.writeFileSync(path.join(root, "PREVIEW-FTP-PATHS.txt"), text);
}

function ftpList() {
  const files = [];
  function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(full);
      else if (entry.name !== "htaccess.snippet") {
        files.push("/public_html/" + path.relative(dist, full).split(path.sep).join("/"));
      }
    }
  }
  walk(dist);
  files.sort();
  const manual = [
    "/public_html/api/contact.php",
    "/public_html/api/.htaccess",
    "/public_html/api/config.php   (create on the server; not in git)",
  ];
  const text = [
    "Static files uploaded from compath-site/dist/ (htaccess.snippet is merged by hand, not uploaded as .htaccess):",
    "",
    ...files,
    "",
    "Uploaded separately, once, and then left alone by the GitHub Action:",
    "",
    ...manual,
    "",
  ].join("\n");
  fs.writeFileSync(path.join(root, "FTP-PATHS.txt"), text);
}

fs.rmSync(dist, { recursive: true, force: true });
fs.mkdirSync(dist, { recursive: true });
copyAssets();
for (const lang of langs) {
  writePage(urlFor(lang, "home"), home(lang));
  writePage(urlFor(lang, "services"), servicesPage(lang));
  for (const id of ["it", "plugins", "amazon"]) writePage(urlFor(lang, id), servicePage(lang, id));
  writePage(urlFor(lang, "choir"), choirPage(lang));
  writePage(urlFor(lang, "contact"), contactPage(lang));
  writePage(urlFor(lang, "privacy"), prosePage(lang, "privacy"));
  writePage(urlFor(lang, "thanks"), prosePage(lang, "thanks"));
  writePage(urlFor(lang, "formError"), prosePage(lang, "formError"));
}
notFound();
if (preview) {
  previewHtaccess();
  fs.writeFileSync(path.join(dist, "health.txt"), "preview OK\n");
  previewFtpList();
} else {
  robots();
  sitemap();
  llms();
  htaccess();
  fs.writeFileSync(path.join(dist, "health.txt"), "deploy OK\n");
  ftpList();
}

const problems = validate(dist, { preview });
if (problems.length) {
  console.error(problems.join("\n"));
  process.exit(1);
}
console.log(`Built ${langs.length} languages into ${dist}`);
