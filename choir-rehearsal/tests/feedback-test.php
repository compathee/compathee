<?php
/**
 * Feedback issue payload, rate limit, and token handling.
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

$token = 'github_pat_TESTTOKENVALUE123456';
$fail = 0;

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
			'token'          => $GLOBALS['token'],
			'repo'           => 'compathee/compathee',
			'role'           => 'Singer',
			'plugin_version' => '0.4.61',
			'wp_version'     => '6.8',
			'php_version'    => '8.3.6',
			'site_url'       => 'https://choir.example/',
			'now'            => 1_000_000,
			'rate_keys'      => array('u7'),
			'rate_limit'     => 5,
			'rate_window'    => 3600,
		),
		$overrides
	);
}

$GLOBALS['token'] = $token;

check('token shape', Choir_Rehearsal_Feedback::is_token_shape($token));
check('short token rejected', !Choir_Rehearsal_Feedback::is_token_shape('ghp_short'));
check('token with space rejected', !Choir_Rehearsal_Feedback::is_token_shape($token . ' x'));
check('blank token keeps saved', Choir_Rehearsal_Feedback::resolve_token('', $token, false) === $token);
check('clear token', Choir_Rehearsal_Feedback::resolve_token('', $token, true) === '');
check('replace token', Choir_Rehearsal_Feedback::resolve_token('github_pat_NEWTOKENVALUE12345678', $token, true) === 'github_pat_NEWTOKENVALUE12345678');
check('invalid paste keeps saved', Choir_Rehearsal_Feedback::resolve_token('not a token', $token, false) === $token);
check('default repo', Choir_Rehearsal_Feedback::sanitize_repo_value('  ') === 'compathee/compathee');
check('custom repo', Choir_Rehearsal_Feedback::sanitize_repo_value('Compathee/Compathee') === 'Compathee/Compathee');
check('repo with spaces rejected', Choir_Rehearsal_Feedback::sanitize_repo_value('owner/repo name') === 'compathee/compathee');

$plain = Choir_Rehearsal_Feedback::to_plain_text("<script>alert(1)</script><b>Hello</b> &amp; choir");
check('plain text strips tags', $plain === 'Hello & choir');
check('plain text has no brackets', !str_contains($plain, '<') && !str_contains($plain, '>'));

$buckets = array();
$calls = array();
$missing = Choir_Rehearsal_Feedback::submit_feedback(
	array(
		'type'        => 'bug',
		'title'       => 'Player stops',
		'description' => 'The sticky player stops after one track.',
		'email'       => 'singer@example.com',
	),
	context(array('token' => '')),
	static function (string $key) use (&$buckets): array {
		return $buckets[$key] ?? array();
	},
	static function (string $key, array $stamps) use (&$buckets): void {
		$buckets[$key] = $stamps;
	},
	static function () use (&$calls): array {
		$calls[] = 'http';
		return array('code' => 500, 'message' => '', 'data' => array());
	}
);
check('missing token status', 503 === $missing['status'] && 'not_configured' === $missing['code']);
check('missing token message', 'Feedback is not configured.' === $missing['message']);
check('missing token skips github', array() === $calls && array() === $buckets);

$calls = array();
$empty = Choir_Rehearsal_Feedback::submit_feedback(
	array(
		'type'        => 'bug',
		'title'       => '  <b></b>  ',
		'description' => '   ',
		'email'       => '',
	),
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
check('empty spam skips github', array() === $calls);

$calls = array();
$limited = Choir_Rehearsal_Feedback::submit_feedback(
	array(
		'type'        => 'wish',
		'title'       => 'Loop a section',
		'description' => 'Please let us loop between two markers.',
		'email'       => '',
	),
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
check('rate limit skips github', array() === $calls);

$calls = array();
$saved = array();
$sent = Choir_Rehearsal_Feedback::submit_feedback(
	array(
		'type'        => 'bug',
		'title'       => "<script>alert(1)</script> Player <b>stops</b>",
		'description' => "It stops.\n<script>nope</script><img src=x onerror=alert(1)>",
		'email'       => 'not-an-email',
	),
	context(array('role' => 'guest', 'repo' => 'not a repo', 'locale' => 'et')),
	static fn(string $key): array => array(),
	static function (string $key, array $stamps) use (&$saved): void {
		$saved[$key] = $stamps;
	},
	static function (string $method, string $url, array $payload, string $sent_token) use (&$calls, $token): array {
		$calls[] = compact('method', 'url', 'payload', 'sent_token');
		return array(
			'code'    => 201,
			'message' => 'Created',
			'data'    => array(
				'number'   => 15,
				'html_url' => 'https://github.com/compathee/compathee/issues/15',
			),
		);
	}
);
check('fallback repo url', isset($calls[0]['url']) && str_contains($calls[0]['url'], '/repos/compathee/compathee/issues'));
check('issue title prefix', isset($calls[0]['payload']['title']) && $calls[0]['payload']['title'] === '[Choir Rehearsal] Player stops');
$body = (string) ($calls[0]['payload']['body'] ?? '');
check('issue body metadata', str_contains($body, 'Type: Bug') && str_contains($body, 'Role: guest') && str_contains($body, 'Plugin version: 0.4.61') && str_contains($body, 'WordPress version: 6.8') && str_contains($body, 'PHP version: 8.3.6') && str_contains($body, 'Site: https://choir.example/') && str_contains($body, 'Locale: et'));
check('invalid email omitted', str_contains($body, 'Contact: (not provided)'));
check('issue body has no html', !str_contains($body, '<') && !str_contains($body, '>') && !str_contains($body, 'onerror') && str_contains($body, 'It stops.'));
check('bug labels', ($calls[0]['payload']['labels'] ?? array()) === array('feedback', 'bug'));
check('token stays out of payload', !str_contains(json_encode($calls[0]['payload']), $token));
check('success number and url', $sent['ok'] && 15 === $sent['number'] && 'https://github.com/compathee/compathee/issues/15' === $sent['url']);
check('success message', 'Thank you. Feedback sent as issue #15.' === $sent['message']);
check('rate bucket recorded', ($saved['u7'] ?? array()) === array(1_000_000));

$calls = array();
Choir_Rehearsal_Feedback::submit_feedback(
	array(
		'type'        => 'wish',
		'title'       => 'Slower countdown',
		'description' => 'A wish for a slower countdown before record.',
		'email'       => 'leader@example.com',
	),
	context(array('role' => 'Voice Leader')),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function (string $method, string $url, array $payload, string $sent_token) use (&$calls): array {
		$calls[] = $payload;
		return array(
			'code'    => 201,
			'message' => 'Created',
			'data'    => array('number' => 3),
		);
	}
);
check('wish labels', ($calls[0]['labels'] ?? array()) === array('feedback', 'enhancement'));
check('contact kept', str_contains((string) $calls[0]['body'], 'Contact: leader@example.com') && str_contains((string) $calls[0]['body'], 'Role: Voice Leader'));

$calls = array();
$labeled = Choir_Rehearsal_Feedback::create_issue(
	'compathee',
	'compathee',
	'[Choir Rehearsal] Missing label',
	"Type: Other\nRole: guest\n\nMessage:\nHello choir",
	array('feedback'),
	$token,
	static function (string $method, string $url, array $payload) use (&$calls): array {
		$calls[] = array('url' => $url, 'payload' => $payload);
		if (str_ends_with($url, '/labels')) {
			return array('code' => 201, 'message' => 'Created', 'data' => array('name' => 'feedback'));
		}
		if (1 === count($calls)) {
			return array('code' => 422, 'message' => 'Validation Failed label feedback is invalid', 'data' => array());
		}
		return array(
			'code'    => 201,
			'message' => 'Created',
			'data'    => array(
				'number'   => 8,
				'html_url' => 'https://evil.example/issues/8',
			),
		);
	}
);
check('creates missing label then issue', 3 === count($calls) && str_ends_with($calls[1]['url'], '/labels') && isset($calls[2]['payload']['labels']));
check('rejects non-github issue url', $labeled['ok'] && 8 === $labeled['number'] && '' === $labeled['url']);

$calls = array();
$unlabeled = Choir_Rehearsal_Feedback::create_issue(
	'compathee',
	'compathee',
	'[Choir Rehearsal] No label permission',
	'Message',
	array('feedback', 'bug'),
	$token,
	static function (string $method, string $url, array $payload) use (&$calls): array {
		$calls[] = array('url' => $url, 'payload' => $payload);
		if (str_ends_with($url, '/labels')) {
			return array('code' => 403, 'message' => 'Resource not accessible', 'data' => array());
		}
		if (1 === count($calls)) {
			return array('code' => 422, 'message' => 'label does not exist', 'data' => array());
		}
		return array(
			'code'    => 201,
			'message' => 'Created',
			'data'    => array(
				'number'   => 9,
				'html_url' => 'https://github.com/compathee/compathee/issues/9',
			),
		);
	}
);
check('skips labels when create is forbidden', $unlabeled['ok'] && 9 === $unlabeled['number']);
check('second issue has no labels', !isset($calls[count($calls) - 1]['payload']['labels']));

$calls = array();
$auth = Choir_Rehearsal_Feedback::create_issue(
	'compathee',
	'compathee',
	'[Choir Rehearsal] Bad token',
	'Message',
	array('feedback'),
	$token,
	static function () use (&$calls): array {
		$calls[] = 'once';
		return array('code' => 401, 'message' => 'Bad credentials ' . $GLOBALS['token'], 'data' => array());
	}
);
check('auth failure stops', !$auth['ok'] && 1 === count($calls));
check('auth message hides token', str_contains($auth['message'], 'check the GitHub token') && !str_contains($auth['message'], $token));

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
check('settings not edition gated', str_contains($admin, 'Choir_Rehearsal_Feedback::register_settings()') && str_contains($admin, 'Choir_Rehearsal_Feedback::render_settings_rows()'));
$settings_at = strpos($admin, 'Choir_Rehearsal_Feedback::render_settings_rows()');
$settings_next = false !== $settings_at ? substr($admin, $settings_at, 160) : '';
$register_at = strpos($admin, 'Choir_Rehearsal_Feedback::register_settings()');
$register_next = false !== $register_at ? substr($admin, $register_at, 140) : '';
check('settings shown for every distribution', str_contains($settings_next, 'uses_github_updater()') && str_contains($register_next, 'uses_github_updater()'));
check('no pro gate in feedback class', !str_contains($feedback, 'Choir_Rehearsal_Edition'));

$enqueue_start = strpos($feedback, 'function enqueue_assets');
$enqueue_end = strpos($feedback, 'function render_panel');
$enqueue = substr($feedback, (int) $enqueue_start, (int) $enqueue_end - (int) $enqueue_start);
$panel_start = strpos($feedback, 'function render_panel');
$panel_end = strpos($feedback, 'function render_settings_rows');
$panel = substr($feedback, (int) $panel_start, (int) $panel_end - (int) $panel_start);
check('front-end script has no token option', !str_contains($enqueue, 'OPTION_TOKEN') && !str_contains($enqueue, 'get_option') && !str_contains($panel, 'get_option'));
check('password field is blank', str_contains($feedback, 'type="password"') && str_contains($feedback, 'value=""'));
check('js has no token or html injection', !str_contains($js, 'innerHTML') && !str_contains($js, 'github_pat') && !str_contains($js, 'ghp_') && str_contains($js, 'textContent'));
check('uninstall removes feedback options', str_contains($uninstall, 'choir_rehearsal_feedback_github_token') && str_contains($uninstall, 'choir_rehearsal_feedback_github_repo'));

if (!class_exists('WP_User')) {
	class WP_User {
		public string $user_email = 'singer@example.com';
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
check('logged-in form prefill', str_contains($logged_in_html, 'value="singer@example.com"') && str_contains($logged_in_html, 'Send feedback'));
check('form posts to rest', str_contains($logged_in_html, 'action="https://choir.example/wp-json/choir-rehearsal/v1/feedback"') && str_contains($logged_in_html, 'name="_wpnonce"'));
check('rendered form has no token', !str_contains($logged_in_html, $token) && !str_contains($logged_in_html, 'choir_rehearsal_feedback_github_token') && !str_contains($logged_in_html, 'github_pat'));

$GLOBALS['choir_feedback_logged_in'] = false;
ob_start();
Choir_Rehearsal_Feedback::render_panel();
$guest_html = (string) ob_get_clean();
check('guest form still renders', str_contains($guest_html, 'name="email"') && str_contains($guest_html, 'value=""') && str_contains($guest_html, 'value="bug"') && str_contains($guest_html, 'value="wish"'));

$enqueue_at = strpos($feedback, 'function enqueue_assets');
$panel_fn = strpos($feedback, 'function render_panel');
$enqueue_src = (false !== $enqueue_at && false !== $panel_fn) ? substr($feedback, $enqueue_at, $panel_fn - $enqueue_at) : '';
check('front end has no license fields', !str_contains($enqueue_src, 'license') && !str_contains($js, 'license_key') && !str_contains($logged_in_html, 'License ID') && !str_contains($logged_in_html, 'sc_license'));

$secret = 'ZZZZ-SECRET-KEY-9999';
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
	array(
		'type' => 'bug',
		'title' => 'Pro player stops',
		'description' => 'Stops on the second verse.',
		'email' => '',
	),
	context(array('license' => $pro)),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function (string $method, string $url, array $payload, string $sent_token) use (&$calls): array {
		unset($method, $url, $sent_token);
		$calls[] = $payload;
		return array(
			'code' => 201,
			'message' => 'Created',
			'data' => array('number' => 21, 'html_url' => 'https://github.com/compathee/compathee/issues/21'),
		);
	}
);
$pro_body = (string) ($calls[0]['body'] ?? '');
check('pro title marker', ($calls[0]['title'] ?? '') === '[Choir Rehearsal][Pro] Pro player stops');
check('pro label', ($calls[0]['labels'] ?? array()) === array('feedback', 'bug', 'pro'));
check(
	'pro identifier',
	str_contains($pro_body, 'License: active')
	&& str_contains($pro_body, 'License ID: li_priority_1')
	&& str_contains($pro_body, 'Customer ID: cus_buyer_1')
	&& str_contains($pro_body, 'Purchase ID: pur_sale_1')
	&& str_contains($pro_body, 'Activation ID: act_site_1')
	&& str_contains($pro_body, 'Site: https://choir.example/')
);
check('pro body hides full key', !str_contains($pro_body, $secret) && !str_contains($pro_body, 'SECRET') && !str_contains($pro_body, 'License key:'));

$key_only = 'ABCD1234WXYZ5678';
$masked = Choir_Rehearsal_Feedback::license_snapshot(true, array(
	'sc_license_status' => 'active',
	'sc_license_key' => $key_only,
));
$masked_body = Choir_Rehearsal_Feedback::issue_body(array(
	'type' => 'bug',
	'email' => '',
	'role' => 'Singer',
	'plugin_version' => '0.4.61',
	'wp_version' => '6.8',
	'php_version' => '8.3.6',
	'site_url' => 'https://choir.example/',
	'description' => 'Masked key only.',
	'license' => $masked,
));
check('masked key when no public id', str_contains($masked_body, 'License key: ABCD********5678') && !str_contains($masked_body, $key_only) && !str_contains($masked_body, '1234WXYZ'));

$lite = Choir_Rehearsal_Feedback::license_snapshot(false, array('sc_license_key' => $secret));
$calls = array();
Choir_Rehearsal_Feedback::submit_feedback(
	array(
		'type' => 'wish',
		'title' => 'Lite wish',
		'description' => 'A wish from Lite.',
		'email' => '',
	),
	context(array('license' => $lite)),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function (string $method, string $url, array $payload, string $sent_token) use (&$calls): array {
		unset($method, $url, $sent_token);
		$calls[] = $payload;
		return array('code' => 201, 'message' => 'Created', 'data' => array('number' => 22));
	}
);
$lite_body = (string) ($calls[0]['body'] ?? '');
check('lite has no pro label', ($calls[0]['labels'] ?? array()) === array('feedback', 'enhancement'));
check('lite title has no marker', ($calls[0]['title'] ?? '') === '[Choir Rehearsal] Lite wish');
check('lite license line', str_contains($lite_body, 'License: Lite') && !str_contains($lite_body, $secret) && !str_contains($lite_body, '[Pro]'));

$expired = Choir_Rehearsal_Feedback::license_snapshot(true, array(
	'sc_license_status' => 'expired',
	'sc_license_id' => 'li_old',
	'sc_license_key' => $secret,
));
check('expired is not pro', !$expired['pro'] && 'expired' === $expired['status']);
$expired_body = Choir_Rehearsal_Feedback::issue_body(array(
	'type' => 'bug',
	'email' => '',
	'role' => 'Singer',
	'plugin_version' => '0.4.61',
	'wp_version' => '6.8',
	'php_version' => '8.3.6',
	'site_url' => 'https://choir.example/',
	'description' => 'Expired license.',
	'license' => $expired,
));
check('expired body', str_contains($expired_body, 'License: expired') && str_contains($expired_body, 'License ID: li_old') && !str_contains($expired_body, $secret));
check('expired title', Choir_Rehearsal_Feedback::issue_title('Expired', $expired['pro']) === '[Choir Rehearsal] Expired');
check('expired labels', Choir_Rehearsal_Feedback::labels_for_type('bug', $expired['pro']) === array('feedback', 'bug'));

$calls = array();
Choir_Rehearsal_Feedback::create_issue(
	'compathee',
	'compathee',
	'[Choir Rehearsal][Pro] Missing pro label',
	"License: active\n",
	array('feedback', 'bug', 'pro'),
	$token,
	static function (string $method, string $url, array $payload) use (&$calls): array {
		unset($method);
		$calls[] = array('url' => $url, 'payload' => $payload);
		if (str_ends_with($url, '/labels')) {
			return array('code' => 201, 'message' => 'Created', 'data' => array('name' => $payload['name'] ?? ''));
		}
		if (1 === count($calls)) {
			return array('code' => 422, 'message' => 'Validation Failed label pro is invalid', 'data' => array());
		}
		return array(
			'code' => 201,
			'message' => 'Created',
			'data' => array('number' => 23, 'html_url' => 'https://github.com/compathee/compathee/issues/23'),
		);
	}
);
$created_names = array();
foreach ($calls as $call) {
	if (isset($call['payload']['name']) && is_string($call['payload']['name'])) {
		$created_names[] = $call['payload']['name'];
	}
}
check('pro label is created when missing', in_array('pro', $created_names, true) && in_array('feedback', $created_names, true));

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
	array(
		'type' => 'other',
		'title' => 'Guest note',
		'description' => 'Sent without a locale field.',
		'email' => '',
	),
	context(array()),
	static fn(string $key): array => array(),
	static function (): void {
	},
	static function (string $method, string $url, array $payload, string $sent_token) use (&$calls): array {
		unset($method, $url, $sent_token);
		$calls[] = $payload;
		return array('code' => 201, 'message' => 'Created', 'data' => array('number' => 24));
	}
);
check('guest issue uses site locale', str_contains((string) ($calls[0]['body'] ?? ''), 'Locale: en_US'));
check('locale is not a request field', !str_contains($js, 'locale') && !str_contains($feedback, "get_param( 'locale'"));

exit($fail > 0 ? 1 : 0);
