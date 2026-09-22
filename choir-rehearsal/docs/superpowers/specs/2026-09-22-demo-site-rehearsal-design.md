# Demo site: demo.rehearsal.compath.ee

Date: 2026-09-22  
Status: draft (awaiting product review)  
Scope: separate **Demo** distribution of Compath Choir Rehearsal + public sandbox site

## Problem

Prospects need a live sandbox to click through songs, tracks, PDF, and Pro-like tools before buying. A static product page is not enough. A shared WordPress install must reset nightly so vandalism and leftover uploads do not accumulate.

## Goal

Run **https://demo.rehearsal.compath.ee** as a WordPress site with a **Demo** plugin build that unlocks Pro capabilities under hard caps, shared Voice Leader login on the page, seed library after every nightly wipe, and an admin **Demo data** screen with reset setup instructions plus a 24-hour event log.

## Non-goals

- SureCart licensing on the demo site  
- Shipping Demo limits into WordPress.org Lite or commercial Pro zips  
- Relying on visitor-triggered WP-Cron alone for the nightly wipe  
- MuseScore/commercial scores — seed PDFs are LilyPond public-domain exercises  

## URL and hosting

| Item | Value |
|------|--------|
| Public URL | `https://demo.rehearsal.compath.ee` |
| Stack | Separate WP + DB (same class of host as `shop.compath.ee`) |
| Docroot | e.g. `/domains/demo.rehearsal.compath.ee/public_html/` |
| SSL | Let's Encrypt |
| Related | Product marketing stays on `rehearsal.compath.ee` (static); shop on `shop.compath.ee` |

DNS: A/CNAME for `demo` under `rehearsal.compath.ee` (or equivalent panel subdomain).

## Distribution: Demo edition

Separate package from Lite and Pro (marker analogous to wporg):

```php
define( 'CHOIR_REHEARSAL_DISTRIBUTION', 'demo' );
```

| Concern | Demo behavior |
|---------|----------------|
| Features | Same as Pro (search, mic record, editor Play, embedded PDF, YouTube tracks, etc.) |
| License | No SureCart; features on via distribution flag |
| Host lock | Install/run **only** on `compath.ee` and `*.compath.ee` (see below) |
| Song cap | **50** songs site-wide |
| Tracks per song | **10** |
| Score PDF upload | **≤ 5 MB** |
| Admin UI | Extra submenu **Demo data** (not mixed into general Settings) |
| i18n | Full WordPress gettext readiness (any locale) |

Over-limit: disable Add song / Add track / upload with a clear notice. Oversized PDF rejected on upload.

### Host lock (Demo only)

Hard gate so the Demo package cannot be used as a free Pro substitute on third-party sites.

- Allowed hosts: exact `compath.ee` **or** any subdomain whose registrable suffix is `compath.ee` (e.g. `demo.rehearsal.compath.ee`, `shop.compath.ee`, `rehearsal.compath.ee`).
- Disallowed: `localhost`, IP literals, `*.local`, any other TLD — unless a documented override constant is set for engineering (e.g. `CHOIR_REHEARSAL_DEMO_ALLOW_HOST` for staging); default **off** in shipped Demo zips.
- Enforce on: plugin activation (fail activation + admin error) and early `plugins_loaded` (if site URL changed later → deactivate Demo soft-fail / show fatal notice and do not unlock Pro features).
- Compare against `home_url()` / `site_url()` host (normalized, lowercase, no port).

### Internationalization (Demo and shared strings)

Lay translation groundwork from day one (not Russian-only UI):

- All user-facing Demo strings via `__() / esc_html_e()` with text domain `compath-choir-rehearsal` (or dedicated `compath-choir-rehearsal-demo` if the Demo zip is a separate plugin header — prefer **one** domain shared with Lite so translators reuse catalogs).
- Ship / generate `.pot`; load translations the WordPress.org-compatible way (WP 6.5+ language packs / `languages/*.l10n.php` as already used for Lite).
- Demo data page, banner, limit messages, reset log messages, cron instructions — all translatable.
- Seed song titles may stay English codes (`Demo Song 01`) for reset stability; optional localized labels later via filter, not required for v1.
- Do **not** hardcode ET/RU-only copy in PHP for Demo chrome.

