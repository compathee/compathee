<?php
/**
 * Plugin details must report the readme Tested up to value, not a hardcoded 6.9.
 *
 * Run: php choir-rehearsal/tests/updater-compatibility-test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$src  = (string) file_get_contents($root . '/includes/class-updater.php');

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

check('updater does not hardcode tested 6.9', !preg_match("/'tested'\\s*=>\\s*'6\\.9'/", $src));
check('updater reads Tested up to from the readme', str_contains($src, 'Tested up to:'));
check('current-version info includes tested', str_contains($src, "'tested'       => \$remote['tested']") && str_contains($src, "'tested'       => \$compat['tested']"));

if (!defined('ABSPATH')) {
	define('ABSPATH', $root . '/');
}
if (!defined('CHOIR_REHEARSAL_PATH')) {
	define('CHOIR_REHEARSAL_PATH', $root . '/');
}
if (!defined('CHOIR_REHEARSAL_FILE')) {
	define('CHOIR_REHEARSAL_FILE', $root . '/choir-rehearsal.php');
}

require_once $root . '/includes/class-updater.php';

$readme = new ReflectionMethod(Choir_Rehearsal_Updater::class, 'readme_compatibility');
$readme->setAccessible(true);
$compat = $readme->invoke(null);
check('readme tested is 7.1', ($compat['tested'] ?? '') === '7.1');
check('readme requires is 6.4', ($compat['requires'] ?? '') === '6.4');
check('readme requires php is 8.0', ($compat['requires_php'] ?? '') === '8.0');

$apply = new ReflectionMethod(Choir_Rehearsal_Updater::class, 'apply_compatibility');
$apply->setAccessible(true);
$raised = $apply->invoke(null, array(
	'requires' => '6.4',
	'tested' => '6.9',
	'requires_php' => '8.0',
));
check('stale tested 6.9 is raised to the readme', ($raised['tested'] ?? '') === '7.1');
$kept = $apply->invoke(null, array(
	'requires' => '6.4',
	'tested' => '7.2',
	'requires_php' => '8.0',
));
check('a newer remote tested value is kept', ($kept['tested'] ?? '') === '7.2');

$map = new ReflectionMethod(Choir_Rehearsal_Updater::class, 'map_github_release');
$map->setAccessible(true);
$mapped = $map->invoke(null, array(
	'tag_name' => 'compath-choir-rehearsal-v0.4.74',
	'published_at' => '2026-11-04T00:00:00Z',
	'body' => 'notes',
	'assets' => array(
		array(
			'name' => 'compath-choir-rehearsal.zip',
			'browser_download_url' => 'https://example.com/compath-choir-rehearsal.zip',
		),
	),
));
check('github release mapping uses readme tested', is_array($mapped) && ($mapped['tested'] ?? '') === '7.1');
check('github release mapping keeps requires and php', is_array($mapped) && ($mapped['requires'] ?? '') === '6.4' && ($mapped['requires_php'] ?? '') === '8.0');

$normalize = new ReflectionMethod(Choir_Rehearsal_Updater::class, 'normalize_metadata');
$normalize->setAccessible(true);
$normalized = $normalize->invoke(null, array(
	'version' => '0.4.73',
	'download_url' => 'https://example.com/plugin.zip',
	'tested' => '6.9',
));
check('update.json tested 6.9 is raised to the readme', is_array($normalized) && ($normalized['tested'] ?? '') === '7.1');

exit($fail > 0 ? 1 : 0);
