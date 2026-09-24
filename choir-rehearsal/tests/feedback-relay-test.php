<?php
/**
 * Jira and SMTP are mocked. No network.
 *
 * Run: php choir-rehearsal/tests/feedback-relay-test.php
 */

declare(strict_types=1);

define('CHOIR_FEEDBACK_RELAY_LIBRARY', true);

require dirname(__DIR__) . '/docs/deploy/api/feedback.php';

$fail = 0;
$secret_token = 'jira-api-token-SECRET-VALUE';
$smtp_pass = 'smtp-password-SECRET-VALUE';
$full_key = 'ABCD1234WXYZ5678';

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

function config(): array {
	return array(
		'jira_base' => 'https://compath.atlassian.net',
		'jira_email' => 'dev@compath.ee',
		'jira_token' => $GLOBALS['secret_token'],
		'jira_project' => 'WP',
		'jira_issue_type' => 'Task',
		'smtp_host' => 'compath.ee',
		'smtp_port' => 465,
		'smtp_user' => 'compath@compath.ee',
		'smtp_pass' => $GLOBALS['smtp_pass'],
		'from_email' => 'support@compath.ee',
		'from_name' => 'Compath Support',
		'reply_to' => 'support@compath.ee',
		'rate_limit' => 2,
		'rate_window' => 3600,
	);
}

function payload(array $overrides = array()): string {
	$data = array_merge(
		array(
			'type' => 'bug',
			'title' => 'Player stops',
			'message' => 'The sticky player stops after one track.',
			'name' => 'Ada',
			'email' => 'singer@example.com',
			'locale' => 'en_US',
			'site_url' => 'https://choir.example/',
			'role' => 'Singer',
			'edition' => 'Lite',
			'plugin_version' => '0.4.61',
			'wp_version' => '6.8',
			'php_version' => '8.3.6',
			'pro' => false,
			'license' => array('status' => 'Lite'),
			'company' => '',
		),
		$overrides
	);

	return (string) json_encode($data);
}

/**
 * @return array{0: array{status:int, body:array<string,mixed>}, 1: list<array<string,mixed>>, 2: list<array<string,mixed>>, 3: list<string>}
 */
function post(string $raw, ?callable $jira = null, ?callable $mail = null, ?callable $rate = null): array {
	$jira_calls = array();
	$mail_calls = array();
	$logs = array();
	$jira ??= static function (string $method, string $url, array $body, string $user, string $token) use (&$jira_calls): array {
		$jira_calls[] = compact('method', 'url', 'body', 'user', 'token');
		return array('code' => 201, 'body' => '{"key":"WP-57"}');
	};
	$mail ??= static function (array $message) use (&$mail_calls): bool {
		$mail_calls[] = $message;
		return true;
	};
	$result = Choir_Feedback_Relay::handle(
		'POST',
		$raw,
		config(),
		'203.0.113.10',
		1_700_000_000,
		static function (string $method, string $url, array $body, string $user, string $token) use (&$jira_calls, $jira): array {
			$jira_calls[] = compact('method', 'url', 'body', 'user', 'token');
			return $jira($method, $url, $body, $user, $token);
		},
		static function (array $message) use (&$mail_calls, $mail): bool {
			$mail_calls[] = $message;
			return $mail($message);
		},
		static function (string $line) use (&$logs): void {
			$logs[] = $line;
		},
		$rate
	);

	return array($result, $jira_calls, $mail_calls, $logs);
}

$get = Choir_Feedback_Relay::handle('GET', payload(), config(), '203.0.113.10', 1, static fn(): array => array('code' => 500, 'body' => ''), static fn(): bool => false, null, static fn(): bool => true);
check('get rejected', 405 === $get['status'] && false === $get['body']['ok']);

$big = Choir_Feedback_Relay::handle('POST', str_repeat('a', Choir_Feedback_Relay::MAX_BODY + 1), config(), '203.0.113.10', 1, static fn(): array => array('code' => 201, 'body' => '{"key":"WP-1"}'), static fn(): bool => true, null, static fn(): bool => true);
check('oversized rejected', 413 === $big['status']);

[$honey] = post(payload(array('company' => 'Acme')));
check('honeypot rejected', 400 === $honey['status'] && 'invalid' === $honey['body']['error']);

[$bad_email, $jira_calls] = post(payload(array('email' => 'not-an-email')));
check('email required', 400 === $bad_email['status'] && array() === $jira_calls);

[$limited, $jira_calls] = post(payload(), null, null, static fn(): bool => false);
check('rate limit', 429 === $limited['status'] && 'rate_limited' === $limited['body']['error'] && array() === $jira_calls);

