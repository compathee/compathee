# Building the Demo package

The Demo sandbox (`demo.rehearsal.compath.ee`) uses the same plugin tree with:

1. `includes/distribution-demo.php` present (sets `CHOIR_REHEARSAL_DISTRIBUTION=demo`)
2. Seed assets under `assets/demo/` (`demo-voice-track.mp3`, `choir-warm-up.pdf`)
3. Secret in `wp-config.php`:

```php
define( 'CHOIR_REHEARSAL_DEMO_RESET_KEY', 'long-random-string' );
```

Optional local override (never ship to production Demo):

```php
define( 'CHOIR_REHEARSAL_DEMO_ALLOW_HOST', true ); // allow non-compath.ee hosts
```

## Lite / Pro GitHub zips

**Exclude** `includes/distribution-demo.php` from Lite and Pro release zips so they stay `github` / Pro-licensed builds.

## WordPress.org

Use `includes/distribution-wporg.php` instead (unchanged).

## Shared demo accounts

The must-use plugin in [`mu-plugins/`](../../mu-plugins/README.md) locks `demosinger` and `demoleader` (password, email, profile, role, deletion) and restores a baseline of songs, tracks, media, and those two accounts.

Install these files on the demo site (FTP root `/demo.rehearsal.compath.ee`, `wp-content/mu-plugins/` already exists). They are not part of the Lite or Pro zip.

| Repo file | Server path |
| --- | --- |
| `mu-plugins/compath-rehearsal-demo-guard.php` | `/demo.rehearsal.compath.ee/wp-content/mu-plugins/compath-rehearsal-demo-guard.php` |
| `mu-plugins/compath-rehearsal-profanity.php` | `/demo.rehearsal.compath.ee/wp-content/mu-plugins/compath-rehearsal-profanity.php` |
| `mu-plugins/compath-rehearsal-profanity/class-profanity.php` | `/demo.rehearsal.compath.ee/wp-content/mu-plugins/compath-rehearsal-profanity/class-profanity.php` |

The profanity must-use plugin rejects obscene song titles, notes, part names, and upload file names for every user who cannot manage site settings. The same class ships in the plugin (`includes/class-profanity.php`) so a later release can turn it on with the setting “Block profanity in song titles, notes, part names, and file names” (on by default for the Demo build, off until enabled for Lite). Wordlists extend through the `choir_rehearsal_profanity_lists` filter. There is no playlist post type; part names are the `choir_voice_type` taxonomy.

## Nightly reset

Save a baseline once, then schedule a restore. Hosting cron (Europe/Tallinn), `0 3 * * *`:

```bash
wp compath-demo baseline-restore --path=/demo.rehearsal.compath.ee
```

Or, without WP-CLI:

```bash
WP_ROOT=/demo.rehearsal.compath.ee php /path/to/scripts/compath-demo-restore.php restore
```

The older library reseed still works and, when the must-use plugin is active, also resets the demo accounts:

```bash
curl -fsS 'https://demo.rehearsal.compath.ee/?choir_demo_reset=1&key=SECRET'
```

Or: `wp choir-rehearsal demo-reset`

If `compath-demo-baseline/baseline.json` exists, that command restores the snapshot instead of generating Demo Song 01–25. It never updates users outside the guarded login list.

Full UI instructions: **Songs → Demo data** in wp-admin. Account lock and cron details: `mu-plugins/README.md`.

## Download

Draft GitHub Release (Demo zip):

https://github.com/compathee/compathee/releases/tag/compath-choir-rehearsal-demo-v0.4.63

Asset: **`compath-choir-rehearsal-demo.zip`**

Build locally:

```bash
./scripts/build-demo-zip.sh
# → dist/compath-choir-rehearsal-demo.zip
```
