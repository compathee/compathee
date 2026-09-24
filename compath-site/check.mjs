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
  const banned = ["mailto:", "support@compath.ee", "sisterz", "tõlketeenus"];
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
      if (rel === "index.html" || rel === path.join("et", "index.html") || rel === path.join("ru", "index.html")) {
        if (!types.includes("LocalBusiness") || !types.includes("SoftwareApplication") || !types.includes("Service")) {
          problems.push(`${rel} home schema missing LocalBusiness, Service or SoftwareApplication`);
        }
        const business = data["@graph"].find((node) => [].concat(node["@type"]).includes("LocalBusiness"));
        for (const key of ["address", "telephone", "vatID", "openingHoursSpecification", "geo"]) {
          if (!business || business[key] == null) problems.push(`${rel} LocalBusiness missing ${key}`);
        }
        if (business && business.openingHours !== "Mo-Fr 09:00-17:00") problems.push(`${rel} openingHours`);
        if (JSON.stringify(business).includes("support@")) problems.push(`${rel} schema has email`);
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
    ["#142033", "#ffffff"],
    ["#3a465c", "#ffffff"],
    ["#ffffff", "#1f4fd8"],
    ["#ffffff", "#10192f"],
    ["#d6e2ff", "#10192f"],
    ["#173ea8", "#ffffff"],
  ];
  for (const [fg, bg] of pairs) {
    const ratio = contrast(fg, bg);
    if (ratio < 4.5) problems.push(`contrast ${fg} on ${bg} is ${ratio.toFixed(2)}`);
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
    if (!manifest.includes("/preview-2026/favicon-192.png")) problems.push("preview manifest");
    const missing = fs.readFileSync(path.join(dist, "404.html"), "utf8");
    if (!missing.includes('<meta name="robots" content="noindex, nofollow" />')) problems.push("preview 404 robots");
    if (!missing.includes('href="/preview-2026/et/"')) problems.push("preview 404 links");
  } else {
    const robots = fs.readFileSync(path.join(dist, "robots.txt"), "utf8");
    for (const bot of ["Googlebot", "Bingbot", "GPTBot", "OAI-SearchBot", "ClaudeBot", "PerplexityBot", "Google-Extended"]) {
      if (!robots.includes(`User-agent: ${bot}`)) problems.push(`robots missing ${bot}`);
    }
    const sitemap = fs.readFileSync(path.join(dist, "sitemap.xml"), "utf8");
    if (!sitemap.includes('hreflang="x-default"') || !sitemap.includes(`${origin}/et/`)) problems.push("sitemap hreflang");
    if (!fs.readFileSync(path.join(dist, "llms.txt"), "utf8").includes("Choir Rehearsal")) problems.push("llms.txt");
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
