<?php
/**
 * Choir role names stay English under et and ru_RU.
 *
 * Run: php choir-rehearsal/tests/role-names-i18n-test.php
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

function esc_html_e(string $text, string $domain = ''): void {
	unset($domain);
	echo esc_html(choir_i18n_lookup($text));
}

require dirname(__DIR__) . '/includes/class-roles.php';

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

$roles_src = (string) file_get_contents(dirname(__DIR__) . '/includes/class-roles.php');
check('singer slug unchanged', str_contains($roles_src, "const ROLE_SINGER       = 'choir_singer'"));
check('voice leader slug unchanged', str_contains($roles_src, "const ROLE_VOICE_LEADER = 'choir_voice_leader'"));
check('listen cap unchanged', str_contains($roles_src, "const CAP_LISTEN = 'choir_rehearsal_listen'"));
check('manage cap unchanged', str_contains($roles_src, "const CAP_MANAGE = 'choir_rehearsal_manage_songs'"));
check('add_role display name is not gettext', 1 === preg_match('/add_role\(\s*\$role_key,\s*\$display_name,\s*\$caps\s*\)/', $roles_src));
check(
	'role labels are english constants',
	Choir_Rehearsal_Roles::LABEL_SINGER === 'Singer'
	&& Choir_Rehearsal_Roles::LABEL_VOICE_LEADER === 'Voice Leader'
	&& Choir_Rehearsal_Roles::LABEL_ADMINISTRATOR === 'Administrator'
	&& Choir_Rehearsal_Roles::LABEL_ADMIN === 'Admin'
	&& Choir_Rehearsal_Roles::LABEL_GUEST === 'Guest'
);

$plugin_src = '';
foreach (array(
	'includes/class-roles.php',
	'includes/class-access.php',
	'includes/class-admin.php',
	'includes/class-frontend.php',
	'includes/class-feedback.php',
) as $relative) {
	$plugin_src .= (string) file_get_contents(dirname(__DIR__) . '/' . $relative);
}
check(
	'role names are not wrapped in gettext',
	1 !== preg_match("/__\\(\\s*'[^']*(Singer|Voice Leader|Administrator|\\bAdmin\\b|Guest|guests|administrator)[^']*'/", $plugin_src)
);

check(
	'user role dropdown keeps Administrator',
	'Administrator' === Choir_Rehearsal_Roles::keep_role_label_untranslated_context('Administraator', 'Administrator', 'User role', 'default')
);
check(
	'user role dropdown keeps Singer',
	'Singer' === Choir_Rehearsal_Roles::keep_role_label_untranslated_context('Laulja', 'Singer', 'User role', 'default')
);
check(
	'user role dropdown keeps Admin',
	'Admin' === Choir_Rehearsal_Roles::keep_role_label_untranslated_context('Админ', 'Admin', 'User role', 'default')
);
check(
	'user role dropdown keeps Guest',
	'Guest' === Choir_Rehearsal_Roles::keep_role_label_untranslated_context('Гость', 'Guest', 'User role', 'default')
);
check(
	'unrelated user-role string still translates',
	'Toimetaja' === Choir_Rehearsal_Roles::keep_role_label_untranslated_context('Toimetaja', 'Editor', 'User role', 'default')
);

$english_names = array('Singer', 'Voice Leader', 'Administrator', 'Guest');
$forbidden = array(
	'et' => array('administraator', 'külalis', 'laulja', 'häälejuht'),
	'ru_RU' => array('администратор', 'гост', 'певец', 'дириж'),
);

foreach (array('et', 'ru_RU') as $locale) {
	load_catalog($locale);
	$rendered = array(
		Choir_Rehearsal_Roles::describe_activation(),
		Choir_Rehearsal_Roles::describe_role_names_policy(),
		Choir_Rehearsal_Roles::describe_feedback_audience(),
		Choir_Rehearsal_Roles::ask_administrator_to_assign_library(),
		Choir_Rehearsal_Roles::ask_administrator_to_assign_song(),
		Choir_Rehearsal_Roles::ask_administrator_github_token(),
		Choir_Rehearsal_Roles::ask_administrator_github_repository(),
	);
	$blob = implode("\n", $rendered);
	$has_names = true;
	foreach ($english_names as $name) {
		if (!str_contains($blob, $name)) {
			$has_names = false;
		}
	}
	check("$locale renders English role names", $has_names);
	$clean = true;
	$blob_lower = mb_strtolower($blob);
	foreach ($forbidden[$locale] as $word) {
		if (str_contains($blob_lower, $word)) {
			$clean = false;
		}
	}
	check("$locale does not translate role names", $clean);

	$mo = dirname(__DIR__) . '/languages/compath-choir-rehearsal-' . $locale . '.mo';
	$bytes = is_file($mo) ? (string) file_get_contents($mo) : '';
	$needle = 'et' === $locale
		? 'Palu kasutajal rolliga %s kontrollida GitHubi tunnust.'
		: 'Попросите пользователя с ролью %s проверить токен GitHub.';
	check("$locale mo has untranslated role placeholder", str_contains($bytes, $needle));
}

exit($fail > 0 ? 1 : 0);
