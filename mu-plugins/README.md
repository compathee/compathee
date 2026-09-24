# Shared demo account guard

Must-use plugin for the public Choir Rehearsal demo at `https://demo.rehearsal.compath.ee`. It locks the shared logins, rejects obscene text on songs and uploads, and restores a known library each night.

This extends the Demo distribution already in the plugin (`Choir_Rehearsal_Demo_Reset`, song/PDF caps, host lock). It does not replace that package. When a baseline file exists, `wp choir-rehearsal demo-reset` and the secret URL restore that baseline instead of generating a fresh seed library.

The demo WordPress root on the owner’s FTP is `/demo.rehearsal.compath.ee` (the directory that contains `wp-load.php`). `wp-content/mu-plugins/` already exists there.

## Files to install

| Repo file | Server path |
| --- | --- |
| `mu-plugins/compath-rehearsal-demo-guard.php` | `/demo.rehearsal.compath.ee/wp-content/mu-plugins/compath-rehearsal-demo-guard.php` |
| `mu-plugins/compath-rehearsal-profanity.php` | `/demo.rehearsal.compath.ee/wp-content/mu-plugins/compath-rehearsal-profanity.php` |
| `mu-plugins/compath-rehearsal-profanity/class-profanity.php` | `/demo.rehearsal.compath.ee/wp-content/mu-plugins/compath-rehearsal-profanity/class-profanity.php` |
| `mu-plugins/compath-rehearsal-profanity/index.php` | `/demo.rehearsal.compath.ee/wp-content/mu-plugins/compath-rehearsal-profanity/index.php` |
| `scripts/compath-demo-restore.php` | Outside the web root when the account can write there, for example `/compath-demo-restore.php` |

WordPress loads every PHP file placed directly in `mu-plugins/`. The class file stays in a subdirectory so it is loaded only by `compath-rehearsal-profanity.php`. Do not place the restore script in `wp-content/mu-plugins`.

These files are not part of the Demo zip. The assistant uploads them by FTP. This change does not deploy them.

## What the guard does

For `demosinger` and `demoleader` (override with `COMPATH_DEMO_GUARDED_LOGINS`):

- Hides password, email, display name, nickname, and the other profile fields.
- Rejects those changes from profile save, `wp_update_user`, user meta, the REST API (`/wp/v2/users/me` and `/wp/v2/users/<id>`, including application passwords), admin-ajax paths that call the same user APIs, and XML-RPC `wp.editProfile` / `wp.setOptions`.
- Disables password reset, including a reset key that was already emailed, and disables application passwords.
- Sends `/wp-admin/` (dashboard, profile, plugins, users, settings) back to `/rehearsal/`.
- Still allows the Voice Leader song screens (`choir_song` / `choir_track`), `admin-ajax.php`, and the uploader, so Add song and Manage library keep working. Singers are sent back to the rehearsal page from every admin screen.
- Hides the admin bar.
- Stops a demo account from deleting itself or changing its own role. An administrator can still do both.
- Shows a short notice on the front end and on the login screen. English is the source string. Estonian and Russian are built in and follow the WordPress locale (`et`, `ru`). Role names such as Singer and Voice Leader are not passed through translation.

Optional `wp-config.php` constants:

```php
define( 'COMPATH_DEMO_GUARDED_LOGINS', 'demosinger,demoleader' );
define( 'COMPATH_DEMO_REHEARSAL_PATH', '/rehearsal/' );
define( 'COMPATH_DEMO_MAX_UPLOAD_BYTES', 8 * 1024 * 1024 );
define( 'COMPATH_DEMO_BASELINE_DIR', '/compath-demo-baseline' );
// Only if the host has no real cron. Prefer the system cron below.
define( 'COMPATH_DEMO_GUARD_USE_WP_CRON', false );
```

Account emails and the published passwords live in `Compath_Rehearsal_Demo_Guard::default_accounts()`. Override them with `COMPATH_DEMO_ACCOUNT_BASELINE` (array keyed by login: `user_email`, `user_pass`, `display_name`, `nickname`, `first_name`, `last_name`, `role`). Allowed roles are `choir_singer` and `choir_voice_leader` only.

## Profanity filter

`compath-rehearsal-profanity.php` checks text when a song, track, or attachment is saved, when a `choir_voice_type` term (part name) is created, and when a file is uploaded. There is no playlist post type.

On the demo it applies to every user who cannot manage site settings, including both demo accounts. A user who can manage site settings can still save any title. The check runs in `wp_insert_post_data` (before and after the slug filter), on the core REST insert hooks for `choir_song`, `choir_track`, and attachments, on `pre_insert_term`, and on `wp_handle_upload_prefilter`. A bad title or note is reverted to the previous value on update, or cleared on create, and the other fields in that save are kept. The notice is “Please use appropriate language in the song title.” (and the matching line for the description, part name, file name, or category), in English, Estonian, and Russian.

Matching folds case, `ё`/`ë`, Latin/Cyrillic look-alikes (`a/а`, `e/е`, `o/о`, `p/р`, `c/с`, `x/х`, `y/у`, `k/к`), and leetspeak (`0→o`, `3→e` or `з`, `@→a`, `1→i` or `l`, `$→s`). Separators inside a word are removed (`х.у.й`, `f*u*c*k`), and repeated letters are collapsed. Russian roots are matched with an allowlist so words such as «истребитель», «рубля», «оскорблять», «сукно», and «персик» still save. English whole-word checks leave Scunthorpe, Dickinson, Dickens, and “Moby Dick” alone. Estonian “litsents”, “raiskama”, and “perseverance” stay allowed. Liturgical and folk titles used in the tests include Hallelujah, Ave Maria, Kyrie eleison, Panis angelicus, Õhtu laul, and Mu isamaa.

