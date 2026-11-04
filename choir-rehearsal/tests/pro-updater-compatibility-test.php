<?php
/**
 * Pro plugin details must include tested, requires, and requires_php
 * even when the SureCart release payload omits them.
 *
 * Run: php choir-rehearsal/tests/pro-updater-compatibility-test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/choir-rehearsal-pro/licensing/src/Updater.php';

use SureCart\Licensing\Updater;

$plugin = $root . '/choir-rehearsal-pro/choir-rehearsal-pro.php';
$fail   = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

$local = Updater::compatibility_from_install($plugin);
check('header and release.json tested is 7.1', ($local['tested'] ?? '') === '7.1');
check('requires is 6.4', ($local['requires'] ?? '') === '6.4');
check('requires php is 8.0', ($local['requires_php'] ?? '') === '8.0');

$empty = Updater::apply_compatibility_fields((object) array(
	'slug' => 'choir-rehearsal-pro',
	'new_version' => '0.5.5',
), $plugin);
check('missing tested is filled', isset($empty->tested) && $empty->tested === '7.1');
check('missing requires is filled', isset($empty->requires) && $empty->requires === '6.4');
check('missing requires_php is filled', isset($empty->requires_php) && $empty->requires_php === '8.0');

$stale = Updater::apply_compatibility_fields((object) array(
	'tested' => '6.9',
	'requires' => '6.4',
	'requires_php' => '8.0',
), $plugin);
check('stale tested 6.9 is raised to 7.1', isset($stale->tested) && $stale->tested === '7.1');

$newer = Updater::apply_compatibility_fields((object) array(
	'tested' => '7.2',
	'requires' => '6.4',
	'requires_php' => '8.0',
), $plugin);
check('newer remote tested value is kept', isset($newer->tested) && $newer->tested === '7.2');

$src = (string) file_get_contents($root . '/choir-rehearsal-pro/licensing/src/Updater.php');
check('plugins api path normalizes version info', str_contains($src, 'return $this->normalize_version_info( $version_info );'));
check('normalize applies compatibility fields', str_contains($src, 'return self::apply_compatibility_fields( $version_info, $file );'));

exit($fail > 0 ? 1 : 0);
