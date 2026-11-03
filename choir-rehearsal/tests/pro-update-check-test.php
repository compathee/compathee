<?php
/**
 * Check Updates must refresh SureCart as well as Lite, without leaking a license.
 *
 * Run: php choir-rehearsal/tests/pro-update-check-test.php
 */

declare(strict_types=1);

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

define('ABSPATH', __DIR__);

require dirname(__DIR__) . '/includes/class-updater.php';
require dirname(__DIR__, 2) . '/choir-rehearsal-pro/includes/class-pro-updates.php';

$lite = (string) file_get_contents(dirname(__DIR__) . '/includes/class-updater.php');
$admin = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin.php');
$pro = (string) file_get_contents(dirname(__DIR__, 2) . '/choir-rehearsal-pro/includes/class-pro-updates.php');
$boot = (string) file_get_contents(dirname(__DIR__, 2) . '/choir-rehearsal-pro/choir-rehearsal-pro.php');
$sdk = (string) file_get_contents(dirname(__DIR__, 2) . '/choir-rehearsal-pro/licensing/src/Updater.php');
$demo = (string) file_get_contents(dirname(__DIR__) . '/includes/class-demo-data.php');

$available = Choir_Rehearsal_Updater::summarize_version_check('0.5.2', '0.5.4');
check('newer release is available', ($available['status'] ?? '') === 'available' && ($available['installed'] ?? '') === '0.5.2' && ($available['available'] ?? '') === '0.5.4');

$current = Choir_Rehearsal_Updater::summarize_version_check('0.4.71', 'v0.4.71');
check('same version is up to date', ($current['status'] ?? '') === 'up_to_date' && ($current['available'] ?? '') === '0.4.71');

$ahead = Choir_Rehearsal_Updater::summarize_version_check('0.4.71', '0.4.68');
check('installed newer than metadata is ahead', ($ahead['status'] ?? '') === 'ahead' && ($ahead['available'] ?? '') === '0.4.68');

$offline = Choir_Rehearsal_Updater::summarize_version_check('0.4.71', null);
check('missing metadata fails closed', ($offline['status'] ?? '') === 'failed' && ($offline['available'] ?? '') === '');

$unlicensed = Choir_Rehearsal_Pro_Updates::summarize_pro_check(false, '0.5.2', '0.5.4', true);
check(
	'unlicensed pro does not report a remote version',
	($unlicensed['status'] ?? '') === 'unlicensed'
	&& ($unlicensed['installed'] ?? '') === '0.5.2'
	&& ($unlicensed['available'] ?? '') === ''
);

$licensed = Choir_Rehearsal_Pro_Updates::summarize_pro_check(true, '0.5.2', '0.5.4', true);
check('licensed pro reports the newer release', ($licensed['status'] ?? '') === 'available' && ($licensed['available'] ?? '') === '0.5.4');

$pro_offline = Choir_Rehearsal_Pro_Updates::summarize_pro_check(true, '0.5.2', null, false);
check('licensed but unreachable is failed not unlicensed', ($pro_offline['status'] ?? '') === 'failed');

$cache_key = Choir_Rehearsal_Pro_Updates::version_cache_key('choir-rehearsal-pro');
check('surecart cache key matches the SDK', $cache_key === 'surecart_' . md5('choir-rehearsal-pro') . '_version_info');

$from_object = Choir_Rehearsal_Pro_Updates::release_version((object) array(
	'slug' => 'choir-rehearsal-pro',
	'version' => 'v0.5.4',
));
check('release object version', $from_object === '0.5.4');

$from_string = Choir_Rehearsal_Pro_Updates::release_version('{"slug":"choir-rehearsal-pro","version":"0.5.4"}');
check('string release_json still yields a version', $from_string === '0.5.4');

$no_slug = Choir_Rehearsal_Pro_Updates::release_version((object) array('version' => '9.9.9'));
check('release without a slug is ignored', $no_slug === '');

