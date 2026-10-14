<?php
/**
 * The songs-page feedback form is only for logged-in users.
 * The Choir Rehearsal admin page still renders the same form.
 *
 * Run: php choir-rehearsal/tests/feedback-song-list-auth-test.php
 */

declare(strict_types=1);

$fail = 0;
$frontend = (string) file_get_contents(dirname(__DIR__) . '/includes/class-frontend.php');
$feedback = (string) file_get_contents(dirname(__DIR__) . '/includes/class-feedback.php');

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

$list_fn = strpos($frontend, 'function render_song_list');
$list_end = strpos($frontend, 'function get_current_list_page');
$list = (false !== $list_fn && false !== $list_end) ? substr($frontend, $list_fn, $list_end - $list_fn) : '';
$panel_at = strpos($list, 'Choir_Rehearsal_Feedback::render_panel()');
$before_panel = false !== $panel_at ? substr($list, max(0, $panel_at - 180), 180) : '';

check('song list renders feedback only when logged in', false !== $panel_at && str_contains($before_panel, 'is_user_logged_in()'));
if (false === $panel_at || !str_contains($before_panel, 'is_user_logged_in()')) {
	echo '  before panel: ' . json_encode($before_panel) . "\n";
}

$enqueue_at = strpos($frontend, 'Choir_Rehearsal_Feedback::enqueue_assets()');
$before_enqueue = false !== $enqueue_at ? substr($frontend, max(0, $enqueue_at - 160), 160) : '';
check(
	'song list does not load feedback script for guests',
	false !== $enqueue_at && str_contains($before_enqueue, 'is_song_list_page()') && str_contains($before_enqueue, 'is_user_logged_in()')
);

$admin_fn = strpos($feedback, 'function render_admin_page');
$admin_end = strpos($feedback, 'function enqueue_assets');
$admin = (false !== $admin_fn && false !== $admin_end) ? substr($feedback, $admin_fn, $admin_end - $admin_fn) : '';
check(
	'admin menu page still renders the form',
	str_contains($admin, 'render_panel( true )') && str_contains($feedback, "self::MENU_SLUG")
);

exit($fail > 0 ? 1 : 0);
