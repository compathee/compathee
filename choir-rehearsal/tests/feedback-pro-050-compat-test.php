<?php
/**
 * Feedback license lookup must not fatal on Pro 0.5.0 (is_licensed only).
 *
 * Run: php choir-rehearsal/tests/feedback-pro-050-compat-test.php
 */

declare(strict_types=1);

$fail = 0;
$feedback = dirname(__DIR__) . '/includes/class-feedback.php';

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

/**
 * @return array<string, mixed>
 */
function run_feedback(string $feedback, string $setup): array {
	$code = "define('ABSPATH', '1');\n" . $setup . "\nrequire " . var_export($feedback, true) . ";\n"
		. "try {\n"
		. "  \$snap = Choir_Rehearsal_Feedback::current_license();\n"
		. "  echo json_encode(array('ok' => true, 'snap' => \$snap, 'refreshed' => !empty(\$GLOBALS['refreshed'])));\n"
		. "} catch (Throwable \$e) {\n"
		. "  echo json_encode(array('ok' => false, 'error' => \$e->getMessage()));\n"
		. "}\n";

	$spec = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$proc = proc_open(array(PHP_BINARY, '-r', $code), $spec, $pipes);
	if (!is_resource($proc)) {
		return array('ok' => false, 'error' => 'proc_open failed');
	}
	fclose($pipes[0]);
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	$decoded = json_decode((string) $out, true);
	if (!is_array($decoded)) {
		return array('ok' => false, 'error' => trim((string) $out . "\n" . $err));
	}
	return $decoded;
}

$pro_050 = <<<'PHP'
class Choir_Rehearsal_Pro_Licensing {
	public static function is_licensed(): bool {
		return false;
	}
}
function get_option($key, $default = array()) {
	if ('compathchoirrehearsalpro_license_options' === $key) {
		return array(
			'sc_activation_id' => 'act_site_1',
			'sc_license_key' => 'ABCD1234WXYZ5678',
		);
	}
	return $default;
}
PHP;

$unlicensed = run_feedback($feedback, str_replace('return false;', 'return false;', $pro_050));
check(
	'pro 0.5.0 lookup does not fatal',
	($unlicensed['ok'] ?? false) === true
);
if (!($unlicensed['ok'] ?? false)) {
	echo '  got: ' . json_encode($unlicensed) . "\n";
}

$licensed_setup = str_replace('return false;', 'return true;', $pro_050);
$licensed = run_feedback($feedback, $licensed_setup);
$snap = is_array($licensed['snap'] ?? null) ? $licensed['snap'] : array();
check(
	'pro 0.5.0 active license is read from the SureCart option',
	($licensed['ok'] ?? false) === true
		&& ($snap['pro'] ?? null) === true
		&& ($snap['activation_id'] ?? '') === 'act_site_1'
		&& ($snap['license_key'] ?? '') === 'ABCD1234WXYZ5678'
);
if (!(($licensed['ok'] ?? false) === true && ($snap['pro'] ?? null) === true)) {
	echo '  got: ' . json_encode($licensed) . "\n";
}

$newer = <<<'PHP'
class Choir_Rehearsal_Pro_Licensing {
	public static function is_licensed(): bool {
		return true;
	}
	public static function refresh_cached_details(): void {
		$GLOBALS['refreshed'] = true;
	}
	public static function stored_options(): array {
		return array(
			'sc_license_status' => 'active',
			'sc_activation_id' => 'act_from_method',
			'sc_license_key' => 'NEWERKEY12345678',
		);
	}
}
function get_option($key, $default = array()) {
	return array('sc_activation_id' => 'act_from_option');
}
PHP;
$newer_result = run_feedback($feedback, $newer);
$newer_snap = is_array($newer_result['snap'] ?? null) ? $newer_result['snap'] : array();
check(
	'newer pro uses stored_options and refreshes details',
	($newer_result['ok'] ?? false) === true
		&& ($newer_result['refreshed'] ?? false) === true
		&& ($newer_snap['activation_id'] ?? '') === 'act_from_method'
);
if (($newer_snap['activation_id'] ?? '') !== 'act_from_method') {
	echo '  got: ' . json_encode($newer_result) . "\n";
}

exit($fail > 0 ? 1 : 0);
