import fs from "node:fs";
import http from "node:http";
import path from "node:path";

const root = import.meta.dirname;
const dist = path.join(root, "dist");
const redirects = JSON.parse(fs.readFileSync(path.join(root, "redirects.json"), "utf8")).map((rule) => ({
  re: new RegExp(`^${rule.from}$`),
  to: rule.to,
}));
const port = Number(process.env.PORT || 4173);

const types = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".svg": "image/svg+xml",
  ".webp": "image/webp",
  ".png": "image/png",
  ".ico": "image/x-icon",
  ".xml": "application/xml; charset=utf-8",
  ".txt": "text/plain; charset=utf-8",
  ".webmanifest": "application/manifest+json",
};

function redirectTarget(urlPath) {
  const bare = urlPath.replace(/^\//, "").replace(/\/$/, (match, offset, string) => (string.length > 1 ? "" : match));
  const probe = urlPath.replace(/^\//, "");
  const hit = redirects.find((rule) => rule.re.test(probe) || rule.re.test(probe.replace(/\/$/, "")));
  return hit ? hit.to : null;
}

function fileFor(urlPath) {
  const relative = decodeURIComponent(urlPath.split("?")[0]);
  const clean = path.normalize(relative).replace(/^(\.\.(\/|\\|$))+/, "");
  const full = path.join(dist, clean);
  if (!full.startsWith(dist)) return null;
  if (fs.existsSync(full) && fs.statSync(full).isDirectory()) {
    const index = path.join(full, "index.html");
    return fs.existsSync(index) ? index : null;
  }
  if (fs.existsSync(full) && fs.statSync(full).isFile()) return full;
  if (!path.extname(full)) {
    const index = path.join(full, "index.html");
    if (fs.existsSync(index)) return index;
  }
  return null;
}

function field(raw, name) {
  const match = raw.match(new RegExp(`name="${name}"\\r\\n\\r\\n([^\\r]*)`));
  return match ? match[1].trim() : "";
}

function previewContact(request, response) {
  const chunks = [];
  request.on("data", (chunk) => chunks.push(chunk));
  request.on("end", () => {
    const raw = Buffer.concat(chunks).toString("utf8");
    const type = request.headers["content-type"] || "";
    const read = type.includes("application/x-www-form-urlencoded")
      ? (name) => new URLSearchParams(raw).get(name) || ""
      : (name) => field(raw, name);
    const locale = ["en", "et", "ru"].includes(read("locale")) ? read("locale") : "en";
    const thanks = { en: "/contact/thank-you/", et: "/et/kontakt/tanu/", ru: "/ru/kontakty/spasibo/" };
    const errors = { en: "/contact/error/", et: "/et/kontakt/viga/", ru: "/ru/kontakty/oshibka/" };
    const honeypot = read("company");
    const email = read("email");
    const name = read("name");
    const message = read("message");
    const ok = honeypot !== "" || (name.length >= 2 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && message.length >= 10);
    const wantsJson = (request.headers.accept || "").includes("application/json");
    if (wantsJson) {
      response.writeHead(ok ? 200 : 400, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify({ ok, error: ok ? "" : "invalid" }));
      return;
    }
    response.writeHead(303, { Location: ok ? thanks[locale] : errors[locale] });
    response.end();
  });
}

const server = http.createServer((request, response) => {
  const url = new URL(request.url || "/", "http://127.0.0.1");
  if (request.method === "POST" && url.pathname === "/api/contact.php") {
    previewContact(request, response);
    return;
  }
  const target = redirectTarget(url.pathname);
  if (target) {
    response.writeHead(301, { Location: target });
    response.end();
    return;
  }
  const file = fileFor(url.pathname);
  if (!file) {
    const missing = fs.readFileSync(path.join(dist, "404.html"));
    response.writeHead(404, { "Content-Type": "text/html; charset=utf-8" });
    response.end(missing);
    return;
  }
  const ext = path.extname(file);
  response.writeHead(200, { "Content-Type": types[ext] || "application/octet-stream" });
  response.end(fs.readFileSync(file));
});

server.listen(port, "127.0.0.1", () => {
  console.log(`Compath preview at http://127.0.0.1:${port}/`);
});
