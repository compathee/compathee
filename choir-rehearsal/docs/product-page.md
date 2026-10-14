# Compath Choir Rehearsal — rehearsal library for choirs

**WordPress plugin · version 0.4.61** · [rehearsal.compath.ee](https://rehearsal.compath.ee) · [WordPress.org](https://wordpress.org/plugins/compath-choir-rehearsal/)

A private rehearsal library: songs, voice tracks, optional YouTube embeds, PDF scores, and mobile-friendly listening.

- [Get Lite on WordPress.org](https://wordpress.org/plugins/compath-choir-rehearsal/)
- [Download latest release (GitHub)](https://github.com/compathee/compathee/releases/latest)
- [Full HTML page](product-page.html) — for publishing on the website

---

## Overview

Choir Rehearsal helps choir members learn new pieces by voice part. A Voice Leader uploads PDF scores and audio for each voice — or links an official YouTube video per track. Singers visit `/rehearsal/`, pick a song, and listen to their part.

**Features:**

- Song list on your choir website
- PDF viewer with page navigation
- Voice tracks: bass, tenor, alto, soprano, and more
- Per-track Audio / YouTube source (official embed; video stays visible)
- Microphone recording in the admin (Pro)
- Sticky player at the bottom of the screen
- Login-only access (optional)
- Roles: Singer (listen), Voice Leader (manage songs), Administrator (Settings) — role names stay in English
- Updates via WordPress.org (Lite) or GitHub

**Requirements:** WordPress 6.4+, PHP 8.0+

**Owner and developer:** Compath OÜ, Tallinn, Estonia  
First customer — [Cappella Veneta](https://veneta.ee)

---

## Order & subscription

The plugin is licensed under GPL. Install Lite yourself for free from WordPress.org or GitHub, or purchase Pro support from us.

### Lite — free

- WordPress.org plugin directory or GitHub Releases
- Up to 4 voice tracks per song
- PDF scores, sticky player, optional YouTube embeds
- Self-service installation

### Pro — subscription — €49 / year

- 1 choir website
- Unlimited tracks, microphone recording, song search, Play preview, PDF view while recording
- Pro add-on installed beside Lite
- Priority email support

### Done-for-you setup — from €120

- Setup on your WordPress site
- `/rehearsal/` page, Singer / Voice Leader roles, access
- Voice Leader training
- First year of Pro included

### How to order

1. Open [shop.compath.ee/products/choir-rehearsal-pro](https://shop.compath.ee/products/choir-rehearsal-pro/) or email **order@compath.ee**.
2. After payment — download `choir-rehearsal-pro.zip` from your SureCart account.
3. Keep Lite installed → **Plugins → Upload** Pro add-on → Activate.

---

## Installation

1. Install Lite from [WordPress.org](https://wordpress.org/plugins/compath-choir-rehearsal/) or upload the zip from [GitHub Releases](https://github.com/compathee/compathee/releases).
2. **Plugins → Add New** — activate Compath Choir Rehearsal.
3. The plugin creates `/rehearsal/` with shortcode `[choir_rehearsal]` and WordPress roles **Singer** and **Voice Leader** (English names, not translated).
4. **Choir Rehearsal → Add Song** — title, PDF, tracks (Audio or YouTube; needs Voice Leader or Administrator).
5. Assign users: **Singer** to listen; **Voice Leader** to manage songs. **Settings** stay Administrator-only.

### Updates

- **Lite:** WordPress.org directory updates (Plugins → Updates). GitHub Releases remain an alternative.
- **Pro add-on:** new zip from SureCart customer dashboard (until licensed auto-updates).

### Roles

| Role | Access |
|------|--------|
| Singer | Listen, view PDF, full private library |
| Voice Leader | Manage songs in admin + listen; Audio or YouTube per track |
| Administrator | Everything, including plugin Settings |

---

## Changelog

### 0.4.61
- Per-track Audio / YouTube source switch in the song editor
- YouTube tracks open the official embed on the song page (video stays visible)
- Song list shows a YouTube icon next to PDF and public badges

See [product-data.json](product-data.json) for the full changelog history.

---

**Contact:** [rehearsal.compath.ee](https://rehearsal.compath.ee) · [compath.ee](https://compath.ee) · [GitHub](https://github.com/compathee/compathee) · order@compath.ee

© Compath OÜ, Tallinn, Estonia

Licensed under GPL-2.0-or-later
