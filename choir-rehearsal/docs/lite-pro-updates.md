# Lite, Pro, and updates

Choir Rehearsal uses the WordPress add-on model (same pattern as WooCommerce, ACF, Gravity Forms).

| Plugin | What it is | Who installs it |
|--------|------------|-----------------|
| **Choir Rehearsal** (`choir-rehearsal.zip`) | Base library. Always Lite until Pro is active. | Everyone |
| **Choir Rehearsal Pro** (`choir-rehearsal-pro.zip`) | Add-on. Unlocks search, unlimited tracks, microphone recording. | Paying customers only |

**Never replace Lite with Pro.** Pro is an extra plugin next to Lite. Songs, tracks, and PDFs stay in WordPress; the add-on only turns features on.

---

## Customer: clean Lite install (test or production)

1. Download `choir-rehearsal.zip` (GitHub Release or this package).
2. WordPress → **Plugins → Add New → Upload Plugin** → install → **Activate**.
3. Open **Choir Rehearsal → Settings**.
4. Confirm:
   - **Edition:** `Lite`
   - **Plugin version:** matches the zip
   - **Buy Pro** button is visible
5. Do **not** install `choir-rehearsal-pro.zip` on this site if you want a Lite-only test.

Lite limits: 4 voice tracks per song, no microphone recording, no song search.

---

## Customer: Lite → Pro (do not uninstall Lite)

After purchase on [shop.compath.ee](https://shop.compath.ee/):

1. Keep **Choir Rehearsal** installed and active.
2. Download `choir-rehearsal-pro.zip` from the SureCart customer dashboard (or the email after payment).
3. **Plugins → Add New → Upload Plugin** → upload the Pro zip → **Activate**.
4. Refresh **Choir Rehearsal → Settings**.
   - **Edition** must show `Pro`.
   - **Buy Pro** button disappears.
5. Check a song: Record button visible, more than 4 tracks allowed, search on the public library.

If Pro is uploaded but Lite is missing, WordPress shows: *Choir Rehearsal Pro requires the Choir Rehearsal plugin*.

**Do not:**

- Delete Lite “to make room” for Pro
- Upload a combined “full Pro” that overwrites `choir-rehearsal/`
- Copy Pro files into the Lite folder

Deactivating Pro returns the site to Lite limits. Content is not deleted.

---

## Publisher: ship a new Lite version (GitHub)

WordPress **Check for updates** only sees a newer **GitHub Release** whose asset is named exactly `choir-rehearsal.zip`.

Current GitHub latest is `choir-rehearsal-v0.3.11`. Until a newer tagged release exists, the button will find nothing. That is expected.

### Release checklist

1. Bump `CHOIR_REHEARSAL_VERSION` in `choir-rehearsal/choir-rehearsal.php` (and changelog files).
2. Build a zip whose **root folder is `choir-rehearsal/`** (not `choir-rehearsal-0.4.3/`).
3. GitHub → **Releases → Draft a new release**:
   - Tag: `choir-rehearsal-vX.Y.Z` (example: `choir-rehearsal-v0.4.3`)
   - Asset filename: **`choir-rehearsal.zip`** (required by the updater)
   - Do **not** attach `choir-rehearsal-pro.zip` to a public release
4. Publish the release (not draft, not prerelease).
5. Update `choir-rehearsal/update.json` `version` and `download_url` to the same tag, then merge to `main` (fallback if GitHub API fails).

### How to test auto-update

Use a **clean** WordPress site:

1. Install an **older** Lite (for example current GitHub `0.3.11`, or this package if it is older than the new tag).
2. Publish the new GitHub release as above.
3. In WP: **Choir Rehearsal → Settings → Check for updates now**.
4. **Plugins** should show an update to `X.Y.Z`. Update it. Songs must remain.

If you install the *same* version that you just published, Check for updates correctly shows nothing.

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
| `choir-rehearsal.zip` | `choir-rehearsal/` | GitHub / wordpress.org |
| `choir-rehearsal-pro.zip` | `choir-rehearsal-pro/` | SureCart customer download |

A Pro buyer who does not have Lite yet: install Lite first, then Pro. SureCart can attach both zips to the product; the install order is still Lite, then Pro.
