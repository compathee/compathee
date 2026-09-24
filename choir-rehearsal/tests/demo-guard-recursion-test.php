<?php
/**
 * The application-password availability filter must not re-enter current-user lookup.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['wp_filters'] = array();
$GLOBALS['wp_did']     = array();
$GLOBALS['wp_user_depth'] = 0;
$GLOBALS['wp_user_max']   = 0;

class WP_User {
	public int $ID = 0;

	/** @var mixed Core returns false from __get() when the login is unset. */
	public mixed $user_login = '';

	/** @var mixed */
	public mixed $user_email = '';
}

/**
 * @param callable $callback Callback.
 */
function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
}

/**
 * @param callable $callback Callback.
 */
function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
	add_filter( $hook, $callback, $priority, $accepted_args );
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
	if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) {
		return $value;
	}
	$priorities = $GLOBALS['wp_filters'][ $hook ];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$call_args = array_slice( array_merge( array( $value ), $args ), 0, (int) $callback[1] );
			$value     = call_user_func_array( $callback[0], $call_args );
		}
	}
	return $value;
}

function do_action( string $hook, mixed ...$args ): void {
	$GLOBALS['wp_did'][ $hook ] = ( $GLOBALS['wp_did'][ $hook ] ?? 0 ) + 1;
	apply_filters( $hook, null, ...$args );
}

function did_action( string $hook ): int {
	return (int) ( $GLOBALS['wp_did'][ $hook ] ?? 0 );
}

function get_locale(): string {
	return 'en_US';
}

function determine_locale(): string {
	// Core does this on wp-admin via get_user_locale(). It must not loop.
	wp_get_current_user();
	return 'et_EE';
}

function __( string $text, string $domain = 'default' ): string {
	$translated = apply_filters( 'gettext', $text, $text, $domain );
	return is_string( $translated ) ? $translated : $text;
}

function get_userdata( int $user_id ): WP_User {
	$user             = new WP_User();
	$user->ID         = $user_id;
	$user->user_login = 2 === $user_id ? 'demosinger' : 'admin';
	return $user;
}

function current_user_can( string $cap ): bool {
	wp_get_current_user();
	return 'manage_options' === $cap;
}

function wp_is_application_passwords_available(): bool {
	$available = apply_filters( 'wp_is_application_passwords_available', true );
	return (bool) $available;
}

function wp_get_current_user(): WP_User {
	global $current_user;
	$GLOBALS['wp_user_depth']++;
	$GLOBALS['wp_user_max'] = max( (int) $GLOBALS['wp_user_max'], (int) $GLOBALS['wp_user_depth'] );
	if ( (int) $GLOBALS['wp_user_depth'] > 3 ) {
		fwrite( STDERR, "FAIL recursion depth {$GLOBALS['wp_user_depth']}\n" );
		exit( 1 );
	}

	if ( did_action( 'set_current_user' ) > 0 && $current_user instanceof WP_User ) {
		$GLOBALS['wp_user_depth']--;
		return $current_user;
	}

	// Same order as core: determine_current_user runs wp_validate_application_password
	// before set_current_user, and that calls wp_is_application_passwords_available().
	apply_filters( 'determine_current_user', false );
	wp_is_application_passwords_available();
	__( Compath_Rehearsal_Demo_Guard::NOTICE, Compath_Rehearsal_Demo_Guard::TEXT_DOMAIN );
	__( 'Please use appropriate language in the song title.', 'compath-choir-rehearsal' );
	Compath_Rehearsal_Demo_Guard::block_locked_usermeta( null, 2, 'session_tokens', array(), null );
	$GLOBALS['nickname_during_auth'] = Compath_Rehearsal_Demo_Guard::block_locked_usermeta( null, 2, 'nickname', 'hacked', null );

	$current_user             = new WP_User();
	$current_user->ID         = 0;
	$current_user->user_login = '';
	do_action( 'set_current_user', 0 );
	$GLOBALS['wp_user_depth']--;
	return $current_user;
}

require_once dirname( __DIR__, 2 ) . '/mu-plugins/compath-rehearsal-demo-guard.php';
require_once dirname( __DIR__, 2 ) . '/mu-plugins/compath-rehearsal-profanity.php';

function assert_true( string $label, bool $got ): void {
	if ( ! $got ) {
		fwrite( STDERR, "FAIL $label expected=true\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

function assert_false( string $label, bool $got ): void {
	if ( $got ) {
		fwrite( STDERR, "FAIL $label expected=false\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

function assert_same( string $label, string $expected, string $got ): void {
	if ( $expected !== $got ) {
		fwrite( STDERR, "FAIL $label expected={$expected} got={$got}\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

$guard_src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/mu-plugins/compath-rehearsal-demo-guard.php' );
assert_false( 'global application-password filter is not registered', str_contains( $guard_src, "add_filter( 'wp_is_application_passwords_available'," ) );
assert_false( 'global callback is gone', str_contains( $guard_src, 'filter_application_passwords_global' ) );
assert_true( 'per-user application-password filter remains', str_contains( $guard_src, 'wp_is_application_passwords_available_for_user' ) );
assert_true( 'no global hook stored', empty( $GLOBALS['wp_filters']['wp_is_application_passwords_available'] ) );

wp_get_current_user();
assert_same( 'lookup depth stays at one', '1', (string) $GLOBALS['wp_user_max'] );
assert_true( 'nickname meta is blocked during lookup', false === $GLOBALS['nickname_during_auth'] );

$after = __( Compath_Rehearsal_Demo_Guard::NOTICE, Compath_Rehearsal_Demo_Guard::TEXT_DOMAIN );
assert_same( 'notice translates after the user is set', 'See on ühine demo. Muudatused lähtestatakse igal ööl.', $after );
assert_same( 'depth unchanged by later translation', '1', (string) $GLOBALS['wp_user_max'] );

$profanity = __( 'Please use appropriate language in the song title.', 'compath-choir-rehearsal' );
assert_same( 'profanity message translates after the user is set', 'Palun kasuta laulu pealkirjas sobivat keelt.', $profanity );

$singer             = new WP_User();
$singer->user_login = 'demosinger';
$depth_before       = (int) $GLOBALS['wp_user_max'];
$for_user           = apply_filters( 'wp_is_application_passwords_available_for_user', true, $singer );
assert_false( 'demo user cannot have application passwords', (bool) $for_user );
assert_same( 'per-user filter does not look up the current user', (string) $depth_before, (string) $GLOBALS['wp_user_max'] );

$admin             = new WP_User();
$admin->user_login = 'siteowner';
$for_admin         = apply_filters( 'wp_is_application_passwords_available_for_user', true, $admin );
assert_true( 'other users keep application passwords', (bool) $for_admin );

$unset             = new WP_User();
$unset->user_login = false;
$unset->user_email = false;
$GLOBALS['current_user'] = $unset;
$created = Compath_Rehearsal_Demo_Guard::filter_user_data(
	array(
		'user_login' => 'localadmin',
		'user_pass'  => 'secret',
	),
	false,
	null,
	array()
);
assert_same( 'install can create a user while the current login is unset', 'localadmin', (string) $created['user_login'] );
$for_unset = apply_filters( 'wp_is_application_passwords_available_for_user', true, $unset );
assert_true( 'an unset login does not disable application passwords', (bool) $for_unset );

echo "OK demo guard recursion\n";
