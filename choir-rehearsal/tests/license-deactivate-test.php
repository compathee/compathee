<?php
/**
 * Deactivate License confirms, notices survive the redirect, and 2xx clears local state.
 *
 * Run: php choir-rehearsal/tests/license-deactivate-test.php
 */

declare(strict_types=1);

$root     = dirname( __DIR__, 2 );
$settings = (string) file_get_contents( $root . '/choir-rehearsal-pro/licensing/src/Settings.php' );
$client   = (string) file_get_contents( $root . '/choir-rehearsal-pro/licensing/src/Client.php' );
$license  = (string) file_get_contents( $root . '/choir-rehearsal-pro/licensing/src/License.php' );
$admin    = (string) file_get_contents( $root . '/choir-rehearsal/includes/class-admin.php' );

$confirm = 'Deactivate the Pro license on this site? Pro features will be disabled and the activation slot freed.';
$success = 'License deactivated';

$submit_start = strpos( $settings, 'function license_form_submit' );
$submit_end   = strpos( $settings, 'function redirect' );
$submit_body  = ( false !== $submit_start && false !== $submit_end ) ? substr( $settings, $submit_start, $submit_end - $submit_start ) : '';
$error_at     = strpos( $submit_body, 'is_wp_error( $deactivated )' );
$return_at    = ( false !== $error_at ) ? strpos( $submit_body, 'return;', $error_at ) : false;
$success_at   = ( false !== $error_at ) ? strpos( $submit_body, $success, $error_at ) : false;

$checks = array(
	'confirm string is translatable' => str_contains( $settings, $confirm ) && str_contains( $settings, 'compath-choir-rehearsal' ) && str_contains( $admin, $confirm ),
	'deactivate button uses confirm()' => str_contains( $settings, 'confirm(' ),
	'manage_options gate'            => str_contains( $submit_body, "current_user_can( 'manage_options' )" ),
	'error returns before success'   => false !== $return_at && false !== $success_at && $return_at < $success_at,
	'client accepts every 2xx'       => str_contains( $client, '$response_code < 200 || $response_code >= 300' ),
	'clears activation option'       => str_contains( $license, '_license_activation_id' ) && str_contains( $license, 'delete_option' ),
	'clears license cache'           => str_contains( $license, 'choir_rehearsal_pro_license_cache' ) && str_contains( $license, 'delete_transient' ),
	'qualified admin class'          => str_contains( $settings, '\\Choir_Rehearsal_Admin::render_my_orders_button' ),
);

$GLOBALS['choir_opts']    = array();
$GLOBALS['choir_trans']   = array();
$GLOBALS['choir_notices'] = array();
$GLOBALS['choir_http']    = array( 'code' => 200, 'body' => '{}' );
$GLOBALS['choir_can']     = true;
$GLOBALS['choir_user']    = 7;

function choir_check( string $name, bool $ok ): void {
	global $checks;
	$checks[ $name ] = $ok;
}

