<?php
/**
 * SureCart licensing for Choir Rehearsal Pro.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Pro_Licensing {

	/** @var \SureCart\Licensing\Client|null */
	private static $client = null;

	public static function register(): void {
		add_action( 'init', array( self::class, 'boot' ), 5 );
		add_action( 'admin_notices', array( self::class, 'maybe_admin_notices' ) );
	}

	public static function boot(): void {
		$token = self::public_token();
		if ( '' === $token ) {
			return;
		}

		$client_file = CHOIR_REHEARSAL_PRO_PATH . 'licensing/src/Client.php';
		if ( ! is_readable( $client_file ) ) {
			return;
		}

		if ( ! class_exists( '\SureCart\Licensing\Client', false ) ) {
			require_once $client_file;
		}

		self::$client = new \SureCart\Licensing\Client(
			'Compath Choir Rehearsal Pro',
			$token,
			CHOIR_REHEARSAL_PRO_FILE
		);
		self::$client->set_textdomain( 'choir-rehearsal-pro' );

		$parent = 'edit.php?post_type=choir_song';
		self::$client->settings()->add_page(
			array(
				'type'                 => 'submenu',
				'parent_slug'          => $parent,
				'page_title'           => __( 'Pro License', 'choir-rehearsal-pro' ),
				'menu_title'           => __( 'Pro License', 'choir-rehearsal-pro' ),
				'capability'           => 'manage_options',
				'menu_slug'            => 'choir-rehearsal-pro-license',
				'activated_redirect'   => admin_url( $parent . '&page=choir-rehearsal-pro-license' ),
				'deactivated_redirect' => admin_url( $parent . '&page=choir-rehearsal-pro-license' ),
			)
		);
	}

	public const OPTION_KEY = 'compathchoirrehearsalpro_license_options';

	private const CACHE_KEY = 'choir_rehearsal_pro_license_cache';

	/**
	 * Local check only (no network). Used for feature gates on every request.
	 */
	public static function is_licensed(): bool {
		$token = self::public_token();
		if ( '' === $token ) {
			// Token not configured yet — keep legacy unlock (install = Pro) for shop staging.
			return true;
		}

		$options = self::stored_options();
		$activation_id = isset( $options['sc_activation_id'] ) ? (string) $options['sc_activation_id'] : '';
		$license_key   = isset( $options['sc_license_key'] ) ? (string) $options['sc_license_key'] : '';

		return '' !== $activation_id && '' !== $license_key;
	}

	/**
	 * Local SureCart option array. Does not call the API.
	 *
	 * @return array<string, mixed>
	 */
	public static function stored_options(): array {
		// SureCart Settings option key: lowercase name without spaces + _license_options
		$opts = get_option( self::OPTION_KEY, array() );
		return is_array( $opts ) ? $opts : array();
	}

	/**
	 * Fill customer, purchase, order, and status from SureCart at most every 12 hours.
	 * Feedback still works from the values already stored at activation when this fails.
	 */
	public static function refresh_cached_details(): void {
		if ( ! function_exists( 'get_transient' ) || false !== get_transient( self::CACHE_KEY ) ) {
			return;
		}

		$ttl = 12 * 3600;
		if ( ! self::$client instanceof \SureCart\Licensing\Client ) {
			set_transient( self::CACHE_KEY, 'skip', 3600 );
			return;
		}

		$key = (string) self::$client->settings()->license_key;
		if ( '' === $key ) {
			set_transient( self::CACHE_KEY, 'skip', 3600 );
			return;
		}

		$license = self::$client->license()->retrieve( $key );
		if ( ! is_wp_error( $license ) ) {
			\SureCart\Licensing\License::remember_public_details( self::$client->settings(), $license );
		} else {
			$ttl = 3600;
		}

		set_transient( self::CACHE_KEY, '1', $ttl );
	}

	public static function public_token(): string {
		if ( defined( 'CHOIR_REHEARSAL_PRO_PUBLIC_TOKEN' ) ) {
			$token = (string) CHOIR_REHEARSAL_PRO_PUBLIC_TOKEN;
			if ( '' !== $token ) {
				return $token;
			}
		}

		$env = getenv( 'SURECART_PUBLIC_TOKEN' );
		if ( is_string( $env ) && '' !== $env ) {
			return $env;
		}

		$config_file = CHOIR_REHEARSAL_PRO_PATH . 'includes/surecart-config.php';
		if ( is_readable( $config_file ) ) {
			$config = include $config_file;
			if ( is_array( $config ) && ! empty( $config['public_token'] ) ) {
				return (string) $config['public_token'];
			}
		}

		return '';
	}

	public static function maybe_admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( '' === self::public_token() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__(
				'Choir Rehearsal Pro: SureCart public token is missing. Add it to includes/surecart-config.php (or CHOIR_REHEARSAL_PRO_PUBLIC_TOKEN) before shipping the shop zip.',
				'choir-rehearsal-pro'
			);
			echo '</p></div>';
			return;
		}

		if ( ! self::is_licensed() ) {
			$url = admin_url( 'edit.php?post_type=choir_song&page=choir-rehearsal-pro-license' );
			echo '<div class="notice notice-info"><p>';
			printf(
				/* translators: %s: license settings URL */
				esc_html__( 'Choir Rehearsal Pro is installed but not activated. Enter your license key under %s to unlock Pro features.', 'choir-rehearsal-pro' ),
				'<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Choir Rehearsal → Pro License', 'choir-rehearsal-pro' ) . '</strong></a>'
			);
			echo '</p></div>';
		}
	}
}
