<?php
/**
 * Pro feature gates follow the license filter, even when the Pro add-on defines CHOIR_REHEARSAL_PRO.
 *
 * Run: php choir-rehearsal/tests/license-gate-test.php
 */

declare(strict_types=1);

$fail = 0;
$edition = dirname(__DIR__) . '/includes/class-edition.php';

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

/**
 * @return array{exit:int, out:string}
 */
function run_edition(string $edition, string $prelude): array {
	$code = $prelude . "\nrequire " . var_export($edition, true) . ";\n"
		. "echo json_encode(array(\n"
		. "  'pro' => Choir_Rehearsal_Edition::is_pro(),\n"
		. "  'record' => Choir_Rehearsal_Edition::can_record(),\n"
		. "  'search' => Choir_Rehearsal_Edition::can_search_songs(),\n"
		. "  'play' => Choir_Rehearsal_Edition::can_play_in_editor(),\n"
		. "  'badge' => Choir_Rehearsal_Edition::can_show_pdf_badge(),\n"
		. "  'score' => Choir_Rehearsal_Edition::can_view_score_in_editor(),\n"
		. "  'tracks' => Choir_Rehearsal_Edition::max_tracks(),\n"
		. "  'upgrade' => Choir_Rehearsal_Edition::shows_commercial_upgrade(),\n"
		. "));\n";

	$spec = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$proc = proc_open(array(PHP_BINARY, '-r', $code), $spec, $pipes);
	if (!is_resource($proc)) {
		return array('exit' => 1, 'out' => '');
	}
	fclose($pipes[0]);
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($proc);
	if ('' !== $err) {
		$out .= "\n" . $err;
	}
	return array('exit' => $exit, 'out' => (string) $out);
}

function prelude(bool $define_pro, ?bool $filter): string {
	$lines = array(
		"define('ABSPATH', '1');",
		"function __(\$text, \$domain = 'default') { return \$text; }",
		"function apply_filters(\$tag, \$value) {",
		"  if ('choir_rehearsal_is_pro' === \$tag && array_key_exists('choir_license_filter', \$GLOBALS)) {",
		"    return (bool) \$GLOBALS['choir_license_filter'];",
		"  }",
		"  return \$value;",
		"}",
	);
	if (null !== $filter) {
		$lines[] = '$GLOBALS[\'choir_license_filter\'] = ' . ($filter ? 'true' : 'false') . ';';
	}
	if ($define_pro) {
		$lines[] = "define('CHOIR_REHEARSAL_PRO', true);";
	}
	return implode("\n", $lines);
}

/**
 * @param array<string, mixed> $expected
 */
function expect_gate(string $name, string $edition, string $prelude, array $expected): void {
	$result = run_edition($edition, $prelude);
	$decoded = json_decode($result['out'], true);
	$ok = 0 === $result['exit'] && is_array($decoded);
	if ($ok) {
		foreach ($expected as $key => $value) {
			if (!array_key_exists($key, $decoded) || $decoded[$key] !== $value) {
				$ok = false;
				break;
			}
		}
	}
	check($name, $ok);
	if (!$ok) {
		echo "  got: " . trim($result['out']) . "\n";
	}
}

expect_gate(
	'unlicensed pro add-on keeps pro features off',
	$edition,
	prelude(true, false),
	array(
		'pro' => false,
		'record' => false,
		'search' => false,
		'play' => false,
		'badge' => false,
		'score' => true,
		'tracks' => 4,
		'upgrade' => true,
	)
);

expect_gate(
	'licensed pro add-on unlocks pro features',
	$edition,
	prelude(true, true),
	array(
		'pro' => true,
		'record' => true,
		'search' => true,
		'play' => true,
		'badge' => true,
		'score' => true,
		'tracks' => 0,
		'upgrade' => false,
	)
);

expect_gate(
	'pro constant without a license filter stays unlocked',
	$edition,
	prelude(true, null),
	array(
		'pro' => true,
		'record' => true,
		'tracks' => 0,
	)
);

expect_gate(
	'lite without pro stays limited',
	$edition,
	prelude(false, null),
	array(
		'pro' => false,
		'record' => false,
		'tracks' => 4,
		'score' => true,
	)
);

exit($fail > 0 ? 1 : 0);