The same class lives at `choir-rehearsal/includes/class-profanity.php` and is what a later plugin release should load. It belongs in the plugin: song save, REST, and uploads already pass through core hooks, so the plugin side is the class, one settings checkbox, and those hooks. The Demo build defaults the checkbox on. Lite and GitHub builds default it off until someone enables “Block profanity in song titles, notes, part names, and file names”. Wordlists extend with the `choir_rehearsal_profanity_lists` filter (`block_substrings`, `block_tokens`, `block_token_patterns`, `allow_tokens`, `allow_stems`, `allow_phrases`). `choir_rehearsal_block_profanity` can force the check. On this demo the must-use plugin keeps the check on for non-managers even if that checkbox is later turned off. The live site does not get the plugin update in this change; the must-use files are what start enforcing it.

## What it deliberately does not block

Voice Leader can add, edit, and trash songs and tracks. That is the demo of managing the library. Those changes are undone by the nightly restore.

The guard does block a few things that would outlive a useful demo session:

- Uploads larger than 8 MB, so one visitor cannot fill the disk. Normal voice tracks and the demo PDF cap (5 MB in the Demo build) still fit.
- Deleting pages, other people’s media, or the administrator account.
- Demo-user uploads that are not in the baseline (removed on restore). Media attached to ordinary pages is left alone.
- Obscene titles, notes, part names, and file names, as described above.

## Baseline and cron

The baseline is a JSON snapshot plus copies of the media files. It includes choir songs, tracks, the attachments they use, `choir_voice_type` terms, `choir_rehearsal_*` options (not the reset key, license, or token options), and the `users` / `usermeta` rows for the demo logins only. It is written outside the web root because it contains password hashes.

Default directory: the parent of the WordPress root, `compath-demo-baseline/`. For this site that is `/compath-demo-baseline`, next to `/demo.rehearsal.compath.ee`, not inside it.

If the cron user cannot create that parent directory (the FTP login only sees `/demo.rehearsal.compath.ee`), set `COMPATH_DEMO_BASELINE_DIR` to a path that user can write. A fallback inside the site is `/demo.rehearsal.compath.ee/wp-content/compath-demo-baseline`. The guard writes `index.php` and a deny-all `.htaccess` into the baseline directory. Nginx does not read `.htaccess`, so use the parent-of-docroot path whenever the cron user can create it.

### 1. Confirm the accounts, then save

Do this while the two demo users still have the published passwords and the library looks right. Saving after someone has changed a song will make that change the new baseline.

From the WordPress directory:

```bash
wp compath-demo baseline-save --path=/demo.rehearsal.compath.ee
```

Without WP-CLI, with the script placed outside the web root:

```bash
WP_ROOT=/demo.rehearsal.compath.ee \
  php /compath-demo-restore.php save
```

Check the JSON result. It should mention both demo accounts. To refresh the baseline later, run the same save command again.

### 2. Nightly restore

Hosting cron, timezone Europe/Tallinn, every day at 03:00:

```cron
CRON_TZ=Europe/Tallinn
0 3 * * * wp compath-demo baseline-restore --path=/demo.rehearsal.compath.ee >> /compath-demo-restore.log 2>&1
```

Or:

```cron
CRON_TZ=Europe/Tallinn
0 3 * * * WP_ROOT=/demo.rehearsal.compath.ee php /compath-demo-restore.php restore >> /compath-demo-restore.log 2>&1
```

If the panel has no `CRON_TZ`, set the panel timezone to Europe/Tallinn and keep `0 3 * * *`.

Restore rewrites only rows whose `user_login` is `demosinger` or `demoleader`. The `WHERE` clause includes both the user ID and the login. An `administrator` capability in the snapshot aborts the restore before it is applied. Other users, including the admin account, are not updated or deleted.

Each restore sets the published password, email, name, and role from the account baseline, clears application passwords and sessions for those two users, and puts the song library and its files back. Titles in the snapshot are written with SQL, so the profanity filter does not block the nightly restore.

The existing Demo data curl (`/?choir_demo_reset=1&key=SECRET`) calls the same restore once the baseline file exists. Until then it still reseeds Demo Song 01–25 and, with this mu-plugin installed, resets the two demo accounts. Use one nightly job, not both.

### 3. WP-Cron fallback

If the host cannot run a system cron, set `COMPATH_DEMO_GUARD_USE_WP_CRON` to true. WordPress will schedule a daily event at 03:00 Europe/Tallinn. That only runs when the site receives traffic. Prefer the system cron.

## Access needed to install

WP admin is not enough. Must-use plugins do not appear under Plugins → Add New.

The owner’s assistant needs:

1. FTP, SFTP, or the hosting file manager, rooted at `/demo.rehearsal.compath.ee`, to upload the two must-use plugins into `wp-content/mu-plugins/` (the profanity class goes in the `compath-rehearsal-profanity/` subdirectory) and the restore script outside the web root when that parent directory is writable.
2. The same file access if they want the optional constants in `wp-config.php`.
3. The hosting cron screen, or SSH with WP-CLI or PHP CLI, to save the baseline once and to add the 03:00 job.

An administrator login is useful only to look at the library before the first save. It cannot install these files.
