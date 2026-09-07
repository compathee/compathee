<?php
/**
 * Minimal WP stubs + updater repro for Check for updates failure modes.
 * Run: php choir-rehearsal/tests/updater-repro-bootstrap.php
 */

declare(strict_types=1);

define( 'ABSPATH', '/tmp/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WP_PLUGIN_DIR', '/tmp/plugins' );

$GLOBALS['cr_remote_mock'] = array();
$GLOBALS['cr_options']     = array(
	'choir_rehearsal_update_json_url' => '',
	'choir_rehearsal_github_repo'     => 'compathee/compathee',
);
$GLOBALS['cr_transients']  = array();
$GLOBALS['cr_log']         = '/opt/cursor/logs/debug.log';

function cr_debug_log( string $hypothesisId, string $location, string $message, array $data = array() ): void {
	$line = wp_json_encode(
		array(
			'hypothesisId' => $hypothesisId,
			'location'     => $location,
			'message'      => $message,
			'data'         => $data,
			'timestamp'    => (int) ( microtime( true ) * 1000 ),
		)
	);
	file_put_contents( $GLOBALS['cr_log'], $line . "\n", FILE_APPEND );
}

function is_admin(): bool { return true; }
function add_filter( ...$args ): void {}
function add_action( ...$args ): void {}
function current_user_can( string $cap ): bool { return $cap === 'update_plugins'; }
function check_admin_referer( string $action ): void {}
function wp_die( string $message ): void { throw new RuntimeException( $message ); }
function esc_html__( string $text, string $domain = '' ): string { return $text; }
function __ ( string $text, string $domain = '' ): string { return $text; }
function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function wp_nonce_url( string $url, string $action ): string { return $url . '&_wpnonce=test'; }
function wp_safe_redirect( string $url ): void { $GLOBALS['cr_redirect'] = $url; }
function get_option( string $key, $default = false ) {
	return $GLOBALS['cr_options'][ $key ] ?? $default;
}
function get_transient( string $key ) {
	return $GLOBALS['cr_transients'][ $key ] ?? false;
}
function set_transient( string $key, $value, int $expiration = 0 ): bool {
	$GLOBALS['cr_transients'][ $key ] = $value;
	return true;
}
function delete_transient( string $key ): bool {
	unset( $GLOBALS['cr_transients'][ $key ] );
	return true;
}
function delete_site_transient( string $key ): bool {
	unset( $GLOBALS['cr_transients'][ 'site_' . $key ] );
	return true;
}
function get_site_transient( string $key ) {
	return $GLOBALS['cr_transients'][ 'site_' . $key ] ?? false;
}
function set_site_transient( string $key, $value, int $expiration = 0 ): bool {
	$GLOBALS['cr_transients'][ 'site_' . $key ] = $value;
	return true;
}
function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function wp_remote_retrieve_body( $response ): string {
	return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
}
function wp_remote_retrieve_response_code( $response ): int {
	return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
}
function wp_json_encode( $data ): string {
	return json_encode( $data, JSON_UNESCAPED_SLASHES ) ?: '{}';
}
function wp_remote_get( string $url, array $args = array() ) {
	foreach ( $GLOBALS['cr_remote_mock'] as $mock ) {
		if ( str_contains( $url, $mock['match'] ) ) {
			cr_debug_log(
				$mock['hypothesisId'] ?? 'A',
				'updater-repro-bootstrap.php:wp_remote_get',
				'mocked HTTP',
				array(
					'url'    => $url,
					'status' => $mock['status'],
					'body'   => substr( (string) $mock['body'], 0, 120 ),
				)
			);
			if ( ! empty( $mock['error'] ) ) {
				return new WP_Error( 'http_request_failed', 'mocked error' );
			}
			return array(
				'body'     => (string) $mock['body'],
				'response' => array( 'code' => (int) $mock['status'] ),
			);
		}
	}
	cr_debug_log( 'A', 'updater-repro-bootstrap.php:wp_remote_get', 'unmocked URL', array( 'url' => $url ) );
	return new WP_Error( 'http_request_failed', 'unmocked' );
}

final class WP_Error {
	public function __construct( public string $code = '', public string $message = '' ) {}
}

require_once dirname( __DIR__ ) . '/includes/class-updater.php';

/**
 * @return array<string, mixed>|null
 */
function cr_call_fetch_remote(): ?array {
	$m = new ReflectionMethod( Choir_Rehearsal_Updater::class, 'fetch_remote_metadata' );
	$m->setAccessible( true );
	return $m->invoke( null );
}

function cr_call_default_fallback_url(): string {
	$m = new ReflectionMethod( Choir_Rehearsal_Updater::class, 'default_update_json_url' );
	$m->setAccessible( true );
	return (string) $m->invoke( null );
}