$secret = 'ABCD1234WXYZ5678';
$activation = 'act_full_secret_value';
$row = Choir_Rehearsal_Updater::sanitize_plugin_check_row(array(
	'id' => 'pro',
	'name' => 'Compath Choir Rehearsal Pro',
	'status' => 'unlicensed',
	'installed' => '0.5.2',
	'available' => '',
	'basename' => 'choir-rehearsal-pro/choir-rehearsal-pro.php',
	'license_key' => $secret,
	'sc_license_key' => $secret,
	'activation_id' => $activation,
	'sc_activation_id' => $activation,
	'package' => 'https://api.surecart.com/download?activation_id=' . $activation,
));
$encoded = (string) json_encode($row);
check(
	'sanitized row drops the license key and activation id',
	!str_contains($encoded, $secret)
	&& !str_contains($encoded, $activation)
	&& !str_contains($encoded, 'surecart.com')
	&& !array_key_exists('license_key', $row)
	&& !array_key_exists('activation_id', $row)
	&& ($row['basename'] ?? '') === 'choir-rehearsal-pro/choir-rehearsal-pro.php'
);

check('check updates requires manage_options', str_contains($lite, "current_user_can( 'manage_options' )"));
check('check updates keeps its nonce', str_contains($lite, "check_admin_referer( 'choir_rehearsal_check_updates' )"));
check('lite clear notifies pro before the plugin transient is rebuilt', str_contains($lite, "do_action( 'choir_rehearsal_clear_update_caches' )") && str_contains($lite, 'wp_update_plugins'));
check('notice names installed and available versions', str_contains($lite, '%1$s: installed %2$s, available %3$s.'));
check('notice offers update or up to date', str_contains($lite, "__( 'Update', 'compath-choir-rehearsal' )") && str_contains($lite, 'Up to date.'));
check('settings button mentions SureCart', str_contains($lite, 'SureCart') && str_contains($lite, 'function render_settings_button'));
check('wporg admin file has no SureCart update copy', !str_contains($admin, 'SureCart'));
check('admin asks the updater for the button', str_contains($admin, 'Choir_Rehearsal_Updater::render_settings_button'));

check('pro clears the surecart version transient', str_contains($pro, 'delete_transient') && str_contains($pro, 'version_cache_key'));
check('pro flushes that cache when WordPress rebuilds update_plugins', str_contains($pro, 'pre_set_site_transient_update_plugins'));
check('pro listens for the settings check', str_contains($pro, 'choir_rehearsal_clear_update_caches') && str_contains($pro, 'choir_rehearsal_update_check_plugins'));
check('unlicensed copy does not call the release endpoint first', str_contains($pro, 'is_licensed()') && str_contains($pro, 'unlicensed'));
check('pro module is loaded', str_contains($boot, 'class-pro-updates.php') && str_contains($boot, 'Choir_Rehearsal_Pro_Updates::register'));

check('sdk download url lives for twelve hours', str_contains($sdk, '12 * HOUR_IN_SECONDS'));
check('sdk replaces a stale response entry', !str_contains($sdk, 'if ( ! empty( $transient_data->response ) && ! empty( $transient_data->response[ $this->client->basename ] ) )'));
check('sdk compares the on-disk checked version', str_contains($sdk, 'checked[ $this->client->basename ]'));
check('sdk accepts a string release_json', str_contains($sdk, 'is_string( $release )'));
check('sdk always pins the plugin basename', str_contains($sdk, '$version_info->plugin = $this->client->basename'));

check('demo songs do not grow a license gate', !str_contains($demo, 'sc_license_key') && !str_contains($demo, 'is_licensed'));

$strings = array(
	'Contacts GitHub Releases and SureCart and shows the result for each plugin on this page.',
	'%1$s: installed %2$s, available %3$s.',
	'Up to date.',
	'%1$s: installed %2$s. Could not check for updates.',
	'Compath Choir Rehearsal Pro is installed (version %s), but its license is not activated. Pro updates are not checked until you activate the license. The Lite check still runs.',
);
foreach (array('et', 'et_EE', 'ru_RU') as $locale) {
	$base = dirname(__DIR__) . '/languages/compath-choir-rehearsal-' . $locale;
	$po = (string) file_get_contents($base . '.po');
	$l10n = (string) file_get_contents($base . '.l10n.php');
	$mo = (string) file_get_contents($base . '.mo');
	foreach ($strings as $msgid) {
		$ok = str_contains($po, 'msgid "' . $msgid . '"') && str_contains($l10n, $msgid) && str_contains($mo, $msgid);
		check($locale . ' ' . substr($msgid, 0, 28), $ok);
	}
}

exit($fail > 0 ? 1 : 0);
