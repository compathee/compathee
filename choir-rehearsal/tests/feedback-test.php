<?php
/**
 * Feedback relay payload, rate limit, and license metadata.
 *
 * Run: php choir-rehearsal/tests/feedback-test.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!function_exists('__')) {
	function __(string $text, string $domain = 'default'): string {
		unset($domain);
		return $text;
	}
}

require dirname(__DIR__) . '/includes/class-roles.php';
require dirname(__DIR__) . '/includes/class-feedback.php';

$fail = 0;
$secret = 'ZZZZ-SECRET-KEY-9999';

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

function context(array $overrides = []): array {
	return array_merge(
		array(
			'role'           => 'Singer',
			'plugin_version' => '0.4.61',
			'pro_version'    => '',
			'wp_version'     => '6.8',
			'php_version'    => '8.3.6',
			'site_url'       => 'https://choir.example/',
			'endpoint'       => 'https://rehearsal.compath.ee/api/feedback.php',
			'now'            => 1_000_000,
			'rate_keys'      => array('u7'),
			'rate_limit'     => 5,
			'rate_window'    => 3600,
		),
		$overrides
	);
}

function input(array $overrides = []): array {
	return array_merge(
		array(
			'type'        => 'bug',
			'title'       => 'Player stops',
			'description' => 'The sticky player stops after one track.',
			'name'        => 'Ada',
			'email'       => 'singer@example.com',
			'company'     => '',
		),
		$overrides
	);
}

function relay_ok(string $case = 'DBT-57'): array {
	return array(
		'code' => 200,
		'data' => array(
			'ok' => true,
			'case' => $case,
		),
	);
}

check('default endpoint', Choir_Rehearsal_Feedback::ENDPOINT === 'https://rehearsal.compath.ee/api/feedback.php');
check('endpoint filter name', Choir_Rehearsal_Feedback::ENDPOINT_FILTER === 'choir_rehearsal_feedback_endpoint');
check('https endpoint accepted', Choir_Rehearsal_Feedback::is_https_url('https://rehearsal.compath.ee/api/feedback.php'));
check('http endpoint rejected', !Choir_Rehearsal_Feedback::is_https_url('http://rehearsal.compath.ee/api/feedback.php'));
check('case shape', Choir_Rehearsal_Feedback::is_case('DBT-57') && !Choir_Rehearsal_Feedback::is_case('dbt-57') && !Choir_Rehearsal_Feedback::is_case('DBT-57;'));

$plain = Choir_Rehearsal_Feedback::to_plain_text("<script>alert(1)</script><b>Hello</b> &amp; choir");
check('plain text strips tags', $plain === 'Hello & choir');
check('plain text has no brackets', !str_contains($plain, '<') && !str_contains($plain, '>'));

$calls = array();
$buckets = array();
$honey = Choir_Rehearsal_Feedback::submit_feedback(
	input(array('company' => 'Acme')),
	context(),
	static function (string $key) use (&$buckets): array {
		return $buckets[$key] ?? array();
	},
	static function (string $key, array $stamps) use (&$buckets): void {
		$buckets[$key] = $stamps;
	},
	static function () use (&$calls): array {
		$calls[] = 'http';
		return relay_ok();
	}
);
check('honeypot rejected', 400 === $honey['status'] && 'invalid' === $honey['code']);
check('honeypot skips relay', array() === $calls && array() === $buckets);

$calls = array();
$empty = Choir_Rehearsal_Feedback::submit_feedback(
	input(array(
		'title' => '  <b></b>  ',
		'description' => '   ',
		'email' => '',
	)),
	context(),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function () use (&$calls): array {
		$calls[] = 'http';
		return array();
	}
);
check('empty spam rejected', 400 === $empty['status'] && 'invalid' === $empty['code']);
check('empty spam skips relay', array() === $calls);

$calls = array();
$missing_email = Choir_Rehearsal_Feedback::submit_feedback(
	input(array('email' => 'not-an-email')),
	context(),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function () use (&$calls): array {
		$calls[] = 'http';
		return relay_ok();
	}
);
check('email required', 400 === $missing_email['status'] && 'email' === $missing_email['code'] && 'Please enter a valid email address.' === $missing_email['message']);
check('invalid email skips relay', array() === $calls);

$calls = array();
$limited = Choir_Rehearsal_Feedback::submit_feedback(
	input(),
	context(),
	static fn(string $key): array => array(999_000, 999_100, 999_200, 999_300, 999_400),
	static function (): void {
		throw new RuntimeException('rate limit should not record another send');
	},
	static function () use (&$calls): array {
		$calls[] = 'http';
		return array();
	}
);
check('rate limit', 429 === $limited['status'] && 'rate_limited' === $limited['code']);
check('rate limit skips relay', array() === $calls);

$calls = array();
$saved = array();
$sent = Choir_Rehearsal_Feedback::submit_feedback(
	input(array(
		'title' => "<script>alert(1)</script> Player <b>stops</b>",
		'description' => "It stops.\n<script>nope</script><img src=x onerror=alert(1)>",
		'role' => 'ignored',
	)),
	context(array('role' => 'Guest', 'locale' => 'et')),
	static fn(string $key): array => array(),
	static function (string $key, array $stamps) use (&$saved): void {
		$saved[$key] = $stamps;
	},
	static function (string $url, array $payload) use (&$calls): array {
		$calls[] = compact('url', 'payload');
		return relay_ok('DBT-15');
	}
);
$payload = $calls[0]['payload'] ?? array();
check('posts to relay', ($calls[0]['url'] ?? '') === 'https://rehearsal.compath.ee/api/feedback.php');
check('payload title and message', ($payload['title'] ?? '') === 'Player stops' && str_contains((string) ($payload['message'] ?? ''), 'It stops.') && !str_contains((string) ($payload['message'] ?? ''), '<') && !str_contains((string) ($payload['message'] ?? ''), 'onerror'));
check('payload metadata', ($payload['type'] ?? '') === 'bug' && ($payload['role'] ?? '') === 'Guest' && ($payload['locale'] ?? '') === 'et' && ($payload['plugin_version'] ?? '') === '0.4.61' && ($payload['site_url'] ?? '') === 'https://choir.example/' && ($payload['email'] ?? '') === 'singer@example.com' && ($payload['name'] ?? '') === 'Ada' && ($payload['edition'] ?? '') === 'Lite' && false === ($payload['pro'] ?? true));
check('success case', $sent['ok'] && 'DBT-15' === $sent['case'] && 'sent' === $sent['code']);
check('success message', 'Request received, case number DBT-15. A confirmation has been sent to singer@example.com.' === $sent['message']);
check('rate bucket recorded', ($saved['u7'] ?? array()) === array(1_000_000));

$calls = array();
$down = Choir_Rehearsal_Feedback::submit_feedback(
	input(),
	context(),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function () use (&$calls): array {
		$calls[] = 'http';
		return array('code' => 502, 'data' => array('ok' => false, 'error' => 'unavailable ' . $GLOBALS['secret']));
	}
);
check('relay failure', !$down['ok'] && 502 === $down['status'] && 'relay' === $down['code']);
check('relay failure hides body', 'Could not send feedback. Please try again later.' === $down['message'] && !str_contains($down['message'], $secret));

$frontend = (string) file_get_contents(dirname(__DIR__) . '/includes/class-frontend.php');
$feedback = (string) file_get_contents(dirname(__DIR__) . '/includes/class-feedback.php');
$plugin = (string) file_get_contents(dirname(__DIR__) . '/includes/class-plugin.php');
$admin = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin.php');
$js = (string) file_get_contents(dirname(__DIR__) . '/public/js/feedback.js');
$uninstall = (string) file_get_contents(dirname(__DIR__) . '/uninstall.php');

$panel_at = strpos($frontend, 'Choir_Rehearsal_Feedback::render_panel()');
$panel_line = false !== $panel_at ? substr($frontend, $panel_at, 80) : '';
$before_panel = false !== $panel_at ? substr($frontend, max(0, $panel_at - 40), 40) : '';
check('panel under song list', str_contains($panel_line, 'render_panel()') && str_contains($before_panel, 'endif'));
check('script not limited to pro search', str_contains($frontend, "if ( self::is_song_list_page() ) {\n\t\t\tChoir_Rehearsal_Feedback::enqueue_assets();"));
check('plugin boots feedback', str_contains($plugin, 'class-feedback.php') && str_contains($plugin, 'Choir_Rehearsal_Feedback::register()'));
check('no feedback settings in admin', !str_contains($admin, 'Choir_Rehearsal_Feedback::register_settings()') && !str_contains($admin, 'Choir_Rehearsal_Feedback::render_settings_rows()'));
check('updater repo stays gated', str_contains($admin, 'uses_github_updater()') && str_contains($admin, 'choir_rehearsal_github_repo'));
check('no pro gate in feedback class', !str_contains($feedback, 'Choir_Rehearsal_Edition'));
check('no github issue client', !str_contains($feedback, 'api.github.com') && !str_contains($feedback, 'github_pat') && !str_contains($feedback, 'OPTION_TOKEN') && !str_contains($js, 'github.com'));

$enqueue_start = strpos($feedback, 'function enqueue_assets');
$enqueue_end = strpos($feedback, 'function render_panel');
$enqueue = substr($feedback, (int) $enqueue_start, (int) $enqueue_end - (int) $enqueue_start);
$panel_start = strpos($feedback, 'function render_panel');
$panel_end = strpos($feedback, 'function to_plain_text');
$panel = substr($feedback, (int) $panel_start, (int) $panel_end - (int) $panel_start);
check('front-end script has no options', !str_contains($enqueue, 'get_option') && !str_contains($panel, 'get_option'));
check('no password field', !str_contains($feedback, 'type="password"'));
check('js has no html injection', !str_contains($js, 'innerHTML') && str_contains($js, 'textContent') && str_contains($js, 'company'));
check('uninstall removes old feedback options', str_contains($uninstall, 'choir_rehearsal_feedback_github_token') && str_contains($uninstall, 'choir_rehearsal_feedback_github_repo'));

if (!class_exists('WP_User')) {
	class WP_User {
		public string $user_email = 'singer@example.com';
		public string $display_name = 'Ada Singer';
	}
}
if (!function_exists('is_user_logged_in')) {
	function is_user_logged_in(): bool {
		return (bool) ($GLOBALS['choir_feedback_logged_in'] ?? false);
	}
}
if (!function_exists('wp_get_current_user')) {
	function wp_get_current_user(): WP_User {
		return new WP_User();
	}
}
if (!function_exists('sanitize_email')) {
	function sanitize_email(string $email): string {
		return trim($email);
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field(string $text): string {
		return trim(strip_tags($text));
	}
}
if (!function_exists('is_email')) {
	function is_email(string $email): bool {
		return str_contains($email, '@');
	}
}
if (!function_exists('esc_html_e')) {
	function esc_html_e(string $text, string $domain = ''): void {
		unset($domain);
		echo $text;
	}
}
if (!function_exists('esc_attr_e')) {
	function esc_attr_e(string $text, string $domain = ''): void {
		unset($domain);
		echo $text;
	}
}
if (!function_exists('esc_attr')) {
	function esc_attr(string $text): string {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('esc_url')) {
	function esc_url(string $url): string {
		return $url;
	}
}
if (!function_exists('rest_url')) {
	function rest_url(string $path = ''): string {
		return 'https://choir.example/wp-json/' . ltrim($path, '/');
	}
}
if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field(string $action): void {
		echo '<input type="hidden" name="_wpnonce" value="test-nonce" />';
	}
}

$GLOBALS['choir_feedback_logged_in'] = true;
ob_start();
Choir_Rehearsal_Feedback::render_panel();
$logged_in_html = (string) ob_get_clean();
check('logged-in form prefill', str_contains($logged_in_html, 'value="singer@example.com"') && str_contains($logged_in_html, 'value="Ada Singer"') && str_contains($logged_in_html, 'Send feedback'));
check('email required in the form', str_contains($logged_in_html, 'type="email"') && str_contains($logged_in_html, 'required') && str_contains($logged_in_html, 'Your email is used only to answer this request.'));
check('form posts to rest', str_contains($logged_in_html, 'action="https://choir.example/wp-json/choir-rehearsal/v1/feedback"') && str_contains($logged_in_html, 'name="_wpnonce"'));
check('rendered form has no secrets', !str_contains($logged_in_html, $secret) && !str_contains($logged_in_html, 'jira_token') && !str_contains($logged_in_html, 'github_pat'));
check('honeypot present', str_contains($logged_in_html, 'name="company"'));

$GLOBALS['choir_feedback_logged_in'] = false;
ob_start();
Choir_Rehearsal_Feedback::render_panel();
$guest_html = (string) ob_get_clean();
check('guest form still renders', str_contains($guest_html, 'name="email"') && str_contains($guest_html, 'value=""') && str_contains($guest_html, 'value="bug"') && str_contains($guest_html, 'value="wish"') && str_contains($guest_html, 'required'));

$enqueue_at = strpos($feedback, 'function enqueue_assets');
$panel_fn = strpos($feedback, 'function render_panel');
$enqueue_src = (false !== $enqueue_at && false !== $panel_fn) ? substr($feedback, $enqueue_at, $panel_fn - $enqueue_at) : '';
check('front end has no license fields', !str_contains($enqueue_src, 'license') && !str_contains($js, 'license_key') && !str_contains($logged_in_html, 'License ID') && !str_contains($logged_in_html, 'sc_license'));

$pro = Choir_Rehearsal_Feedback::license_snapshot(true, array(
	'sc_license_status' => 'active',
	'sc_license_id' => 'li_priority_1',
	'sc_activation_id' => 'act_site_1',
	'sc_customer_id' => 'cus_buyer_1',
	'sc_purchase_id' => 'pur_sale_1',
	'sc_license_key' => $secret,
));
check('pro snapshot is active', $pro['pro'] && 'active' === $pro['status']);

$calls = array();
Choir_Rehearsal_Feedback::submit_feedback(
	input(array('title' => 'Pro player stops', 'description' => 'Stops on the second verse.')),
	context(array('license' => $pro, 'pro_version' => '0.5.0')),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function (string $url, array $payload) use (&$calls): array {
		unset($url);
		$calls[] = $payload;
		return relay_ok('DBT-21');
	}
);
$pro_payload = $calls[0] ?? array();
$encoded = (string) json_encode($pro_payload);
check('pro flag', true === ($pro_payload['pro'] ?? false) && ($pro_payload['edition'] ?? '') === 'Pro' && ($pro_payload['pro_version'] ?? '') === '0.5.0');
check(
	'pro identifier',
	($pro_payload['license']['status'] ?? '') === 'active'
	&& ($pro_payload['license']['license_id'] ?? '') === 'li_priority_1'
	&& ($pro_payload['license']['customer_id'] ?? '') === 'cus_buyer_1'
	&& ($pro_payload['license']['purchase_id'] ?? '') === 'pur_sale_1'
	&& ($pro_payload['license']['activation_id'] ?? '') === 'act_site_1'
	&& ($pro_payload['site_url'] ?? '') === 'https://choir.example/'
);
check('pro payload hides full key', !str_contains($encoded, $secret) && !str_contains($encoded, 'SECRET') && !isset($pro_payload['license']['license_key']) && !isset($pro_payload['license']['license_key_masked']));

$key_only = 'ABCD1234WXYZ5678';
$masked = Choir_Rehearsal_Feedback::license_snapshot(true, array(
	'sc_license_status' => 'active',
	'sc_license_key' => $key_only,
));
$masked_body = Choir_Rehearsal_Feedback::issue_body(array(
	'type' => 'bug',
	'email' => 'singer@example.com',
	'role' => 'Singer',
	'plugin_version' => '0.4.61',
	'wp_version' => '6.8',
	'php_version' => '8.3.6',
	'site_url' => 'https://choir.example/',
	'description' => 'Masked key only.',
	'license' => $masked,
));
check('masked key when no public id', str_contains($masked_body, 'License key: ABCD********5678') && !str_contains($masked_body, $key_only) && !str_contains($masked_body, '1234WXYZ'));
$masked_payload = Choir_Rehearsal_Feedback::license_payload($masked);
check('masked payload key', ($masked_payload['license_key_masked'] ?? '') === 'ABCD********5678' && !str_contains((string) json_encode($masked_payload), $key_only));

$lite = Choir_Rehearsal_Feedback::license_snapshot(false, array('sc_license_key' => $secret));
$calls = array();
Choir_Rehearsal_Feedback::submit_feedback(
	input(array('type' => 'wish', 'title' => 'Lite wish', 'description' => 'A wish from Lite.')),
	context(array('license' => $lite)),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function (string $url, array $payload) use (&$calls): array {
		unset($url);
		$calls[] = $payload;
		return relay_ok('DBT-22');
	}
);
$lite_payload = $calls[0] ?? array();
check('lite has no pro flag', false === ($lite_payload['pro'] ?? true) && ($lite_payload['edition'] ?? '') === 'Lite' && ($lite_payload['type'] ?? '') === 'wish');
check('lite license line', ($lite_payload['license']['status'] ?? '') === 'Lite' && !str_contains((string) json_encode($lite_payload), $secret));

$expired = Choir_Rehearsal_Feedback::license_snapshot(true, array(
	'sc_license_status' => 'expired',
	'sc_license_id' => 'li_old',
	'sc_license_key' => $secret,
));
check('expired is not pro', !$expired['pro'] && 'expired' === $expired['status']);
$expired_body = Choir_Rehearsal_Feedback::issue_body(array(
	'type' => 'bug',
	'email' => 'singer@example.com',
	'role' => 'Singer',
	'plugin_version' => '0.4.61',
	'wp_version' => '6.8',
	'php_version' => '8.3.6',
	'site_url' => 'https://choir.example/',
	'description' => 'Expired license.',
	'license' => $expired,
));
check('expired body', str_contains($expired_body, 'License: expired') && str_contains($expired_body, 'License ID: li_old') && !str_contains($expired_body, $secret));
$expired_payload = Choir_Rehearsal_Feedback::license_payload($expired);
check('expired payload', ($expired_payload['status'] ?? '') === 'expired' && ($expired_payload['license_id'] ?? '') === 'li_old' && !isset($expired_payload['license_key_masked']));

require dirname(__DIR__, 2) . '/choir-rehearsal-pro/licensing/src/License.php';
$stored = new stdClass();
\SureCart\Licensing\License::remember_public_details($stored, (object) array(
	'status' => 'Active',
	'key' => $secret,
	'customer' => (object) array('id' => 'cus_from_api'),
	'purchase' => 'pur_from_api',
	'order' => array('id' => 'ord_from_api'),
));
check(
	'activation stores public ids not the key',
	($stored->license_status ?? '') === 'active'
	&& ($stored->customer_id ?? '') === 'cus_from_api'
	&& ($stored->purchase_id ?? '') === 'pur_from_api'
	&& ($stored->order_id ?? '') === 'ord_from_api'
	&& !isset($stored->license_key)
);
check('email is not a customer id', '' === \SureCart\Licensing\License::public_id('singer@example.com'));

if (!function_exists('get_user_locale')) {
	function get_user_locale(): string {
		return (string) ($GLOBALS['choir_user_locale'] ?? '');
	}
}
if (!function_exists('get_locale')) {
	function get_locale(): string {
		return (string) ($GLOBALS['choir_site_locale'] ?? '');
	}
}

$GLOBALS['choir_feedback_logged_in'] = true;
$GLOBALS['choir_user_locale'] = 'et_EE';
$GLOBALS['choir_site_locale'] = 'en_US';
check('logged-in locale', 'et_EE' === Choir_Rehearsal_Feedback::sender_locale());

$GLOBALS['choir_feedback_logged_in'] = false;
$GLOBALS['choir_user_locale'] = 'en_US';
$GLOBALS['choir_site_locale'] = 'ru_RU';
check('guest locale', 'ru_RU' === Choir_Rehearsal_Feedback::sender_locale());

$GLOBALS['choir_feedback_logged_in'] = true;
$GLOBALS['choir_user_locale'] = '';
$GLOBALS['choir_site_locale'] = 'et';
check('empty user locale uses site', 'et' === Choir_Rehearsal_Feedback::sender_locale());
check('locale rejects extra text', '' === Choir_Rehearsal_Feedback::sanitize_locale("ru_RU\nInjected"));

$GLOBALS['choir_feedback_logged_in'] = false;
$GLOBALS['choir_site_locale'] = 'en_US';
$calls = array();
Choir_Rehearsal_Feedback::submit_feedback(
	input(array('type' => 'other', 'title' => 'Guest note', 'description' => 'Sent without a locale field.')),
	context(array()),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function (string $url, array $payload) use (&$calls): array {
		unset($url);
		$calls[] = $payload;
		return relay_ok('DBT-24');
	}
);
check('guest issue uses site locale', ($calls[0]['locale'] ?? '') === 'en_US');
check('locale is not a request field', !str_contains($js, 'locale') && !str_contains($feedback, "get_param( 'locale'"));

exit($fail > 0 ? 1 : 0);
