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

## Nightly reset

Hosting cron (Europe/Tallinn), `0 3 * * *`:

```bash
curl -fsS 'https://demo.rehearsal.compath.ee/?choir_demo_reset=1&key=SECRET'
```

Or: `wp choir-rehearsal demo-reset`

Full UI instructions: **Songs → Demo data** in wp-admin.
