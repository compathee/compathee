# Pro Backup Import UX Implementation Plan

> **For agentic workers:** Steps use checkbox (`- [x]`) syntax for tracking. Spec: `choir-rehearsal/docs/superpowers/specs/2026-09-14-pro-songs-backup-import-design.md`.

**Goal:** Change Pro Backup songs Settings so Import uses Skip/Replace-per-slug (not wipe-all), with a single Import button and match policy control.

**Architecture:** Update `Choir_Rehearsal_Pro_Backup` UI + `handle_import` / `import_from_manifest`. Export unchanged. Demo library unchanged.

**Tech Stack:** WordPress admin PHP, ZipArchive, inline admin JS.

## Global Constraints

- Pro only (`Choir_Rehearsal_Edition::is_pro()` + `manage_options`)
- Match key: song slug (`post_name`)
- Replace = delete matching song then recreate from ZIP
- Skip = leave matching songs alone; still add new slugs
- Never wipe the whole library on Import
- Manifest format id stays `compath-choir-rehearsal-backup`

---

### Task 1: Import match modes + delete-one-song

**Files:**
- Modify: `choir-rehearsal-pro/includes/class-backup.php`

- [x] **Step 1:** Change `handle_import` modes from `merge`/`replace`(wipe-all) to `skip`/`replace`(per-slug). Remove `delete_all_songs()` call before import.
- [x] **Step 2:** Change `import_from_manifest` to accept `$match_mode` (`skip`|`replace`). On existing slug: skip, or `delete_song_by_slug` then create.
- [x] **Step 3:** Add `delete_song_by_slug( string $slug ): bool` — delete that song’s tracks, song, and its audio/PDF attachments (reuse logic from `delete_all_songs` scoped to one song).
- [x] **Step 4:** Update success notices: always show imported/skipped/replaced counts; remove `choir_backup_restored` wipe-all notice.
- [x] **Step 5:** Commit.

### Task 2: Settings UI + JS

**Files:**
- Modify: `choir-rehearsal-pro/includes/class-backup.php` (`render_settings_section`, `settings_inline_js`)

- [x] **Step 1:** UI: Export button; Import file input; radios **What to do with matches?** → Skip (default) / Replace; single **Import** button. Remove dual import buttons and replace-all dialog.
- [x] **Step 2:** Simplify/remove inline JS that drove the replace-all dialog (optional: require file before submit via HTML `required` only).
- [x] **Step 3:** Bump Pro to `0.4.47`.
- [x] **Step 4:** `php -l` on Pro files; commit; push; refresh Pro draft release zip.

### Task 3: Verify

- [x] Confirm `delete_all_songs` is unused by Import (may keep private for future or remove if dead).
- [x] Confirm Demo settings block unchanged in Lite admin.
