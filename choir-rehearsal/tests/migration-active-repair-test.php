<?php
declare(strict_types=1);

$src = file_get_contents(__DIR__ . '/../includes/class-migration.php');
$boot = file_get_contents(__DIR__ . '/../choir-rehearsal.php');

$checks = [
	'is_lite_basename' => str_contains($src, 'is_lite_basename'),
	'repair_active_plugins' => str_contains($src, 'repair_active_plugins'),
	'find_active_lite_basenames' => str_contains($src, 'find_active_lite_basenames'),
	'needs_active_plugins_repair' => str_contains($src, 'needs_active_plugins_repair'),
	'handle_repair_active' => str_contains($src, 'handle_repair_active'),
	'on_disk_column' => str_contains($src, "'on_disk'"),
	'global_lite_file' => str_contains($boot, 'choir_rehearsal_lite_file'),
	'no_version_only_guard' => ! str_contains($boot, 'defined( \'CHOIR_REHEARSAL_VERSION\' ) || function_exists'),
];

$fail = 0;
foreach ($checks as $k => $v) {
	echo ($v ? 'PASS' : 'FAIL') . " $k\n";
	if (!$v) {
		$fail++;
	}
}
echo $fail ? "FAILURES=$fail\n" : "ALL_OK\n";
exit($fail ? 1 : 0);
