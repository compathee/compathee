# WordPress.org distribution

## Build the catalog zip

From the repository root:

```bash
./scripts/build-wporg-zip.sh
```

Output: `dist/choir-rehearsal-wporg-X.Y.Z.zip`

That zip:

* Includes `includes/distribution-wporg.php` (sets `CHOIR_REHEARSAL_DISTRIBUTION=wporg`)
* **Omits** `includes/class-updater.php` entirely (Plugin Check: `plugin_updater_detected` / update modification)
* Disables GitHub updater Settings UI via distribution channel
* Bundles PDF.js locally under `assets/vendor/pdfjs/`
* Excludes `update.json`, tests, and internal shop/deploy docs
* Requires `readme.txt` → `Tested up to: 7.1` (current WordPress)

## GitHub zip (optional self-updater)

Continue shipping `choir-rehearsal.zip` from GitHub Releases **without** `distribution-wporg.php` and **with** `class-updater.php` so Settings can still check GitHub for updates.

## Reviewer notes

* Pro upgrade links open https://shop.compath.ee/ (documented in readme “External services”)
* No custom update hooks ship in the WordPress.org package — updates only via wordpress.org
* Re-run [Plugin Check](https://wordpress.org/plugins/plugin-check/) on the wporg zip before upload