## Access model

- Shared **Voice Leader** account; credentials shown on the public rehearsal/demo landing (e.g. user `demo`).  
- Role: Voice Leader (manage songs); **not** full Administrator.  
- Guests cannot reach plugin install / theme / Settings that change the site; Administrator remains operator-only.  
- Persistent banner: demo site, nightly reset time, caps (50 / 10 / 5 MB).

## Default library (seed)

Reuse existing Settings machinery in `Choir_Rehearsal_Demo_Data`:

- `SONGS_PER_LOAD = 25` → **Demo Song 01…25**  
- `demo_voices()` → **4** tracks each (Soprano 1, Alto 1, Tenor 1, Bass 1)  
- Shared bundled demo MP3 attachment  

**New for Demo / reset path:** attach one seed **LilyPond** PDF (real engraving, e.g. Choir Warm-up) to every seed song via the existing score PDF meta. Prefer **one** Media attachment referenced by all 25 songs.

Nightly and “Load default library” both produce this seed state (25 × 4 + PDF). Operators may then add more until 50 / 10 caps.

## Nightly reset

### Logic (in plugin)

Class e.g. `Choir_Rehearsal_Demo_Reset` in the Demo build:

1. Snapshot counts for the log (songs / tracks / media to delete).  
2. Delete all songs, tracks, and non-seed media (same spirit as `delete_all_songs`).  
3. Load default library (25 × 4 + seed PDF).  
4. Ensure shared Voice Leader user/password still matches the published demo credentials.  
5. Append success/failure events to the 24h log.

Also expose:

- **WP-CLI:** `wp choir-rehearsal demo-reset`  
- **HTTP:** authenticated by secret key only (see below), same `run()` as CLI  

### Trigger (hosting cron) — primary

Do **not** depend on front-end WP-Cron traffic.

**Primary:** hosting panel Cron Job, Europe/Tallinn, `0 3 * * *`:

```bash
curl -fsS "https://demo.rehearsal.compath.ee/?choir_demo_reset=1&key=SECRET"
```

Secret from `wp-config.php` (never committed):

```php
define( 'CHOIR_REHEARSAL_DEMO_RESET_KEY', 'long-random-string' );
```

**Optional:** WP-CLI cron line if the host provides `wp`.

**Optional in git:** `scripts/demo-reset-cron.sh` as copy-paste documentation only; not required on the server.

| Piece | Location |
|-------|----------|
| Reset PHP | Demo plugin in git → deployed with the site |
| `CHOIR_REHEARSAL_DEMO_RESET_KEY` | Server `wp-config.php` |
| Schedule 03:00 EET | Hosting Cron Jobs UI |
| Sample curl / WP-CLI text | **Demo data** admin page (+ optional script in repo) |

## Admin: Demo data page

Register only when `CHOIR_REHEARSAL_DISTRIBUTION === 'demo'`.

Menu under Songs CPT, alongside Settings / License:

- Slug: `choir-rehearsal-demo-data`  
- Title: **Demo data**  
- Cap: `manage_options` (Administrator)

### Sections

1. **Limits** — short summary (50 songs, 10 tracks/song, PDF ≤ 5 MB).  
2. **Nightly reset setup** — step-by-step for the host cron; site URL already filled; curl command with placeholder or masked key hint; Copy button; pointer to `wp-config.php` constant; WP-CLI alternative; **Run reset now** (nonce + `manage_options`) for manual test.  
3. **Event log (24 hours)** — table of recent demo events (see below).  
4. **Library actions** — **Load default library** and **Delete all** (moved here from general Settings for the Demo build so Settings stays product-agnostic).

Lite/Pro packages: do not register this page. Existing Settings demo buttons may remain for non-Demo builds as today.

