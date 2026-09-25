import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";

const root = import.meta.dirname;
const origin = "https://compath.ee";

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, out);
    else out.push(full);
  }
  return out;
}

function contrast(hexA, hexB) {
  const lin = (channel) => {
    const value = channel / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  };
  const lum = (hex) => {
    const raw = hex.replace("#", "");
    const r = parseInt(raw.slice(0, 2), 16);
    const g = parseInt(raw.slice(2, 4), 16);
    const b = parseInt(raw.slice(4, 6), 16);
    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
  };
  const a = lum(hexA);
  const b = lum(hexB);
  const hi = Math.max(a, b);
  const lo = Math.min(a, b);
  return (hi + 0.05) / (lo + 0.05);
}

export function validate(dist, options = {}) {
  const preview = options.preview === true;
  const base = "/preview-2026";
  const problems = [];
  if (!fs.existsSync(dist)) {
    problems.push(`${preview ? "preview" : "production"} output missing: ${dist}`);
    return problems;
  }
  const files = walk(dist).filter((file) => file.endsWith(".html") || file.endsWith(".txt") || file.endsWith(".xml") || file.endsWith(".css") || file.endsWith(".js"));
  const banned = ["sisterz", "tõlketeenus", "mail-slot"];
  for (const file of files) {
    const text = fs.readFileSync(file, "utf8");
    for (const word of banned) {
      if (text.toLowerCase().includes(word.toLowerCase())) {
        problems.push(`${path.relative(dist, file)} contains ${word}`);
      }
    }
  }

  const pages = walk(dist).filter((file) => file.endsWith(".html") && !file.endsWith(`${path.sep}404.html`));
  for (const file of pages) {
    const html = fs.readFileSync(file, "utf8");
    const rel = path.relative(dist, file);
    const h1 = html.match(/<h1\b/g) || [];
    if (h1.length !== 1) problems.push(`${rel} has ${h1.length} h1 elements`);
    if (!/<html lang="(en|et|ru)"/.test(html)) problems.push(`${rel} missing lang`);
    if (!html.includes('rel="canonical"')) problems.push(`${rel} missing canonical`);
    if (preview) {
      if (!html.includes('<meta name="robots" content="noindex, nofollow" />')) {
        problems.push(`${rel} preview robots meta`);
      }
      if (html.includes("/preview-2026/") && html.includes('rel="canonical" href="https://compath.ee/preview-2026')) {
        problems.push(`${rel} canonical uses the preview path`);
      }
      const canonical = html.match(/rel="canonical" href="([^"]+)"/);
      if (!canonical || !canonical[1].startsWith(`${origin}/`) || canonical[1].includes(base)) {
        problems.push(`${rel} canonical must be the future production URL`);
      }
      for (const link of html.matchAll(/hreflang="[^"]+" href="([^"]+)"/g)) {
        if (link[1].includes(base)) problems.push(`${rel} hreflang points at preview`);
      }
      if (!html.includes(`href="${base}/assets/site.css"`) || !html.includes('class="preview-badge"')) {
        problems.push(`${rel} missing preview base path or badge`);
      }
      if (html.includes("/api/contact.php") || html.includes("<form")) problems.push(`${rel} preview still has a contact form`);
    } else if (html.includes(base)) {
      problems.push(`${rel} production page links into the preview path`);
    }
    for (const code of ["en", "et", "ru", "x-default"]) {
      if (!html.includes(`hreflang="${code}"`)) problems.push(`${rel} missing hreflang ${code}`);
    }
    if (!html.includes('content="1200"') || !html.includes('content="630"')) problems.push(`${rel} missing OG size`);
    if (!html.includes(">ENG<") || !html.includes(">EST<") || !html.includes(">RUS<")) problems.push(`${rel} missing language labels`);
    if (!html.includes('class="brand__logo"') || !html.includes('alt="Compath"') || !html.includes("logo.png")) {
      problems.push(`${rel} missing official logo`);
    }
    const logoSize = html.match(/class="brand__logo"[^>]*width="(\d+)" height="(\d+)"/);
    if (!logoSize || logoSize[1] === "0" || logoSize[2] === "0") problems.push(`${rel} logo width and height`);
    if (html.includes("brand__name") || html.includes("mark.svg")) problems.push(`${rel} still uses the old mark`);
    if (html.includes('class="topbar"')) problems.push(`${rel} still has the header contact bar`);
    if (!/<nav class="nav"[\s\S]*?href="https:\/\/shop\.compath\.ee\/"/.test(html)) {
      problems.push(`${rel} missing shop link`);
    }
    const pageLang = html.match(/<html lang="(en|et|ru)"/);
    const shopLabel = { en: ">Shop<", et: ">Pood<", ru: ">Магазин<" };
    if (pageLang && !html.includes(shopLabel[pageLang[1]])) problems.push(`${rel} shop label`);
    if (!html.includes('<label for="lang" class="visually-hidden">')) problems.push(`${rel} language label is visible`);
    const main = html.match(/<main id="main">([\s\S]*?)<\/main>/);
    const allowPlace = new Set([
      path.join("contact", "index.html"),
      path.join("privacy", "index.html"),
      path.join("et", "kontakt", "index.html"),
      path.join("et", "privaatsus", "index.html"),
      path.join("ru", "kontakty", "index.html"),
      path.join("ru", "konfidencialnost", "index.html"),
    ]);
    if (main && !allowPlace.has(rel)) {
      for (const word of ["Tallinn", "Ahtri", "Таллин"]) {
        if (main[1].includes(word)) problems.push(`${rel} body mentions ${word}`);
      }
    }
    if (!/class="site-footer"[\s\S]*?href="mailto:support@compath\.ee">support@compath\.ee<\/a>/.test(html)) {
      problems.push(`${rel} footer missing support mailto`);
    }
    if (!html.includes("<!-- AI chat widget: insert script here -->\n</body>")) {
      problems.push(`${rel} missing chat widget placeholder`);
    }
    if (/position\s*:\s*fixed/i.test(html) || /bottom:\s*0/.test(html)) {
      problems.push(`${rel} has a fixed or bottom-pinned element`);
    }
    if (rel === path.join("contact", "index.html") || rel === path.join("et", "kontakt", "index.html") || rel === path.join("ru", "kontakty", "index.html")) {
      const facts = html.match(/<dl class="facts">([\s\S]*?)<\/dl>/);
      if (!facts || !facts[1].includes('href="mailto:support@compath.ee"') || !facts[1].includes('href="tel:+37255520482"')) {
        problems.push(`${rel} contact facts missing email next to the phone`);
      }
    }
    const blocks = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)];
    if (!blocks.length) problems.push(`${rel} missing JSON-LD`);
    for (const block of blocks) {
      let data;
      try {
        data = JSON.parse(block[1]);
      } catch (error) {
        problems.push(`${rel} JSON-LD parse: ${error.message}`);
        continue;
      }
      if (data["@context"] !== "https://schema.org" || !Array.isArray(data["@graph"])) {
        problems.push(`${rel} JSON-LD is not a schema.org @graph`);
        continue;
      }
      if (preview && block[1].includes(base)) problems.push(`${rel} JSON-LD mentions the preview path`);
      const types = data["@graph"].flatMap((node) => [].concat(node["@type"] || []));
      const business = data["@graph"].find((node) => [].concat(node["@type"] || []).includes("LocalBusiness"));
      if (business) {
        const point = business.contactPoint || {};
        const hours = point.hoursAvailable || {};
        const languages = [].concat(point.availableLanguage || []);
        const days = [].concat(hours.dayOfWeek || []);
        const week = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
        if (point["@type"] !== "ContactPoint" || point.contactType !== "customer support" || point.email !== "support@compath.ee" || point.telephone !== "+37255520482") {
          problems.push(`${rel} ContactPoint`);
        }
        if (["et", "en", "ru"].some((code) => !languages.includes(code)) || hours.opens !== "09:00" || hours.closes !== "17:00" || week.some((day) => !days.includes(day))) {
          problems.push(`${rel} ContactPoint hours or languages`);
        }
        if (JSON.stringify(data).includes("areaServed")) problems.push(`${rel} schema sells a city`);
      }
      if (rel === "index.html" || rel === path.join("et", "index.html") || rel === path.join("ru", "index.html")) {
        if (!types.includes("LocalBusiness") || !types.includes("SoftwareApplication") || !types.includes("Service")) {
          problems.push(`${rel} home schema missing LocalBusiness, Service or SoftwareApplication`);
        }
        const business = data["@graph"].find((node) => [].concat(node["@type"]).includes("LocalBusiness"));
        for (const key of ["address", "telephone", "vatID", "openingHoursSpecification", "geo"]) {
          if (!business || business[key] == null) problems.push(`${rel} LocalBusiness missing ${key}`);
        }
        if (business && business.openingHours !== "Mo-Fr 09:00-17:00") problems.push(`${rel} openingHours`);
        const services = data["@graph"].filter((node) => node["@type"] === "Service");
        if (services.length !== 3) problems.push(`${rel} expected 3 Service nodes, found ${services.length}`);
      }
      if (rel.includes(`${path.sep}choir-rehearsal${path.sep}`) || rel.startsWith(`choir-rehearsal${path.sep}`)) {
        const app = data["@graph"].find((node) => node["@type"] === "SoftwareApplication");
        if (!app) problems.push(`${rel} missing SoftwareApplication`);
        else if (!Array.isArray(app.offers) || app.offers.length < 2) problems.push(`${rel} SoftwareApplication offers`);
        else if (String(app.offers[1].price) !== "49") problems.push(`${rel} Pro price`);
      }
      const crumbs = data["@graph"].find((node) => node["@type"] === "BreadcrumbList");
      if (rel !== "index.html" && !rel.endsWith(`${path.sep}index.html`.replace("index.html", "index.html"))) {
        /* inner pages should have breadcrumbs; home is */ 
      }
      if (!rel.endsWith(`${path.sep}index.html`) && rel !== "index.html") {
        problems.push(`${rel} unexpected html path`);
      }
      if (rel !== "index.html" && !(rel.endsWith(`${path.sep}index.html`) && rel.split(path.sep).length === 2 && ["et", "ru"].includes(rel.split(path.sep)[0]))) {
        if (!crumbs) problems.push(`${rel} missing BreadcrumbList`);
        else {
          crumbs.itemListElement.forEach((item, index) => {
            if (item.position !== index + 1 || !item.name || !item.item) problems.push(`${rel} breadcrumb ${index}`);
          });
        }
      }
      if (types.includes("Service")) {
        for (const service of data["@graph"].filter((node) => node["@type"] === "Service")) {
          for (const key of ["name", "description", "url", "provider"]) {
            if (!service[key]) problems.push(`${rel} Service missing ${key}`);
          }
        }
      }
    }
  }

  const redirects = JSON.parse(fs.readFileSync(path.join(root, "redirects.json"), "utf8"));
  const samples = {
    "/Services/": "/services/",
    "/Services/extra": "/services/",
    "/Contacts": "/contact/",
    "/et/Services/": "/et/teenused/",
    "/et/Contacts/team": "/et/kontakt/",
    "/ru/Services/": "/ru/uslugi/",
    "/ru/Contacts/": "/ru/kontakty/",
    "/et/": null,
    "/ru/": null,
    "/services/": null,
    "/contact/": null,
  };
  for (const [input, expected] of Object.entries(samples)) {
    const pathOnly = input.replace(/^\//, "");
    const hit = redirects.find((rule) => new RegExp(`^${rule.from}$`).test(pathOnly));
    const actual = hit ? hit.to : null;
    if (actual !== expected) problems.push(`redirect ${input} -> ${actual}, expected ${expected}`);
  }

  const pairs = [
    ["#1c1c1c", "#ffffff"],
    ["#4a453f", "#ffffff"],
    ["#1c1c1c", "#FE4F00"],
    ["#1c1c1c", "#f26b00"],
    ["#ffffff", "#1c1c1c"],
    ["#f0ebe4", "#1c1c1c"],
    ["#173ea8", "#ffffff"],
  ];
  for (const [fg, bg] of pairs) {
    const ratio = contrast(fg, bg);
    if (ratio < 4.5) problems.push(`contrast ${fg} on ${bg} is ${ratio.toFixed(2)}`);
  }

  const iconNames = ["favicon.ico", "favicon.svg", "apple-touch-icon.png", "favicon-192.png", "favicon-512.png"];
  const iconDir = path.join(root, "assets", "brand");
  const hash = (file) => crypto.createHash("sha256").update(fs.readFileSync(file)).digest("hex");
  for (const name of iconNames) {
    const from = path.join(iconDir, name);
    const to = path.join(dist, name);
    if (!fs.existsSync(from) || !fs.existsSync(to)) {
      problems.push(`missing favicon ${name}`);
      continue;
    }
    if (hash(from) !== hash(to)) problems.push(`${name} was not copied from the brand set`);
  }
  const iconSvg = fs.readFileSync(path.join(dist, "favicon.svg"), "utf8");
  if (!iconSvg.includes("radialGradient") || !iconSvg.includes("#FEFE00") || !iconSvg.includes("#FE4F00")) {
    problems.push("favicon svg is not the orange sphere");
  }
  if (iconSvg.includes("#1f4fd8")) problems.push("favicon still uses the rehearsal blue");
  const pngSize = (file) => {
    const buf = fs.readFileSync(file);
    return [buf.readUInt32BE(16), buf.readUInt32BE(20)];
  };
  const expect = { "apple-touch-icon.png": [180, 180], "favicon-192.png": [192, 192], "favicon-512.png": [512, 512] };
  for (const [name, size] of Object.entries(expect)) {
    const actual = pngSize(path.join(dist, name));
    if (actual[0] !== size[0] || actual[1] !== size[1]) problems.push(`${name} is ${actual.join("x")}`);
  }
  const ico = fs.readFileSync(path.join(dist, "favicon.ico"));
  if (ico.readUInt16LE(4) !== 3 || ico[6] !== 16 || ico[22] !== 32 || ico[38] !== 48) {
    problems.push("favicon.ico is not 16, 32 and 48");
  }
  const homeHtml = fs.readFileSync(path.join(dist, "index.html"), "utf8");
  if (!homeHtml.includes('href="/favicon-192.png"') && !homeHtml.includes('href="/preview-2026/favicon-192.png"')) {
    problems.push("home favicon links");
  }
  const css = fs.readFileSync(path.join(dist, "assets", "site.css"), "utf8");
  if (/position\s*:\s*fixed/i.test(css)) problems.push("site.css uses position fixed");
  const script = fs.readFileSync(path.join(dist, "assets", "site.js"), "utf8");
  if (script.includes("mail-slot") || script.includes("support@")) problems.push("site.js still hides or rebuilds the mailbox");
  const missing = fs.readFileSync(path.join(dist, "404.html"), "utf8");
  if (!/class="site-footer"[\s\S]*?href="mailto:support@compath\.ee">support@compath\.ee<\/a>/.test(missing)) {
    problems.push("404 footer missing support mailto");
  }
  if (!missing.includes("<!-- AI chat widget: insert script here -->\n</body>")) {
    problems.push("404 missing chat widget placeholder");
  }

  if (preview) {
    for (const name of ["robots.txt", "sitemap.xml", "llms.txt"]) {
      if (fs.existsSync(path.join(dist, name))) problems.push(`preview contains ${name}`);
    }
    const htaccess = path.join(dist, ".htaccess");
    if (!fs.existsSync(htaccess)) problems.push("preview missing .htaccess");
    else {
      const text = fs.readFileSync(htaccess, "utf8");
      if (!text.includes('X-Robots-Tag "noindex, nofollow"')) problems.push("preview htaccess missing X-Robots-Tag");
      if (/RewriteRule|RewriteEngine/i.test(text)) problems.push("preview htaccess must not rewrite");
      if (!text.includes("this directory")) problems.push("preview htaccess missing folder-scope note");
    }
    const home = fs.readFileSync(path.join(dist, "index.html"), "utf8");
    if (!home.includes('rel="canonical" href="https://compath.ee/"')) problems.push("preview home canonical");
    if (!home.includes('hreflang="et" href="https://compath.ee/et/"')) problems.push("preview home hreflang");
    if (!home.includes('hreflang="x-default" href="https://compath.ee/et/"')) problems.push("preview x-default");
    if (!home.includes('href="/preview-2026/"') || !home.includes('href="/preview-2026/services/"')) problems.push("preview home links");
    const et = fs.readFileSync(path.join(dist, "et", "index.html"), "utf8");
    if (!et.includes('rel="canonical" href="https://compath.ee/et/"')) problems.push("preview et canonical");
    if (!et.includes('href="/preview-2026/et/teenused/"')) problems.push("preview et links");
    const contact = fs.readFileSync(path.join(dist, "contact", "index.html"), "utf8");
    if (!contact.includes("preview-note") || !contact.includes("does not send email")) problems.push("preview contact note");
    const service = fs.readFileSync(path.join(dist, "services", "it-support", "index.html"), "utf8");
    if (!service.includes('href="/preview-2026/contact/"')) problems.push("preview inline link");
    const manifest = fs.readFileSync(path.join(dist, "site.webmanifest"), "utf8");
    if (!manifest.includes("/preview-2026/favicon-192.png") || !manifest.includes("192x192")) problems.push("preview manifest");
    const previewMissing = fs.readFileSync(path.join(dist, "404.html"), "utf8");
    if (!previewMissing.includes('<meta name="robots" content="noindex, nofollow" />')) problems.push("preview 404 robots");
    if (!previewMissing.includes('href="/preview-2026/et/"')) problems.push("preview 404 links");
  } else {
    const robots = fs.readFileSync(path.join(dist, "robots.txt"), "utf8");
    for (const bot of ["Googlebot", "Bingbot", "GPTBot", "OAI-SearchBot", "ClaudeBot", "PerplexityBot", "Google-Extended"]) {
      if (!robots.includes(`User-agent: ${bot}`)) problems.push(`robots missing ${bot}`);
    }
    const sitemap = fs.readFileSync(path.join(dist, "sitemap.xml"), "utf8");
    if (!sitemap.includes('hreflang="x-default"') || !sitemap.includes(`${origin}/et/`)) problems.push("sitemap hreflang");
    const llms = fs.readFileSync(path.join(dist, "llms.txt"), "utf8");
    if (!llms.includes("Choir Rehearsal") || !llms.includes("support@compath.ee") || !llms.includes("Mo-Fr 09:00-17:00") || !llms.includes("Ahtri 12, Tallinn 10151")) problems.push("llms.txt");
    if (/company in Tallinn/i.test(llms)) problems.push("llms.txt sells Tallinn");
    const contact = fs.readFileSync(path.join(dist, "contact", "index.html"), "utf8");
    if (!contact.includes('action="/api/contact.php"')) problems.push("production contact form");
  }
  return problems;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const preview = process.argv.includes("--preview");
  const problems = validate(path.join(root, preview ? "dist-preview" : "dist"), { preview });
  if (problems.length) {
    console.error(problems.join("\n"));
    process.exit(1);
  }
  console.log(preview ? "preview OK" : "OK");
}
