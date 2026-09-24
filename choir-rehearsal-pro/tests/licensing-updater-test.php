<?php
/**
 * Pro SureCart updater wiring (no WordPress bootstrap).
 *
 * Run: php choir-rehearsal-pro/tests/licensing-updater-test.php
 */

declare(strict_types=1);

$root      = dirname(__DIR__);
$main      = file_get_contents($root . '/choir-rehearsal-pro.php');
$licensing = file_get_contents($root . '/includes/class-licensing.php');
$client    = file_get_contents($root . '/licensing/src/Client.php');
$updater   = file_get_contents($root . '/licensing/src/Updater.php');
$license   = file_get_contents($root . '/licensing/src/License.php');
$release   = json_decode((string) file_get_contents($root . '/release.json'), true);

$checks = array();

preg_match('/^\s*\*\s*Version:\s*(\S+)/m', (string) $main, $header);
$header_version = $header[1] ?? '';

$checks['release_json_parses'] = is_array($release);
$checks['slug_is_plugin_folder'] = ($release['slug'] ?? '') === 'choir-rehearsal-pro';
$checks['release_version_matches_header'] = ($release['version'] ?? '') === $header_version && $header_version === '0.5.0';
$checks['constant_matches_header'] = str_contains((string) $main, "define( 'CHOIR_REHEARSAL_PRO_VERSION', '" . $header_version . "' )");

$checks['client_booted_with_main_file'] = str_contains(
	(string) $licensing,
	"new \\SureCart\\Licensing\\Client(\n\t\t\t'Compath Choir Rehearsal Pro',\n\t\t\t\$token,\n\t\t\tCHOIR_REHEARSAL_PRO_FILE\n\t\t)"
);
$checks['boot_on_init'] = str_contains((string) $licensing, "add_action( 'init', array( self::class, 'boot' )");
$checks['client_constructs_updater'] = str_contains((string) $client, '$this->updater();');
$checks['updater_hooks_plugin_transient'] = str_contains((string) $updater, "add_filter( 'pre_set_site_transient_update_plugins'");
$checks['updater_hooks_plugins_api'] = str_contains((string) $updater, "add_filter( 'plugins_api'");
$checks['updater_fetches_current_release'] = str_contains((string) $updater, 'get_current_release(');

$license_fn = '';
if (preg_match('/function get_current_release\(.*?^	\}/ms', (string) $license, $fn)) {
	$license_fn = $fn[0];
}
$checks['release_requires_license_key'] = str_contains($license_fn, "license_key_missing")
	&& str_contains($license_fn, 'empty( $key )');
$checks['release_requires_activation_id'] = str_contains($license_fn, 'activation_id_missing')
	&& str_contains($license_fn, 'empty( $activation_id )');
$key_pos = strpos($license_fn, 'license_key_missing');
$act_pos = strpos($license_fn, 'activation_id_missing');
$req_pos = strpos($license_fn, 'send_request');
$checks['gate_runs_before_network'] = $key_pos !== false && $act_pos !== false && $req_pos !== false
	&& $key_pos < $req_pos && $act_pos < $req_pos;

$checks['license_screen_explains_auto_updates'] = str_contains((string) $licensing, 'From version 0.5.0')
	&& str_contains((string) $licensing, 'older than 0.5.0');
$checks['release_faq_explains_manual_once'] = str_contains((string) ($release['sections']['frequently asked questions'] ?? ''), 'older than 0.5.0');

$fail = 0;
foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

exit($fail > 0 ? 1 : 0);
