<?php
/**
 * Feedback form follows et and ru_RU catalogs.
 *
 * Run: php choir-rehearsal/tests/feedback-i18n-test.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['choir_catalog'] = array('messages' => array());

function choir_i18n_lookup(string $text): string {
	$messages = $GLOBALS['choir_catalog']['messages'] ?? array();
	$translated = $messages[$text] ?? $text;
	if (is_array($translated)) {
		return (string) ($translated[0] ?? $text);
	}

	return (string) $translated;
}

function __(string $text, string $domain = ''): string {
	unset($domain);
	return choir_i18n_lookup($text);
}

function esc_html(string $text): string {
	return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr(string $text): string {
	return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_html_e(string $text, string $domain = ''): void {
	unset($domain);
	echo esc_html(choir_i18n_lookup($text));
}

function esc_attr_e(string $text, string $domain = ''): void {
	unset($domain);
	echo esc_attr(choir_i18n_lookup($text));
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
	return false;
}

function sanitize_email(string $email): string {
	return $email;
}

function is_email(string $email): bool {
	return str_contains($email, '@');
}

require dirname(__DIR__) . '/includes/class-feedback.php';

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

function load_catalog(string $locale): void {
	$path = dirname(__DIR__) . '/languages/compath-choir-rehearsal-' . $locale . '.l10n.php';
	$GLOBALS['choir_catalog'] = include $path;
}

function render_form(): string {
	ob_start();
	Choir_Rehearsal_Feedback::render_panel();
	return (string) ob_get_clean();
}

/**
 * @return array{ok: bool, status: int, message: string, number: int, url: string, code: string}
 */
function submit_with(array $input, array $context): array {
	return Choir_Rehearsal_Feedback::submit_feedback(
		$input,
		$context,
		static fn(string $key): array => $context['buckets'] ?? array(),
		static function (string $key, array $stamps): void {
			unset($key, $stamps);
		},
		static function (): array {
			return array(
				'code' => 201,
				'message' => 'Created',
				'data' => array(
					'number' => 12,
					'html_url' => 'https://github.com/compathee/compathee/issues/12',
				),
			);
		}
	);
}

$base_input = array(
	'type' => 'bug',
	'title' => 'Player stops',
	'description' => 'The sticky player stops after one track.',
	'email' => '',
);
$base_context = array(
	'token' => 'github_pat_TESTTOKENVALUE123456',
	'repo' => 'compathee/compathee',
	'role' => 'Singer',
	'plugin_version' => '0.4.61',
	'wp_version' => '6.8',
	'php_version' => '8.3.6',
	'site_url' => 'https://choir.example/',
	'now' => 1_000_000,
	'rate_keys' => array('u1'),
);

foreach (array(
	'et' => array(
		'button' => 'Saada tagasiside',
		'bug' => 'Viga',
		'wish' => 'Soov',
		'other' => 'Muu',
		'title' => 'Pealkiri',
		'summary' => 'Lühike kokkuvõte',
		'wish_for' => 'Mis juhtus või mida soovid',
		'not_configured' => 'Tagasiside ei ole seadistatud.',
		'wait' => 'Oota enne järgmise tagasiside saatmist.',
		'sent' => 'Aitäh. Tagasiside on saadetud teemana #12.',
	),
	'ru_RU' => array(
		'button' => 'Отправить отзыв',
		'bug' => 'Ошибка',
		'wish' => 'Пожелание',
		'other' => 'Другое',
		'title' => 'Заголовок',
		'summary' => 'Краткое описание',
		'wish_for' => 'Что случилось или какое у вас пожелание',
		'not_configured' => 'Форма отзывов не настроена.',
		'wait' => 'Подождите перед отправкой следующего отзыва.',
		'sent' => 'Спасибо. Отзыв отправлен как задача #12.',
	),
) as $locale => $expect) {
	load_catalog($locale);
	$html = render_form();
	check("$locale button", str_contains($html, $expect['button']) && !str_contains($html, '>Send feedback<'));
	check("$locale type options", str_contains($html, $expect['bug']) && str_contains($html, $expect['wish']) && str_contains($html, $expect['other']));
	check("$locale labels", str_contains($html, $expect['title']));
	check("$locale placeholders", str_contains($html, $expect['summary']) && str_contains($html, $expect['wish_for']));
	check("$locale keeps product name", str_contains($html, 'Choir Rehearsal'));

	$missing = submit_with($base_input, array_merge($base_context, array('token' => '')));
	check("$locale not configured", $missing['message'] === $expect['not_configured'] && 'not_configured' === $missing['code']);

	$limited = submit_with($base_input, array_merge($base_context, array('buckets' => array(999_000, 999_100, 999_200, 999_300, 999_400))));
	check("$locale rate limit", $limited['message'] === $expect['wait'] && 'rate_limited' === $limited['code']);

	$sent = submit_with($base_input, $base_context);
	check("$locale issue created", $sent['message'] === $expect['sent'] && 12 === $sent['number']);

	$body = Choir_Rehearsal_Feedback::issue_body(array(
		'type' => 'bug',
		'email' => '',
		'role' => 'guest',
		'plugin_version' => '0.4.61',
		'wp_version' => '6.8',
		'php_version' => '8.3.6',
		'site_url' => 'https://choir.example/',
		'locale' => $locale,
		'description' => 'The player stops.',
	));
	check("$locale github body stays English", str_contains($body, 'Type: Bug') && str_contains($body, 'Role: guest') && str_contains($body, 'Message:') && str_contains($body, 'Locale: ' . $locale));
}

foreach (array('et', 'et_EE', 'ru_RU') as $locale) {
	$mo = dirname(__DIR__) . '/languages/compath-choir-rehearsal-' . $locale . '.mo';
	$bytes = is_file($mo) ? (string) file_get_contents($mo) : '';
	$needle = 'et' === $locale || 'et_EE' === $locale ? 'Saada tagasiside' : 'Отправить отзыв';
	check("$locale mo contains feedback label", str_contains($bytes, $needle));
}

$ru_mo = (string) file_get_contents(dirname(__DIR__) . '/languages/compath-choir-rehearsal-ru_RU.mo');
check('ru mo public badge', str_contains($ru_mo, 'Публичная — доступна без входа'));

exit($fail > 0 ? 1 : 0);
