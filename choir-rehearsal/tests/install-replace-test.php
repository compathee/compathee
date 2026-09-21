<?php
declare(strict_types=1);

$install = file_get_contents(__DIR__ . '/../includes/class-install-replace.php');
$migration = file_get_contents(__DIR__ . '/../includes/class-migration.php');
$plugin = file_get_contents(__DIR__ . '/../includes/class-plugin.php');
$updater = file_get_contents(__DIR__ . '/../includes/class-updater.php');

$checks = [
	'install_replace_class' => str_contains($install, 'class Choir_Rehearsal_Install_Replace'),
	'source_selection_hook' => str_contains($install, 'upgrader_source_selection'),
	'clear_destination_hook' => str_contains($install, 'upgrader_clear_destination'),
	'after_complete_hook' => str_contains($install, 'after_process_complete'),
	'known_folders' => str_contains($install, 'compath-choir-rehearsal') && str_contains($install, 'choir-rehearsal'),
	'uses_migration_find' => str_contains($install, 'Choir_Rehearsal_Migration::find_lite_installs'),
	'remove_other_folders' => str_contains($install, 'remove_other_lite_folders'),
	'public_delete_folder' => str_contains($migration, 'public static function delete_plugin_folder'),
	'suggested_keep_folder' => str_contains($migration, 'suggested_keep_folder'),
	'plugin_registers_install_replace' => str_contains($plugin, 'Choir_Rehearsal_Install_Replace::register'),
	'updater_no_source_selection' => ! str_contains($updater, 'upgrader_source_selection'),
	'version_at_least_051' => (static function () {
		$main = file_get_contents(__DIR__ . '/../choir-rehearsal.php');
		if (!is_string($main) || !preg_match("/define\(\s*'CHOIR_REHEARSAL_VERSION',\s*'([^']+)'/", $main, $m)) {
			return false;
		}
		return version_compare($m[1], '0.4.51', '>=');
	})(),
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
