# compath.ee

Static company site for Compath OÜ. Pages are generated from `content/en.json`, `content/et.json` and `content/ru.json`.

```bash
node compath-site/build.mjs
node compath-site/build.mjs --preview
node compath-site/preview.mjs
php compath-site/api/contact-test.php
```

`build.mjs --preview` writes a second copy to `dist-preview/` for `https://compath.ee/preview-2026/` (`/public_html/preview-2026/`). Links, assets and language URLs use that base path. Canonical, hreflang, `og:url` and JSON-LD stay on the future production URLs (`https://compath.ee/…`), so a crawled preview URL does not become the official address. Every preview page has `noindex, nofollow`. The folder `.htaccess` sends `X-Robots-Tag: noindex, nofollow` and does not use rewrite rules, so it cannot change the Sitebuilder site at the web root. Preview has no `robots.txt`, `sitemap.xml` or `llms.txt`. The contact form is omitted, with a note, so the preview does not send mail. A Preview badge sits in the header. The upload list is `PREVIEW-FTP-PATHS.txt`, separate from `FTP-PATHS.txt`.

Preview serves `dist/` at http://127.0.0.1:4173/ and applies the old Sitebuilder redirects. The contact form posts to `/api/contact.php`, which is not part of the static upload. See `DEPLOY.md` for the hosting cutover and rollback.

## Language

English stays at `/`, matching the current site. Estonian is `/et/`. Russian is `/ru/`. `x-default` is Estonian, because the market is Estonia: a visitor whose language matches none of the three should get Estonian. Crawlers are not redirected. On a first visit to the English home, the browser language is stored as `compath-lang` and, for Estonian or Russian, the page opens that version. A direct link to `/et/` or `/ru/` is left as it is. Crawlers are not redirected. Later visits follow the saved choice. The switcher uses the labels ENG, EST and RUS.

`rehearsal.compath.ee/et/` and `/ru/` are not published (they 404). Product links use `?lang=et` and `?lang=ru`, plus the bare `https://rehearsal.compath.ee/` on every language.

## Services

The public site lists three services, then Choir Rehearsal as the company’s own plugin:

1. IT support and maintenance
2. Plugin and AI agent development
3. Consulting on launching on Amazon

Translation and video are not offered. The address `support@compath.ee` is the form recipient in `api/config.example.php`. It is not published as a `mailto:` link.

Edit copy in the JSON files and run the build again. The build checks JSON-LD, hreflang, redirects and that the public files do not contain a harvestable mailbox.