[$lite, $jira_calls, $mail_calls] = post(payload());
$fields = $jira_calls[0]['body']['fields'] ?? array();
check('lite summary', ($fields['summary'] ?? '') === '[CR] Player stops');
check('lite labels', ($fields['labels'] ?? array()) === array('choir-rehearsal', 'feedback'));
check('jira project and type', ($fields['project']['key'] ?? '') === 'WP' && ($fields['issuetype']['name'] ?? '') === 'Task');
check('jira url', str_contains((string) ($jira_calls[0]['url'] ?? ''), 'https://compath.atlassian.net/rest/api/3/issue'));
$description = '';
foreach (($fields['description']['content'][0]['content'] ?? array()) as $node) {
	if (isset($node['text']) && is_string($node['text'])) {
		$description .= $node['text'] . "\n";
	}
}
check('english metadata', str_contains($description, 'Type: Bug') && str_contains($description, 'Role: Singer') && str_contains($description, 'License: Lite') && str_contains($description, 'Locale: en_US') && str_contains($description, 'The sticky player stops'));
check('lite response', 200 === $lite['status'] && true === $lite['body']['ok'] && 'WP-57' === $lite['body']['case']);
check('english mail', str_contains((string) $mail_calls[0]['body'], 'Case number: WP-57') && str_contains((string) $mail_calls[0]['body'], 'We will write back when a fix ships.') && str_contains((string) $mail_calls[0]['body'], 'The sticky player stops') && ($mail_calls[0]['from_email'] ?? '') === 'support@compath.ee' && ($mail_calls[0]['reply_to'] ?? '') === 'support@compath.ee');
check('secrets stay out of the response', !str_contains((string) json_encode($lite), $secret_token) && !str_contains((string) json_encode($lite), $smtp_pass));

[$pro, $jira_calls] = post(payload(array(
	'title' => 'Pro player stops',
	'message' => 'Stops on the second verse.',
	'edition' => 'Pro',
	'pro' => true,
	'pro_version' => '0.5.0',
	'license' => array(
		'status' => 'active',
		'license_id' => 'li_priority_1',
		'customer_id' => 'cus_buyer_1',
		'purchase_id' => 'pur_sale_1',
		'activation_id' => 'act_site_1',
		'license_key' => $full_key,
	),
)));
$fields = $jira_calls[0]['body']['fields'] ?? array();
$description = '';
foreach (($fields['description']['content'][0]['content'] ?? array()) as $node) {
	if (isset($node['text']) && is_string($node['text'])) {
		$description .= $node['text'] . "\n";
	}
}
check('pro summary', ($fields['summary'] ?? '') === '[CR][Pro] Pro player stops');
check('pro labels', ($fields['labels'] ?? array()) === array('choir-rehearsal', 'feedback', 'pro'));
check('pro ids and no full key', str_contains($description, 'License ID: li_priority_1') && str_contains($description, 'Customer ID: cus_buyer_1') && str_contains($description, 'Pro add-on version: 0.5.0') && !str_contains($description, $full_key) && !str_contains($description, 'License key:'));

[$masked, $jira_calls] = post(payload(array(
	'pro' => true,
	'edition' => 'Pro',
	'license' => array(
		'status' => 'active',
		'license_key' => $full_key,
	),
)));
$description = '';
foreach (($jira_calls[0]['body']['fields']['description']['content'][0]['content'] ?? array()) as $node) {
	if (isset($node['text']) && is_string($node['text'])) {
		$description .= $node['text'] . "\n";
	}
}
check('masked key fallback', str_contains($description, 'License key: ABCD********5678') && !str_contains($description, $full_key) && !str_contains($description, '1234WXYZ'));

[$expired, $jira_calls] = post(payload(array(
	'pro' => true,
	'license' => array('status' => 'expired', 'license_id' => 'li_old', 'license_key' => $full_key),
)));
$fields = $jira_calls[0]['body']['fields'] ?? array();
check('expired is not pro', ($fields['summary'] ?? '') === '[CR] Player stops' && ($fields['labels'] ?? array()) === array('choir-rehearsal', 'feedback'));

$label_calls = array();
[$labeled] = post(
	payload(array('pro' => true)),
	static function (string $method, string $url, array $body) use (&$label_calls): array {
		unset($method, $url);
		$label_calls[] = $body;
		if (1 === count($label_calls)) {
			return array('code' => 400, 'body' => '{"errors":{"labels":"label pro is invalid"}}');
		}
		return array('code' => 201, 'body' => '{"key":"WP-58"}');
	}
);
check('retries without labels', 2 === count($label_calls) && isset($label_calls[0]['fields']['labels']) && !isset($label_calls[1]['fields']['labels']));
check('label retry still returns the key', 'WP-58' === $labeled['body']['case']);

