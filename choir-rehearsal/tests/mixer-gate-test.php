<?php
/**
 * Multi-track mixer gate, markup, and catalogs.
 *
 * Run: php choir-rehearsal/tests/mixer-gate-test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root);
$fail = 0;

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
function run_php(string $code): array {
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

function edition_prelude(bool $define_pro, ?bool $filter, ?string $distribution): string {
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
	if (null !== $distribution) {
		$lines[] = "define('CHOIR_REHEARSAL_DISTRIBUTION', " . var_export($distribution, true) . ');';
	}
	return implode("\n", $lines);
}

/**
 * @param array<string, mixed> $expected
 */
function expect_edition(string $name, string $file, string $prelude, array $expected): void {
	$code = $prelude . "\nrequire " . var_export($file, true) . ";\n"
		. "echo json_encode(array(\n"
		. "  'pro' => Choir_Rehearsal_Edition::is_pro(),\n"
		. "  'demo' => Choir_Rehearsal_Edition::is_demo(),\n"
		. "  'mixer' => Choir_Rehearsal_Edition::can_use_mixer(),\n"
		. "  'record' => Choir_Rehearsal_Edition::can_record(),\n"
		. "  'tracks' => Choir_Rehearsal_Edition::max_tracks(),\n"
		. "  'upgrade' => Choir_Rehearsal_Edition::shows_commercial_upgrade(),\n"
		. "  'label' => Choir_Rehearsal_Edition::edition_label(),\n"
		. "));\n";
	$result = run_php($code);
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
		echo '  got: ' . trim($result['out']) . "\n";
	}
}

$edition = $root . '/includes/class-edition.php';
$wporg = $root . '/includes/edition-wporg.php';
$frontend = (string) file_get_contents($root . '/includes/class-frontend.php');
$distribution = (string) file_get_contents($root . '/includes/class-distribution.php');
$bootstrap = (string) file_get_contents($root . '/choir-rehearsal.php');
$player = (string) file_get_contents($root . '/public/js/player.js');
$mixer = (string) file_get_contents($root . '/public/js/mixer.js');
$css = (string) file_get_contents($root . '/public/css/public.css');
$github_zip = (string) file_get_contents($repo . '/scripts/build-github-zip.sh');
$wporg_zip = (string) file_get_contents($repo . '/scripts/build-wporg-zip.sh');

expect_edition(
	'unlicensed pro add-on keeps the mixer off',
	$edition,
	edition_prelude(true, false, null),
	array(
		'pro' => false,
		'demo' => false,
		'mixer' => false,
		'record' => false,
		'tracks' => 4,
		'upgrade' => true,
		'label' => 'Lite',
	)
);

expect_edition(
	'licensed pro unlocks the mixer',
	$edition,
	edition_prelude(true, true, null),
	array(
		'mixer' => true,
		'record' => true,
		'tracks' => 0,
		'upgrade' => false,
		'label' => 'Pro',
	)
);

expect_edition(
	'demo channel unlocks the mixer without a license and keeps the track cap',
	$edition,
	edition_prelude(false, false, 'demo'),
	array(
		'pro' => false,
		'demo' => true,
		'mixer' => true,
		'record' => true,
		'tracks' => 10,
		'upgrade' => false,
		'label' => 'Demo',
	)
);

expect_edition(
	'wordpress.org edition includes the mixer and has no commercial gate',
	$wporg,
	edition_prelude(false, null, null),
	array(
		'pro' => false,
		'demo' => false,
		'mixer' => true,
		'record' => true,
		'tracks' => 0,
		'upgrade' => false,
		'label' => 'Standard',
	)
);

$wporg_src = (string) file_get_contents($wporg);
check(
	'wporg edition file has no lite/pro gate identifiers',
	!preg_match('/LITE_MAX_TRACKS|choir_rehearsal_is_pro|is_full_edition/', $wporg_src)
);

$dist_code = "define('ABSPATH', '1'); define('CHOIR_REHEARSAL_DISTRIBUTION', 'demo');\n"
	. 'require ' . var_export($root . '/includes/class-distribution.php', true) . ";\n"
	. "echo json_encode(array('demo' => Choir_Rehearsal_Distribution::is_demo(), 'updater' => Choir_Rehearsal_Distribution::uses_github_updater(), 'channel' => Choir_Rehearsal_Distribution::channel()));";
$dist = run_php($dist_code);
$dist_json = json_decode($dist['out'], true);
check(
	'demo channel does not self-update from GitHub',
	0 === $dist['exit'] && is_array($dist_json) && true === $dist_json['demo'] && false === $dist_json['updater'] && 'demo' === $dist_json['channel']
);
if (!(0 === $dist['exit'] && is_array($dist_json) && true === $dist_json['demo'] && false === $dist_json['updater'])) {
	echo '  got: ' . trim($dist['out']) . "\n";
}

