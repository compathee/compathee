# WordPress.org distribution

## Build the catalog zip

From the repository root:

```bash
./scripts/build-wporg-zip.sh
```

Output: `dist/choir-rehearsal-wporg-X.Y.Z.zip`

That zip:

* Includes `includes/distribution-wporg.php` (sets `CHOIR_REHEARSAL_DISTRIBUTION=wporg`)
* Disables the GitHub self-updater and related Settings fields
* Bundles PDF.js locally under `assets/vendor/pdfjs/`
* Excludes `update.json`, tests, and internal shop/deploy docs

## GitHub zip (optional self-updater)

Continue shipping `choir-rehearsal.zip` from GitHub Releases **without** `distribution-wporg.php` so Settings can still check GitHub for updates.

## Reviewer notes

* Pro upgrade links open https://shop.compath.ee/ (documented in readme “External services”)
* No updates are served from GitHub in the WordPress.org package