function cr_scenario_rate_limit_then_dead_fallback(): void {
	$GLOBALS['cr_transients'] = array();
	$GLOBALS['cr_remote_mock'] = array(
		array(
			'match'         => 'api.github.com/repos/compathee/compathee/releases?',
			'status'        => 403,
			'body'          => wp_json_encode(
				array(
					'message'           => 'API rate limit exceeded for xxx.',
					'documentation_url' => 'https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
				)
			),
			'hypothesisId'  => 'A',
		),
		array(
			'match'         => 'raw.githubusercontent.com/compathee/compathee/main/choir-rehearsal/update.json',
			'status'        => 404,
			'body'          => '404: Not Found',
			'hypothesisId'  => 'A',
		),
	);

	$fallback = cr_call_default_fallback_url();
	$result   = cr_call_fetch_remote();
	cr_debug_log(
		'A',
		'updater-repro-bootstrap.php:scenario_rate_limit',
		'fetch_remote_metadata after GitHub 403 + main fallback 404',
		array(
			'fallback_url' => $fallback,
			'result_null'  => null === $result,
			'result'       => $result,
		)
	);
}

function cr_scenario_github_ok(): void {
	$GLOBALS['cr_transients'] = array();
	$release = array(
		array(
			'tag_name'     => 'choir-rehearsal-v0.4.20',
			'draft'        => false,
			'prerelease'   => false,
			'published_at' => '2026-09-07T07:43:49Z',
			'body'         => 'fix',
			'assets'       => array(
				array(
					'name'                 => 'choir-rehearsal.zip',
					'browser_download_url' => 'https://github.com/compathee/compathee/releases/download/choir-rehearsal-v0.4.20/choir-rehearsal.zip',
				),
			),
		),
	);
	$GLOBALS['cr_remote_mock'] = array(
		array(
			'match'        => 'api.github.com/repos/compathee/compathee/releases?',
			'status'       => 200,
			'body'         => wp_json_encode( $release ),
			'hypothesisId' => 'E',
		),
	);
	$result = cr_call_fetch_remote();
	cr_debug_log(
		'E',
		'updater-repro-bootstrap.php:scenario_github_ok',
		'fetch_remote_metadata on healthy GitHub list',
		array(
			'version'      => $result['version'] ?? null,
			'download_url' => $result['download_url'] ?? null,
		)
	);
}

function cr_scenario_check_redirect_has_no_notice_hook(): void {
	$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-updater.php' );
	$admin = file_get_contents( dirname( __DIR__ ) . '/includes/class-admin.php' );
	$all = $src . "\n" . $admin;
	$has_query = str_contains( $src, 'choir_rehearsal_checked=1' );
	$has_notice_handler = (bool) preg_match( '/choir_rehearsal_checked/', $all )
		&& (bool) preg_match( '/admin_notices/', $all );
	// More precise: notice handler that reads the query arg.
	$handles_checked = (bool) preg_match( '/\$_GET\s*\[\s*[\'"]choir_rehearsal_checked[\'"]\s*\]/', $all )
		|| (bool) preg_match( '/isset\(\s*\$_GET\s*\[\s*[\'"]choir_rehearsal_checked/', $all );

	cr_debug_log(
		'C',
		'updater-repro-bootstrap.php:scenario_notice',
		'Check-for-updates redirect vs admin notice handling',
		array(
			'redirect_sets_query_arg' => $has_query,
			'code_reads_query_arg'    => $handles_checked,
			'admin_notices_anywhere'  => (bool) preg_match( '/admin_notices/', $all ),
		)
	);
}

function cr_scenario_live_fallback_404(): void {
	// Live network check of documented fallback (no WP mock).
	$url = 'https://raw.githubusercontent.com/compathee/compathee/main/choir-rehearsal/update.json';
	$ctx = stream_context_create( array( 'http' => array( 'ignore_errors' => true, 'timeout' => 15 ) ) );
	$body = @file_get_contents( $url, false, $ctx );
	$status = 0;
	if ( isset( $http_response_header[0] ) && preg_match( '/\s(\d{3})\s/', $http_response_header[0], $m ) ) {
		$status = (int) $m[1];
	}
	cr_debug_log(
		'A',
		'updater-repro-bootstrap.php:live_fallback',
		'Live GET of default update.json fallback on main',
		array(
			'url'    => $url,
			'status' => $status,
			'body'   => is_string( $body ) ? substr( $body, 0, 80 ) : null,
		)
	);
}

function cr_scenario_missing_http_status_check(): void {
	$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-updater.php' );
	$list_fn = false;
	if ( preg_match( '/function fetch_latest_choir_release_from_list.*?return is_array/s', $src, $m ) ) {
		$list_fn = ! str_contains( $m[0], 'wp_remote_retrieve_response_code' );
	}
	$request_json_missing = false;
	if ( preg_match( '/function request_json.*?return is_array/s', $src, $m ) ) {
		$request_json_missing = ! str_contains( $m[0], 'wp_remote_retrieve_response_code' );
	}
	cr_debug_log(
		'A',
		'updater-repro-bootstrap.php:status_checks',
		'HTTP status check presence in updater fetch helpers',
		array(
			'list_fetch_ignores_http_status' => $list_fn,
			'request_json_ignores_http_status' => $request_json_missing,
		)
	);
}

echo "Running updater repro scenarios...\n";
cr_scenario_missing_http_status_check();
cr_scenario_live_fallback_404();
cr_scenario_rate_limit_then_dead_fallback();
cr_scenario_github_ok();
cr_scenario_check_redirect_has_no_notice_hook();
echo "Done. Logs: {$GLOBALS['cr_log']}\n";