## Event log (24 h)

- Option key e.g. `choir_rehearsal_demo_event_log` (array of `{ ts, code, message, meta }`).  
- On write: drop entries older than 24 hours.  
- Timezone display: Europe/Tallinn (EET/EEST).

Example rows:

| Time | Message |
|------|---------|
| 03:00 | Cron reset completed — default library installed (25 songs, 100 tracks, PDF attached) |
| 03:00 | Deleted before reset — N songs, M tracks, K media |
| 14:22 | Added — 1 song, 4 tracks |
| 18:05 | Deleted — 2 songs, 8 tracks |
| 18:40 | Blocked — song limit (50) reached |
| 19:01 | Blocked — PDF over 5 MB |
| (manual) | Run reset now — default library installed … |

Empty state: “No demo events in the last 24 hours.”

Writers: nightly/HTTP/CLI reset, Run reset now, load/delete default library, song/track create/delete when relevant, limit blocks.

## Seed PDF assets

- Generate with **LilyPond** (real SATB engraving).  
- Bundle under e.g. `assets/demo/*.pdf` in the Demo package (and/or generate in build script).  
- First examples already prototyped: Choir Warm-up, Amen Cadence (public-domain exercises).  
- One primary PDF is enough for v1 seed; extra scores optional later.

## Security

- Host lock to `*.compath.ee` (activation + runtime).  
- Reset HTTP endpoint: constant-time key compare; no cookie auth required (cron has no WP session); refuse empty key.  
- Rate-limit or one-flight lock during reset.  
- Voice Leader cannot change reset key or install plugins.  
- Upload allowlist: audio + PDF only; enforce 5 MB on PDF in Demo.  

## Rollout sketch (ops, not all code)

1. Create subdomain + WP + SSL.  
2. Install Demo zip; set `CHOIR_REHEARSAL_DEMO_RESET_KEY`.  
3. Create shared Voice Leader; put credentials on landing/banner.  
4. Run reset once from Demo data → verify 25×4 + PDF.  
5. Add hosting cron for 03:00; confirm log line next day.  
6. Link from `rehearsal.compath.ee` / ads to the demo URL.

## Open decisions (resolved in discussion)

| Topic | Decision |
|-------|----------|
| Host | `demo.rehearsal.compath.ee` |
| Access | Shared Voice Leader, credentials on page |
| Caps | 50 songs, 10 tracks/song, PDF 5 MB |
| Seed | Existing 25×4 demo load + LilyPond PDF |
| Cron | Host curl → secret URL at 03:00 EET |
| Admin UI | Separate **Demo data** page (not Settings) |
| Log | Last 24 h on Demo data page |
| Host lock | Only `compath.ee` / `*.compath.ee` |
| i18n | gettext + .pot / language packs from the start |

## Related: song slug transliteration (all editions)

Existing `Choir_Rehearsal_Slugs::transliterate()` pipeline (Lite/Pro/Demo):

1. Explicit Cyrillic map (Russian + Ukrainian/Belarusian extras).  
2. WordPress `remove_accents()` — Latin-based languages (French, German, Spanish, Estonian õ/ä/ö/ü, etc.).  
3. `iconv(…, 'ASCII//TRANSLIT')` when available — broader fallback.

**Product answer:** do **not** invent a separate procedure per language. Extend the **same** pipeline:

- Most European languages → already covered by steps 2–3; no new code.  
- Extra Cyrillic letters (e.g. Serbian ђ/ћ/џ) → add rows to the same map.  
- Greek / Georgian / Armenian → optional map additions if iconv quality is poor.  
- CJK / Arabic / Hebrew → iconv often yields empty or ugly slugs; add targeted maps or a documented “fallback to `song-N`” only if those locales become a real customer need.

No per-language strategy objects for v1.

## Out of scope for v1

- Hosting auto-provisioning from CI  
- Soft-delete / recycle bin for demo vandalism (nightly wipe is enough)  
- High-quality CJK/Arabic slug dictionaries (extend later if needed)  