check('bootstrap loads the demo drop-in only when wordpress.org is absent', str_contains($bootstrap, 'distribution-wporg.php') && str_contains($bootstrap, 'distribution-demo.php') && strpos($bootstrap, 'distribution-wporg.php') < strpos($bootstrap, 'distribution-demo.php'));
check('github zip still strips the demo drop-in', str_contains($github_zip, 'distribution-demo.php'));
check('wordpress.org zip strips the demo drop-in', str_contains($wporg_zip, 'distribution-demo.php'));
check('distribution names the demo channel', str_contains($distribution, "CHANNEL_DEMO"));

$heading = '';
if (preg_match('/<div class="choir-tracks-heading">.*?<\/div>/s', $frontend, $heading_match)) {
	$heading = $heading_match[0];
}
check('mixer button sits on the voice tracks heading row', str_contains($heading, "esc_html_e( 'Voice tracks'") && str_contains($heading, 'render_mixer_launch') && strpos($heading, 'Voice tracks') < strpos($heading, 'render_mixer_launch'));
check('launch control is gated by can_use_mixer', str_contains($frontend, 'Choir_Rehearsal_Edition::can_use_mixer()'));
check('launch markup lives in the shared template', str_contains($frontend, 'function render_mixer_launch') && str_contains($frontend, 'class="choir-mixer-launch"'));
$admin = (string) file_get_contents($root . '/includes/class-admin.php');
$place = (string) file_get_contents($root . '/admin/js/mixer-launch.js');
check('editor metabox uses the same Mixer control', str_contains($admin, "__( 'Voice Tracks'") && str_contains($admin, 'Choir_Rehearsal_Frontend::render_mixer_launch'));
check('editor button is placed after the Voice Tracks heading', str_contains($place, 'choir-rehearsal-tracks') && str_contains($place, "insertAdjacentElement('afterend', button)"));
$mixable = '';
if (preg_match('/function mixable_audio_tracks\(.*?\n\t\}/s', $frontend, $mixable_match)) {
	$mixable = $mixable_match[0];
}
check('youtube tracks are not mixer channels', str_contains($mixable, "'youtube'") && str_contains($mixable, 'continue'));
check('channels default to 70 percent', str_contains($frontend, 'aria-valuenow="70"') && str_contains($frontend, 'data-default-volume="70"'));
check('pro badge is omitted on the wordpress.org build', str_contains($frontend, 'Choir_Rehearsal_Distribution::is_wporg()'));
check('song notes block is unchanged', str_contains($frontend, '<div class="choir-song-notes"><?php echo wp_kses_post( wpautop( $song->post_content ) ); ?></div>'));

$ui = $frontend . $mixer . $css . $player;
check('my part control is not part of the mixer', !preg_match('/My part|Моя партия|Minu partii|моя партия/u', $ui));
check('mixer uses one web audio context and shared offsets', str_contains($mixer, 'AudioContext') && str_contains($mixer, 'decodeAudioData') && str_contains($mixer, 'sourceOffset') && str_contains($mixer, 'choirMixerLogic'));
check('play resumes a suspended context for iOS', str_contains($mixer, 'resume'));
check('single-track player pauses the mixer', str_contains($player, 'choirMixer') && str_contains($player, 'pause'));
check('fader and mute targets are at least 44px', (bool) preg_match('/\.choir-mixer__fader\s*\{[^}]*min-width:\s*44px/s', $css) && (bool) preg_match('/\.choir-mixer__mute\s*,\s*\.choir-mixer__solo\s*\{[^}]*min-height:\s*44px/s', $css));
check('channels can scroll sideways', (bool) preg_match('/\.choir-mixer__channels\s*\{[^}]*overflow-x:\s*auto/s', $css));
check('no external audio or script cdn', !preg_match('/cdn\.|cdnjs|unpkg|jsdelivr/i', $mixer . $frontend));

$catalogs = array(
	'et' => 'Mikser',
	'et_EE' => 'Mikser',
	'ru_RU' => 'Микшер',
);
foreach ($catalogs as $locale => $expected) {
	$catalog = include $root . '/languages/compath-choir-rehearsal-' . $locale . '.l10n.php';
	$messages = is_array($catalog) ? ($catalog['messages'] ?? array()) : array();
	check(
		$locale . ' translates Mixer',
		isset($messages['Mixer']) && $messages['Mixer'] === $expected && isset($messages['Reset']) && isset($messages['A–B loop'])
	);
	check($locale . ' does not translate role names inside the mixer catalog', !isset($messages['My part']));
}

$pot = (string) file_get_contents($root . '/languages/compath-choir-rehearsal.pot');
check('template catalogs Mixer', str_contains($pot, 'msgid "Mixer"'));

exit($fail > 0 ? 1 : 0);
