<?php
/**
 * Feedback is a Choir Rehearsal submenu page, using the same form as the song list.
 *
 * Run: php choir-rehearsal/tests/feedback-admin-menu-test.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('CHOIR_REHEARSAL_URL', 'https://choir.example/wp-content/plugins/choir-rehearsal/');
define('CHOIR_REHEARSAL_VERSION', '0.4.65');

$fail = 0;
$GLOBALS['choir_submenus'] = array();
$GLOBALS['choir_scripts'] = array();
$GLOBALS['choir_styles'] = array();

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

function __(string $text, string $domain = ''): string {
	unset($domain);
	return $text;
}

function esc_html__(string $text, string $domain = ''): string {
	unset($domain);
	return $text;
}

function esc_html(string $text): string {
	return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr(string $text): string {
	return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_html_e(string $text, string $domain = ''): void {
	unset($domain);
	echo esc_html(__($text));
}

function esc_attr_e(string $text, string $domain = ''): void {
	unset($domain);
	echo esc_attr(__($text));
}

function esc_url(string $url): string {
	return $url;
}

function rest_url(string $path = ''): string {
	return 'https://choir.example/wp-json/' . ltrim($path, '/');
}

function wp_nonce_field(string $action): void {
	unset($action);
	echo '<input type="hidden" name="_wpnonce" value="test-nonce" />';
}

function is_user_logged_in(): bool {
	return true;
}

function sanitize_email(string $email): string {
	return $email;
}

function sanitize_text_field(string $text): string {
	return $text;
}

function is_email(string $email): bool {
	return str_contains($email, '@');
}

function current_user_can(string $cap): bool {
	unset($cap);
	return true;
}

function wp_create_nonce(string $action): string {
	unset($action);
	return 'rest-nonce';
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void {
	unset($deps, $ver, $in_footer);
	$GLOBALS['choir_scripts'][] = array('handle' => $handle, 'src' => $src);
}

function wp_localize_script(string $handle, string $object, array $data): void {
	$GLOBALS['choir_scripts_data'][$handle] = array('object' => $object, 'data' => $data);
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false): void {
	unset($deps, $ver);
	$GLOBALS['choir_styles'][] = array('handle' => $handle, 'src' => $src);
}

/**
 * @param callable $callback
 */
function add_submenu_page(string $parent, string $page_title, string $menu_title, string $capability, string $slug, $callback): string {
	$GLOBALS['choir_submenus'][] = array(
		'parent' => $parent,
		'page_title' => $page_title,
		'menu_title' => $menu_title,
		'capability' => $capability,
		'slug' => $slug,
		'callback' => $callback,
	);
	return $slug;
}

class WP_User {
	public string $user_email = 'owner@example.com';
	public string $display_name = 'Aleksandr';
}

function wp_get_current_user(): WP_User {
	return new WP_User();
}

require dirname(__DIR__) . '/includes/class-post-types.php';
require dirname(__DIR__) . '/includes/class-feedback.php';

check('feedback registers an admin menu', method_exists('Choir_Rehearsal_Feedback', 'register_menu'));
if (!method_exists('Choir_Rehearsal_Feedback', 'register_menu') || !method_exists('Choir_Rehearsal_Feedback', 'render_admin_page')) {
	exit(1);
}

Choir_Rehearsal_Feedback::register_menu();
$menu = $GLOBALS['choir_submenus'][0] ?? array();
check(
	'menu is a Choir Rehearsal submenu',
	($menu['parent'] ?? '') === 'edit.php?post_type=choir_song'
		&& ($menu['slug'] ?? '') === 'choir-rehearsal-feedback'
		&& ($menu['menu_title'] ?? '') === 'Send feedback'
		&& ($menu['capability'] ?? '') === 'edit_choir_songs'
		&& ($menu['callback'] ?? null) === array('Choir_Rehearsal_Feedback', 'render_admin_page')
);
if (($menu['parent'] ?? '') !== 'edit.php?post_type=choir_song') {
	echo '  got: ' . json_encode($menu) . "\n";
}

ob_start();
Choir_Rehearsal_Feedback::render_admin_page();
$html = (string) ob_get_clean();
check('admin page heading', str_contains($html, '<h1>Send feedback</h1>'));
check('same form posts to the plugin relay route', str_contains($html, 'id="choir-feedback-form"') && str_contains($html, 'action="https://choir.example/wp-json/choir-rehearsal/v1/feedback"'));
check('admin form is expanded', str_contains($html, '<details class="choir-feedback" open>'));
check('admin form keeps the fields', str_contains($html, 'name="type"') && str_contains($html, 'name="email"') && str_contains($html, 'value="owner@example.com"') && str_contains($html, 'name="company"'));
check('page does not call the public relay directly', !str_contains($html, 'https://rehearsal.compath.ee/api/feedback.php'));

Choir_Rehearsal_Feedback::enqueue_admin_assets('choir_song_page_choir-rehearsal-feedback');
$handles = array_column($GLOBALS['choir_scripts'], 'handle');
check('admin page loads feedback.js', in_array('choir-rehearsal-feedback', $handles, true));
$data = $GLOBALS['choir_scripts_data']['choir-rehearsal-feedback']['data'] ?? array();
check(
	'admin script posts to the same REST route',
	($data['restUrl'] ?? '') === 'https://choir.example/wp-json/choir-rehearsal/v1/feedback'
		&& ($data['nonce'] ?? '') === 'rest-nonce'
);
$style_srcs = array_column($GLOBALS['choir_styles'], 'src');
check('admin page loads feedback styles', in_array(CHOIR_REHEARSAL_URL . 'public/css/public.css', $style_srcs, true));

$scripts_before = count($GLOBALS['choir_scripts']);
Choir_Rehearsal_Feedback::enqueue_admin_assets('edit.php');
check('other admin screens do not load the form script', count($GLOBALS['choir_scripts']) === $scripts_before);

exit($fail > 0 ? 1 : 0);
