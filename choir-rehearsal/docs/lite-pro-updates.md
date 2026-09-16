# Lite, Pro, and updates

Choir Rehearsal uses the WordPress add-on model (same pattern as WooCommerce, ACF, Gravity Forms).

| Plugin | What it is | Who installs it |
|--------|------------|-----------------|
| **Compath Choir Rehearsal** (`compath-choir-rehearsal.zip`) | Base library. Always Lite until Pro is active. | Everyone |
| **Compath Choir Rehearsal Pro** (`choir-rehearsal-pro.zip`) | Add-on. Unlocks search, unlimited tracks, microphone recording, Play preview, and embedded PDF in the editor. | Paying customers only |

**Never replace Lite with Pro.** Pro is an extra plugin next to Lite. Songs, tracks, and PDFs stay in WordPress; the add-on only turns features on.

---

## Customer: clean Lite install (test or production)

1. Download `compath-choir-rehearsal.zip` (GitHub Release or this package).
2. WordPress → **Plugins → Add New → Upload Plugin** → install → **Activate**.
3. Open **Choir Rehearsal → Settings**.
4. Confirm:
   - **Edition:** `Lite`
   - **Plugin version:** matches the zip
   - **Buy Pro** button is visible
5. Do **not** install `choir-rehearsal-pro.zip` on this site if you want a Lite-only test.

Lite limits: 4 voice tracks per song; no mic recording, search, editor Play, or embedded PDF in the editor.

---

## Customer: Lite → Pro (do not uninstall Lite)

After purchase on [shop.compath.ee/products/choir-rehearsal-pro](https://shop.compath.ee/products/choir-rehearsal-pro/):

1. Keep **Choir Rehearsal** installed and active.
2. Download `choir-rehearsal-pro.zip` from the SureCart customer dashboard (or the email after payment).
3. **Plugins → Add New → Upload Plugin** → upload the Pro zip → **Activate**.
4. Refresh **Choir Rehearsal → Settings**.
   - **Edition** must show `Pro`.
   - **Buy Pro** button disappears.
5. Check a song: Record, Play, and embedded PDF available; more than 4 tracks allowed; search on the public library.

If Pro is uploaded but Lite is missing, WordPress shows: *Choir Rehearsal Pro requires the Choir Rehearsal plugin*.

**Do not:**

- Delete Lite “to make room” for Pro
- Upload a combined “full Pro” that overwrites `choir-rehearsal/`
- Copy Pro files into the Lite folder

Deactivating Pro returns the site to Lite limits. Content is not deleted.

---

## Publisher: ship a new Lite version (GitHub)

WordPress **Check for plugin updates** only sees a newer **GitHub Release** whose assets include **`compath-choir-rehearsal.zip`** (required) and preferably **`update.json`** (API-failure fallback).

### Release checklist

1. Bump `CHOIR_REHEARSAL_VERSION` in `choir-rehearsal/choir-rehearsal.php` (and changelog files).
2. Build a zip whose **root folder is `choir-rehearsal/`** (not `choir-rehearsal-0.4.3/`).
3. Update `choir-rehearsal/update.json` `version` and `download_url` to match the new tag.
4. GitHub → **Releases → Draft a new release**:
   - Tag: `compath-choir-rehearsal-vX.Y.Z` (example: `choir-rehearsal-v0.4.3`)
   - Assets (required / recommended):
     - **`compath-choir-rehearsal.zip`** (required by the updater)
     - **`update.json`** (recommended; used when the GitHub API is rate-limited or unreachable)
   - Do **not** attach `choir-rehearsal-pro.zip` to a public release
5. Publish the release (not draft, not prerelease).

CLI example after creating the release:

```bash
gh release upload compath-choir-rehearsal-vX.Y.Z choir-rehearsal.zip choir-rehearsal/update.json --clobber
```

### How to test auto-update

Use a **clean** WordPress site:

1. Install an **older** Lite (for example the previous GitHub tag).
2. Publish the new GitHub release as above.
3. In WP: **Choir Rehearsal → Settings → Check for plugin updates**.
4. Settings should show a notice (update available / up to date / failed). If available, **Plugins** shows an update to `X.Y.Z`.

If you install the *same* version that you just published, Check for plugin updates correctly reports up to date.

---

## Publisher: ship a new Pro version (SureCart only)

1. Bump version in `choir-rehearsal-pro/choir-rehearsal-pro.php`.
2. Zip folder `choir-rehearsal-pro/` → `choir-rehearsal-pro.zip`.
3. SureCart product **Choir Rehearsal Pro** → replace **Current release** download.
4. Do not put Pro on public GitHub Releases.

Pro customers update Pro by downloading the new zip from their SureCart account until a licensed updater is added.

---

## What each zip contains

| File | Folder inside zip | Channel |
|------|-------------------|---------|
| `compath-choir-rehearsal.zip` | `choir-rehearsal/` | GitHub / wordpress.org |
| `choir-rehearsal-pro.zip` | `choir-rehearsal-pro/` | SureCart customer download |

A Pro buyer who does not have Lite yet: install Lite first, then Pro. SureCart can attach both zips to the product; the install order is still Lite, then Pro.