if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public function __construct( private string $code, private string $message ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
	}
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function esc_html( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_url( $url ): string { return (string) $url; }
function esc_url_raw( $url ): string { return (string) $url; }
function esc_html_e( $text, $domain = '' ): void { unset( $domain ); echo esc_html( (string) $text ); }
function __( $text, $domain = '' ): string { unset( $domain ); return (string) $text; }
function wp_json_encode( $data ): string { return (string) json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function sanitize_text_field( $text ): string { return trim( (string) $text ); }
function sanitize_key( $key ): string { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }
function wp_unslash( $value ) { return $value; }
function wp_verify_nonce( $nonce, $action ): bool { unset( $nonce, $action ); return true; }
function wp_create_nonce( $action ): string { unset( $action ); return 'nonce'; }
function current_user_can( string $cap ): bool { unset( $cap ); return (bool) $GLOBALS['choir_can']; }
function get_current_user_id(): int { return (int) $GLOBALS['choir_user']; }
function get_option( $key, $default = false ) { return $GLOBALS['choir_opts'][ $key ] ?? $default; }
function update_option( $key, $value ): bool { $GLOBALS['choir_opts'][ $key ] = $value; return true; }
function delete_option( $key ): bool { unset( $GLOBALS['choir_opts'][ $key ] ); return true; }
function get_transient( $key ) { return $GLOBALS['choir_trans'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ): bool { unset( $ttl ); $GLOBALS['choir_trans'][ $key ] = $value; return true; }
function delete_transient( $key ): bool { unset( $GLOBALS['choir_trans'][ $key ] ); return true; }
function add_settings_error( $setting, $code, $message, $type = 'error' ): void {
	$GLOBALS['choir_notices'][] = array( 'setting' => $setting, 'code' => $code, 'message' => $message, 'type' => $type );
}
function settings_errors(): void {}
function add_action( ...$args ): void { unset( $args ); }
function add_filter( ...$args ): void { unset( $args ); }
function apply_filters( $tag, $value ) { unset( $tag ); return $value; }
function trailingslashit( $value ): string { return rtrim( (string) $value, '/' ) . '/'; }
function plugin_basename( $file ): string { unset( $file ); return 'choir-rehearsal-pro/choir-rehearsal-pro.php'; }
function wp_parse_args( $args, $defaults = array() ): array {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}
function add_query_arg( $key, $value, $url ): string {
	$sep = str_contains( (string) $url, '?' ) ? '&' : '?';
	return $url . $sep . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
}
function wp_remote_request( $url, $args = array() ) {
	unset( $url, $args );
	return array( 'code' => $GLOBALS['choir_http']['code'], 'body' => $GLOBALS['choir_http']['body'] );
}
function wp_remote_retrieve_response_code( $response ): int { return (int) ( $response['code'] ?? 0 ); }
function wp_remote_retrieve_body( $response ): string { return (string) ( $response['body'] ?? '' ); }
function submit_button( $text ): void {
	echo '<p class="submit"><input type="submit" name="submit" class="button button-primary" value="' . esc_attr( (string) $text ) . '" /></p>';
}
function get_site_url(): string { return 'https://choir.example'; }
function get_bloginfo(): string { return 'Choir'; }

$stub = sys_get_temp_dir() . '/choir-license-wp';
if ( ! is_dir( $stub . '/wp-admin/includes' ) ) {
	mkdir( $stub . '/wp-admin/includes', 0777, true );
}
file_put_contents( $stub . '/wp-admin/includes/plugin.php', "<?php\nif (!function_exists('get_plugin_data')) { function get_plugin_data(\$file) { return array('Version' => '0.5.2'); } }\n" );
define( 'ABSPATH', $stub . '/' );
define( 'WP_CONTENT_DIR', $stub . '/wp-content' );

require $root . '/choir-rehearsal/includes/class-admin.php';
require $root . '/choir-rehearsal-pro/licensing/src/Client.php';

$client_obj = new SureCart\Licensing\Client( 'Compath Choir Rehearsal Pro', 'pt_test', $root . '/choir-rehearsal-pro/choir-rehearsal-pro.php' );
$client_obj->set_textdomain( 'choir-rehearsal-pro' );
$page = $client_obj->settings();
$page->add_page(
	array(
		'type'                 => 'submenu',
		'parent_slug'          => 'edit.php?post_type=choir_song',
		'page_title'           => 'Pro License',
		'menu_title'           => 'Pro License',
		'deactivated_redirect' => 'https://example.test/wp-admin/edit.php?post_type=choir_song&page=choir-rehearsal-pro-license',
		'activated_redirect'   => 'https://example.test/wp-admin/edit.php?post_type=choir_song&page=choir-rehearsal-pro-license',
	)
);

$option_key = 'compathchoirrehearsalpro_license_options';
$cache_key  = 'choir_rehearsal_pro_license_cache';
$slot_key   = 'surecart_' . md5( 'choir-rehearsal-pro' ) . '_license_activation_id';
$notice_key = 'choir_rehearsal_pro_license_notice_7';

function choir_seed_license(): void {
	global $option_key, $cache_key, $slot_key;
	$GLOBALS['choir_opts']  = array(
		$option_key => array(
			'sc_license_key'   => 'ABCD1234WXYZ5678',
			'sc_activation_id' => 'act_site_1',
		),
		$slot_key   => 'act_site_1',
	);
	$GLOBALS['choir_trans'] = array( $cache_key => '1' );
	$GLOBALS['choir_notices'] = array();
}

function choir_license_still_stored(): bool {
	global $option_key, $cache_key, $slot_key;
	$opts = $GLOBALS['choir_opts'][ $option_key ] ?? array();
	return ( $opts['sc_license_key'] ?? '' ) === 'ABCD1234WXYZ5678'
		&& ( $opts['sc_activation_id'] ?? '' ) === 'act_site_1'
		&& ( $GLOBALS['choir_opts'][ $slot_key ] ?? '' ) === 'act_site_1'
		&& ( $GLOBALS['choir_trans'][ $cache_key ] ?? '' ) === '1';
}

function choir_license_cleared(): bool {
	global $option_key, $cache_key, $slot_key;
	$opts = $GLOBALS['choir_opts'][ $option_key ] ?? array( 'missing' => true );
	return array() === $opts
		&& ! isset( $GLOBALS['choir_opts'][ $slot_key ] )
		&& ! isset( $GLOBALS['choir_trans'][ $cache_key ] );
}

function choir_notice_types(): array {
	return array_column( $GLOBALS['choir_notices'], 'type' );
}

if ( ! method_exists( $page, 'complete_deactivation' ) || ! method_exists( $client_obj, 'send_request' ) ) {
	choir_check( 'runtime deactivation api', false );
} else {
	$GLOBALS['choir_http'] = array( 'code' => 204, 'body' => '' );
	$empty = $client_obj->send_request( 'DELETE', 'v1/public/activations/act_site_1' );
	choir_check( '204 is success', ! is_wp_error( $empty ) );

	$GLOBALS['choir_http'] = array( 'code' => 201, 'body' => '{"id":"act_new"}' );
	$created = $client_obj->send_request( 'POST', 'v1/public/activations' );
	choir_check( '201 returns the body', is_object( $created ) && ( $created->id ?? '' ) === 'act_new' );

	$GLOBALS['choir_http'] = array( 'code' => 299, 'body' => '' );
	choir_check( '299 is success', ! is_wp_error( $client_obj->send_request( 'DELETE', 'v1/public/activations/act_site_1' ) ) );

	$GLOBALS['choir_http'] = array( 'code' => 500, 'body' => '{"code":"locked","message":"Still in use"}' );
	$failed = $client_obj->send_request( 'DELETE', 'v1/public/activations/act_site_1' );
	choir_check( '500 keeps the api message', is_wp_error( $failed ) && 'Still in use' === $failed->get_error_message() && 'locked' === $failed->get_error_code() );

	choir_seed_license();
	$GLOBALS['choir_http'] = array( 'code' => 204, 'body' => '' );
	$done = $client_obj->license()->deactivate( 'act_site_1' );
	choir_check( '204 deactivation succeeds and clears local license', true === $done && choir_license_cleared() );

	choir_seed_license();
	$GLOBALS['choir_http'] = array( 'code' => 500, 'body' => '{"code":"locked","message":"Still in use"}' );
	$kept = $client_obj->license()->deactivate( 'act_site_1' );
	choir_check( 'remote failure keeps the local license', is_wp_error( $kept ) && choir_license_still_stored() );

	choir_seed_license();
	$GLOBALS['choir_http'] = array( 'code' => 404, 'body' => '' );
	$missing = $client_obj->license()->deactivate( 'act_site_1' );
	choir_check( 'missing remote activation still clears local license', true === $missing && choir_license_cleared() );

	choir_seed_license();
	$GLOBALS['choir_notices'] = array();
	$error_url = $page->complete_deactivation( new WP_Error( 'locked', 'Still in use' ) );
	choir_check(
		'error redirect stores the api message',
		str_contains( $error_url, 'choir_license_notice=error' )
		&& ( $GLOBALS['choir_trans'][ $notice_key ]['message'] ?? '' ) === 'Still in use'
		&& ! in_array( 'success', choir_notice_types(), true )
		&& choir_license_still_stored()
	);

	$_GET['choir_license_notice'] = 'error';
	$GLOBALS['choir_notices']     = array();
	$page->restore_license_notice();
	choir_check(
		'error notice is restored after redirect',
		( $GLOBALS['choir_notices'][0]['type'] ?? '' ) === 'error'
		&& ( $GLOBALS['choir_notices'][0]['message'] ?? '' ) === 'Still in use'
		&& ! isset( $GLOBALS['choir_trans'][ $notice_key ] )
	);
	unset( $_GET['choir_license_notice'] );

	$GLOBALS['choir_notices'] = array();
	$ok_url = $page->complete_deactivation( true );
	choir_check(
		'success redirect stores License deactivated',
		str_contains( $ok_url, 'choir_license_notice=deactivated' )
		&& ( $GLOBALS['choir_trans'][ $notice_key ]['message'] ?? '' ) === $success
		&& ! in_array( 'success', choir_notice_types(), true )
	);
	$_GET['choir_license_notice'] = 'deactivated';
	$GLOBALS['choir_notices']     = array();
	$page->restore_license_notice();
	choir_check(
		'success notice is restored after redirect',
		( $GLOBALS['choir_notices'][0]['type'] ?? '' ) === 'success'
		&& ( $GLOBALS['choir_notices'][0]['message'] ?? '' ) === $success
	);
	unset( $_GET['choir_license_notice'] );

	$GLOBALS['choir_can'] = false;
	$_POST                = array(
		'submit'        => 'Deactivate License',
		'_nonce'        => 'nonce',
		'_action'       => 'deactivate',
		'activation_id' => 'act_site_1',
	);
	choir_seed_license();
	$page->license_form_submit();
	choir_check( 'manage_options rejects the submit', ( $GLOBALS['choir_notices'][0]['type'] ?? '' ) === 'error' && choir_license_still_stored() );
	$_POST            = array();
	$GLOBALS['choir_can'] = true;

	$GLOBALS['choir_http'] = array( 'code' => 200, 'body' => '{"id":"act_site_1"}' );
	$GLOBALS['choir_opts'][ $option_key ] = array(
		'sc_license_key'   => 'ABCD1234WXYZ5678',
		'sc_activation_id' => 'act_site_1',
	);
	ob_start();
	$page->settings_output();
	$deactivate_html = (string) ob_get_clean();
	choir_check(
		'deactivate form asks for confirmation',
		str_contains( $deactivate_html, 'value="Deactivate License"' )
		&& str_contains( $deactivate_html, 'confirm(' )
		&& str_contains( $deactivate_html, $confirm )
		&& str_contains( $deactivate_html, 'My orders..' )
		&& str_contains( $deactivate_html, 'target="_blank"' )
		&& str_contains( $deactivate_html, 'rel="noopener noreferrer"' )
	);

	$GLOBALS['choir_opts'][ $option_key ] = array();
	ob_start();
	$page->settings_output();
	$activate_html = (string) ob_get_clean();
	choir_check( 'activate form has no deactivate confirm', str_contains( $activate_html, 'Activate License' ) && ! str_contains( $activate_html, 'confirm(' ) && str_contains( $activate_html, 'My orders..' ) );
}

foreach ( array( 'et', 'et_EE', 'ru_RU' ) as $locale ) {
	$base = dirname( __DIR__ ) . '/languages/compath-choir-rehearsal-' . $locale;
	$po   = (string) file_get_contents( $base . '.po' );
	$l10n = (string) file_get_contents( $base . '.l10n.php' );
	$mo   = (string) file_get_contents( $base . '.mo' );
	$checks[ $locale . ' confirm catalog' ] = str_contains( $po, 'msgid "' . $confirm . '"' ) && str_contains( $l10n, $confirm ) && str_contains( $mo, $confirm );
	$checks[ $locale . ' success catalog' ] = str_contains( $po, 'msgid "' . $success . '"' ) && str_contains( $l10n, $success ) && str_contains( $mo, $success );
}

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' ' . $name . "\n";
	if ( ! $ok ) {
		++$fail;
	}
}

exit( $fail > 0 ? 1 : 0 );
