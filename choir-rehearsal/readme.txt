=== Choir Rehearsal ===
Contributors: compath
Tags: choir, audio, rehearsal, voice parts, music
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.4.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private rehearsal library for choirs: songs, voice tracks, sticky player.

== Description ==

Choir Rehearsal helps choir members learn new pieces by voice part.

* Create songs and attach unlimited voice tracks
* Built-in voice list: backing track, bass, baritone, tenor, alto, soprano, other
* Upload MP3/WAV files from the Media Library
* Attach a PDF score per song with page-by-page viewer
* Frontend song list at `/rehearsal/`
* Sticky HTML5 player at the bottom of the page
* Optional login-only access
* REST API and MCP abilities for automation

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/choir-rehearsal`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Choir Rehearsal → Add Song**
4. Add voice tracks and upload audio files
5. Share `/rehearsal/` with logged-in singers

== Frequently Asked Questions ==

= Who can view rehearsal pages? =

By default only logged-in users can view `/rehearsal/`. Change this under **Choir Rehearsal → Settings**.

= Can I embed the song list in a page? =

Yes. Use the shortcode `[choir_rehearsal]`.

== Changelog ==

= 0.4.9 =
* Load demo songs creates 25 songs so library pagination (20 per page) appears after one click

= 0.4.8 =
* Settings: Load demo songs (10 songs × 4 tracks with demo voice audio)
* Settings: Delete all songs with Yes/No confirmation (removes tracks, media, songs)

= 0.4.7 =
* Pro search: match Cyrillic and other Unicode song titles (not only Latin)

= 0.4.6 =
* Fix double “Update now”: clear update cache after upgrade and compare version from disk

= 0.4.5 =
* Remove License key field from Lite settings (licensing belongs in Pro add-on)

= 0.4.4 =
* Buy Pro links to https://shop.compath.ee/products/choir-rehearsal-pro/

= 0.4.3 =
* Lite: Buy Pro button on Settings, Plugins list, and editor toolbar
* Documented Lite → Pro add-on install (do not replace Lite)

= 0.4.2 =
* Song list pagination (20 songs per page) for large libraries
* Pro search spans the full library while browsing stays paginated
* Mobile: search field spans full width above the song list

= 0.4.1 =
* Pro: search songs by title in the rehearsal library list

= 0.4.0 =
* Lite edition: up to 4 voice tracks per song, no microphone recording
* Pro edition via separate Choir Rehearsal Pro add-on plugin
* Edition label and upgrade link in Settings

= 0.3.11 =
* Mobile-friendly sticky player: wider controls, large play button, seek bar

= 0.3.10 =
* Plugin author shown as Compath OÜ (was Cappella Veneta in older builds)

= 0.3.9 =
* Product page URL set to rehearsal.compath.ee

= 0.3.8 =
* Product documentation page (order, install, pricing, changelog)
* Documentation link on Settings page

= 0.3.7 =
* Fix desktop layout: Publish sidebar no longer overlaps song title

= 0.3.6 =
* Fix: song title field visible again on edit screen

= 0.3.5 =
* Mobile song editor: Back to song list button (top and sticky footer)

= 0.3.4 =
* Simplified song editor: title, PDF score, and voice tracks only
* Removed WordPress content editor and extra meta boxes
* Mobile: main fields first, sticky Save/Publish bar at bottom

= 0.3.3 =
* Mobile-friendly admin layout for voice tracks (compact select and buttons)

= 0.3.2 =
* Fix microphone recording upload (video/webm and file type validation)
* Show specific server error when upload fails

= 0.3.1 =
* Show installed plugin version on the Settings page

= 0.3.0 =
* Record voice tracks from microphone in the song editor (MediaRecorder API)
* Recordings upload directly to the WordPress Media Library

= 0.2.6 =
* Fix duplicated login form caused by multiple [choir_rehearsal] shortcodes on the page

= 0.2.5 =
* Fix critical error after v0.2.4 upgrade

= 0.2.4 =
* Rehearsal library now uses a standard WordPress page with [choir_rehearsal] shortcode
* Auto-creates /rehearsal/ page and refreshes permalinks on upgrade
* Settings: choose rehearsal page and add it to WP menus

= 0.2.3 =
* Fix GitHub update check (repository URL encoding returned 404)
* Fallback update metadata from update.json in the repository
* "Check for updates now" button in settings

= 0.2.2 =
* Frontend login screen on rehearsal pages instead of redirect to wp-login.php
* Role-based toolbar: editors manage songs, singers view and listen only

= 0.2.1 =
* WordPress-native plugin updates via GitHub Releases or custom JSON URL

= 0.2.0 =
* PDF sheet music attachment per song
* PDF viewer with previous/next page controls on song pages

= 0.1.0 =
* Initial release: songs, tracks, sticky player, REST API, MCP abilities.
