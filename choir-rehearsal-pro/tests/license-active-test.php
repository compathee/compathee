<?php
/**
 * Pro is licensed only when SureCart stored both an activation id and a license key.
 *
 * Run: php choir-rehearsal-pro/tests/license-active-test.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('CHOIR_REHEARSAL_PRO_PATH', dirname(__DIR__) . '/');

$fail = 0;
$options = array();

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

function get_option($key, $default = array()) {
	global $options;
	if ('compathchoirrehearsalpro_license_options' === $key) {
		return $options;
	}
	return $default;
}

require dirname(__DIR__) . '/includes/class-licensing.php';

check('public token is configured', '' !== Choir_Rehearsal_Pro_Licensing::public_token());

$options = array();
check('missing option is not licensed', !Choir_Rehearsal_Pro_Licensing::is_licensed());

$options = array('sc_license_key' => 'ABCD1234WXYZ5678');
check('key without activation is not licensed', !Choir_Rehearsal_Pro_Licensing::is_licensed());

$options = array('sc_activation_id' => 'act_site_1');
check('activation without key is not licensed', !Choir_Rehearsal_Pro_Licensing::is_licensed());

$options = array(
	'sc_activation_id' => 'act_site_1',
	'sc_license_key' => 'ABCD1234WXYZ5678',
);
check('activation and key is licensed', Choir_Rehearsal_Pro_Licensing::is_licensed());

$options = array(
	'sc_activation_id' => '',
	'sc_license_key' => 'ABCD1234WXYZ5678',
);
check('empty activation is not licensed', !Choir_Rehearsal_Pro_Licensing::is_licensed());

exit($fail > 0 ? 1 : 0);
