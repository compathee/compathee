<?php
/**
 * Demo distribution may only run on compath.ee hosts.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Demo_Host {

	public static function register(): void {
		if ( ! Choir_Rehearsal_Distribution::is_demo() ) {
			return;
		}

		add_action( 'admin_notices', array( self::class, 'render_blocked_notice' ) );
	}

	/**
	 * Whether the current site host is allowed for Demo.
	 */
	public static function is_allowed_host( ?string $host = null ): bool {
		if ( defined( 'CHOIR_REHEARSAL_DEMO_ALLOW_HOST' ) && CHOIR_REHEARSAL_DEMO_ALLOW_HOST ) {
			return true;
		}

		if ( null === $host ) {
			$host = self::current_host();
		}

		$host = strtolower( preg_replace( '/:\d+$/', '', (string) $host ) ?? '' );
		$host = trim( $host );

		if ( '' === $host ) {
			return false;
		}

		if ( 'compath.ee' === $host ) {
			return true;
		}

		return (bool) preg_match( '/\.compath\.ee$/', $host );
	}

	public static function current_host(): string {
		$url = home_url( '/' );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	public static function assert_allowed_or_deactivate(): bool {
		if ( ! Choir_Rehearsal_Distribution::is_demo() ) {
			return true;
		}

		if ( self::is_allowed_host() ) {
			return true;
		}

		update_option( 'choir_rehearsal_demo_host_blocked', 1, false );

		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( plugin_basename( CHOIR_REHEARSAL_FILE ), true );
		}

		return false;
	}

	public static function render_blocked_notice(): void {
		if ( ! get_option( 'choir_rehearsal_demo_host_blocked' ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$host = self::current_host();
		echo '<div class="notice notice-error"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: site hostname */
				__( 'Compath Choir Rehearsal Demo can only run on compath.ee domains (current host: %s).', 'compath-choir-rehearsal' ),
				$host !== '' ? $host : '(unknown)'
			)
		);
		echo '</p></div>';
	}
}