$logs = array();
[$mailed, , $mail_calls, $logs] = post(
	payload(array('locale' => 'et_EE', 'message' => 'Mängija jäi seisma.')),
	null,
	static function (): bool {
		return false;
	}
);
check('estonian confirmation', str_contains((string) $mail_calls[0]['subject'], 'WP-57') && str_contains((string) $mail_calls[0]['body'], 'Juhtumi number: WP-57') && str_contains((string) $mail_calls[0]['body'], 'Mängija jäi seisma.') && str_contains((string) $mail_calls[0]['body'], 'kui parandus on valmis'));
check('mail failure still succeeds', true === $mailed['body']['ok'] && 'WP-57' === $mailed['body']['case'] && str_contains(implode("\n", $logs), 'confirmation email failed for WP-57'));
check('mail log hides secrets', !str_contains(implode("\n", $logs), $secret_token) && !str_contains(implode("\n", $logs), $smtp_pass));

[, , $mail_calls] = post(payload(array('locale' => 'ru_RU', 'message' => 'Плеер остановился.')));
check('russian confirmation', str_contains((string) $mail_calls[0]['body'], 'Номер обращения: WP-57') && str_contains((string) $mail_calls[0]['body'], 'Плеер остановился.') && str_contains((string) $mail_calls[0]['body'], 'когда исправление будет готово'));

$down = Choir_Feedback_Relay::handle(
	'POST',
	payload(),
	config(),
	'203.0.113.10',
	1,
	static fn(): array => array('code' => 401, 'body' => 'Basic ' . $GLOBALS['secret_token']),
	static fn(): bool => true,
	null,
	static fn(): bool => true
);
check('jira failure hides the token', 502 === $down['status'] && 'unavailable' === $down['body']['error'] && !str_contains((string) json_encode($down), $secret_token));

$store = sys_get_temp_dir() . '/choir-feedback-rate-' . bin2hex(random_bytes(4));
check('first ip allowed', Choir_Feedback_Relay::consume_rate($store, '203.0.113.10', 100, 2, 3600));
check('second ip allowed', Choir_Feedback_Relay::consume_rate($store, '203.0.113.10', 101, 2, 3600));
check('third ip blocked', !Choir_Feedback_Relay::consume_rate($store, '203.0.113.10', 102, 2, 3600));
check('other ip allowed', Choir_Feedback_Relay::consume_rate($store, '203.0.113.11', 102, 2, 3600));

$sent = array();
$smtp_ok = Choir_Feedback_Relay::smtp_converse(
	static function (string $send) use (&$sent): string {
		$sent[] = $send;
		if ('' === $send) {
			return "220 compath.ee ESMTP\r\n";
		}
		if (str_starts_with($send, 'EHLO')) {
			return "250-compath.ee\r\n250 AUTH LOGIN\r\n";
		}
		if ('AUTH LOGIN' . "\r\n" === $send) {
			return "334 VXNlcm5hbWU6\r\n";
		}
		if (base64_encode('compath@compath.ee') . "\r\n" === $send) {
			return "334 UGFzc3dvcmQ6\r\n";
		}
		if (base64_encode($GLOBALS['smtp_pass']) . "\r\n" === $send) {
			return "235 ok\r\n";
		}
		if (str_starts_with($send, 'MAIL FROM:<support@compath.ee>')) {
			return "250 ok\r\n";
		}
		if (str_starts_with($send, 'RCPT TO:<singer@example.com>')) {
			return "250 ok\r\n";
		}
		if ("DATA\r\n" === $send) {
			return "354 go\r\n";
		}
		if (str_contains($send, "\r\n.\r\n")) {
			return "250 queued\r\n";
		}
		return "250 bye\r\n";
	},
	config(),
	Choir_Feedback_Relay::confirmation(
		array('locale' => 'ru', 'message' => 'Плеер остановился.', 'email' => 'singer@example.com'),
		'WP-57',
		config()
	)
);
$data = implode("\n", $sent);
check('smtp dialogue', $smtp_ok);
check('smtp from and reply-to', str_contains($data, 'From: Compath Support <support@compath.ee>') && str_contains($data, 'Reply-To: <support@compath.ee>'));
check('smtp auth login', str_contains($data, "AUTH LOGIN\r\n") && str_contains($data, base64_encode('compath@compath.ee')));
check('russian subject is encoded', str_contains($data, '=?UTF-8?B?') && str_contains($data, 'Плеер остановился.'));

$example = (string) file_get_contents(dirname(__DIR__) . '/docs/deploy/api/config.example.php');
check('example config has empty secrets', str_contains($example, "'jira_project'    => 'WP'") && str_contains($example, "'jira_token'      => ''") && str_contains($example, "'smtp_pass'       => ''") && !str_contains($example, $secret_token));
check('two-letter case key', 'WP-12' === Choir_Feedback_Relay::issue_key(array('code' => 201, 'body' => '{"key":"WP-12"}')));

$workflow = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/deploy-rehearsal-site.yml');
check('workflow publishes the relay', str_contains($workflow, 'deploy/api/feedback.php') && str_contains($workflow, 'api/feedback.php'));

exit($fail > 0 ? 1 : 0);
