=== Choir Rehearsal ===
Contributors: compathee
Tags: choir, audio, rehearsal, voice parts, music
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.3.0
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
