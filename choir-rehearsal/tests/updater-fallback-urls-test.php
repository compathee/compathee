<?php
/**
 * Lightweight updater regression checks (no WordPress bootstrap, no agent logs).
 *
 * Run: php choir-rehearsal/tests/updater-fallback-urls-test.php
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$src  = file_get_contents( $root . '/includes/class-updater.php' );
if ( false === $src ) {
	fwrite( STDERR, "FAIL: cannot read class-updater.php\n" );
	exit( 1 );
}

$checks = array(
	'http_status_list' => (bool) preg_match( '/wp_remote_retrieve_response_code\(\s*\$response\s*\).*?\$status\s*<\s*200\s*\|\|\s*\$status\s*>=\s*300/s', $src )
		|| (bool) preg_match( '/\$status\s*=\s*\(int\)\s*wp_remote_retrieve_response_code/', $src ),
	'release_asset_fallback' => str_contains( $src, '/releases/latest/download/update.json' ),
	'legacy_main_fallback'   => str_contains( $src, '/main/choir-rehearsal/update.json' ),
	'per_page_100'           => str_contains( $src, 'per_page=100' ),
	'settings_redirect'      => str_contains( $src, 'choir-rehearsal-settings' )
		&& str_contains( $src, 'choir_rehearsal_update_check' ),
	'admin_notices'          => str_contains( $src, "add_action( 'admin_notices'" )
		&& str_contains( $src, 'render_check_notices' ),
	'no_agent_debug_log'     => ! str_contains( $src, '/opt/cursor/logs/debug.log' )
		&& ! str_contains( $src, 'hypothesisId' ),
	'array_is_list_guard'    => str_contains( $src, 'array_is_list' ),
);

$failed = array();
foreach ( $checks as $name => $ok ) {
	if ( ! $ok ) {
		$failed[] = $name;
	}
}

// Live: release-asset fallback must return JSON for current latest.
$fallback = 'https://github.com/compathee/compathee/releases/latest/download/update.json';
$ctx      = stream_context_create(
	array(
		'http' => array(
			'follow_location' => 1,
			'timeout'         => 20,
			'header'          => "User-Agent: Choir-Rehearsal-Updater-Test\r\nAccept: application/json\r\n",
		),
	)
);
$body = @file_get_contents( $fallback, false, $ctx );
$json = is_string( $body ) ? json_decode( $body, true ) : null;
if ( ! is_array( $json ) || empty( $json['version'] ) || empty( $json['download_url'] ) ) {
	$failed[] = 'live_release_asset_fallback';
}

if ( $failed ) {
	fwrite( STDERR, 'FAIL: ' . implode( ', ', $failed ) . "\n" );
	exit( 1 );
}

echo "PASS: updater safeguards present; live fallback version=" . $json['version'] . "\n";
exit( 0 );
