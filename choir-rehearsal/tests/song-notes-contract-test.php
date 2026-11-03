<?php
/**
 * Song notes stay behind the Pro license gate, enforce capabilities, and do not email singers.
 *
 * Run: php choir-rehearsal/tests/song-notes-contract-test.php
 */

declare(strict_types=1);

$root    = dirname(__DIR__);
$edition = (string) file_get_contents($root . '/includes/class-edition.php');
$wporg   = (string) file_get_contents($root . '/includes/edition-wporg.php');
$notes   = (string) file_get_contents($root . '/includes/class-song-notes.php');
$plugin  = (string) file_get_contents($root . '/includes/class-plugin.php');
$front   = (string) file_get_contents($root . '/includes/class-frontend.php');
$player  = (string) file_get_contents($root . '/public/js/player.js');
$js      = (string) file_get_contents($root . '/public/js/song-notes.js');
$css     = (string) file_get_contents($root . '/public/css/public.css');
$uninstall = (string) file_get_contents($root . '/uninstall.php');
$readme  = (string) file_get_contents($root . '/readme.txt');
$boot    = (string) file_get_contents($root . '/choir-rehearsal.php');
$pro     = (string) file_get_contents(dirname($root) . '/choir-rehearsal-pro/choir-rehearsal-pro.php');

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

check('github edition gates notes with is_pro', str_contains($edition, 'function can_use_song_notes') && str_contains($edition, 'return self::is_pro();'));
check('wporg edition exposes the method without lite gate identifiers', str_contains($wporg, 'function can_use_song_notes') && !preg_match('/LITE_MAX_TRACKS|choir_rehearsal_is_pro|is_full_edition/', $wporg));
check('plugin loads song notes', str_contains($plugin, 'class-song-notes.php') && str_contains($plugin, 'Choir_Rehearsal_Song_Notes::register()'));
check('version is 0.4.69', str_contains($boot, "define( 'CHOIR_REHEARSAL_VERSION', '0.4.69' )"));
check('pro add-on is 0.5.3', str_contains($pro, "define( 'CHOIR_REHEARSAL_PRO_VERSION', '0.5.3' )"));

check('writes require the permission helper', str_contains($notes, 'Choir_Rehearsal_Song_Notes_Format::user_can_write('));
check('reads require the permission helper', str_contains($notes, 'Choir_Rehearsal_Song_Notes_Format::user_can_read('));
check('writes verify a nonce', str_contains($notes, "wp_verify_nonce(") && str_contains($notes, 'choir_rehearsal_song_notes'));
check('queries are prepared', str_contains($notes, '$wpdb->prepare(') && str_contains($notes, '$wpdb->insert('));
check('notes are not emailed', !preg_match('/wp_mail|wp_schedule_event|wp_mail\(/', $notes));
check('feature checks the edition gate', str_contains($notes, 'Choir_Rehearsal_Edition::can_use_song_notes()'));
check('song visibility is checked', str_contains($notes, 'Choir_Rehearsal_Access::can_view_song('));
check('managers use the existing manage check', str_contains($notes, 'Choir_Rehearsal_Access::can_manage()'));
check('uninstall drops the notes table', str_contains($uninstall, 'choir_song_notes'));

check('song page renders the notes control', str_contains($front, 'Choir_Rehearsal_Song_Notes::render_header_button('));
check('track list can show the recommendation icon', str_contains($front, 'Choir_Rehearsal_Song_Notes::render_track_button('));
check('player exposes seek for time marks', str_contains($player, 'window.choirPlayer') && str_contains($player, 'seek:'));
check('reader script does not auto-publish speech', str_contains($js, 'SpeechRecognition') && !str_contains($js, 'autoPublish') && str_contains($js, 'localStorage'));
check('sheets and 44px targets exist', str_contains($css, 'choir-notes-sheet') && str_contains($css, '44px') && str_contains($css, '320px'));
check('readme documents the pro bullet', str_contains($readme, 'Pro: song notes and track recommendations'));
check('readme changelog is 0.4.69', str_contains($readme, '= 0.4.69 ='));
check('placeholder copy is translatable', str_contains($notes, 'Type @ and choose a part to address advice to a track'));

$et = (string) file_get_contents($root . '/languages/compath-choir-rehearsal-et.l10n.php');
$ru = (string) file_get_contents($root . '/languages/compath-choir-rehearsal-ru_RU.l10n.php');
$etmo = (string) file_get_contents($root . '/languages/compath-choir-rehearsal-et.mo');
$rumo = (string) file_get_contents($root . '/languages/compath-choir-rehearsal-ru_RU.mo');
$placeholder = 'Type @ and choose a part to address advice to a track';
check('estonian catalog translates the placeholder', str_contains($et, $placeholder) && str_contains($et, 'Kirjutage @ ja valige partii') && str_contains($etmo, $placeholder));
check('russian catalog translates the placeholder', str_contains($ru, $placeholder) && str_contains($ru, 'Напишите @ и выберите партию') && str_contains($rumo, $placeholder));

exit($fail > 0 ? 1 : 0);
