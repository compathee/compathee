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
		add_action( 'admin_footer', array( self::class, 'license_screen_help' ) );
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

	/**
	 * Local check only (no network). Used for feature gates on every request.
	 */
	public static function is_licensed(): bool {
		$token = self::public_token();
		if ( '' === $token ) {
			// Token not configured yet — keep legacy unlock (install = Pro) for shop staging.
			return true;
		}

		$options = self::license_options();
		$activation_id = isset( $options['sc_activation_id'] ) ? (string) $options['sc_activation_id'] : '';
		$license_key   = isset( $options['sc_license_key'] ) ? (string) $options['sc_license_key'] : '';

		return '' !== $activation_id && '' !== $license_key;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function license_options(): array {
		// SureCart Settings option key: lowercase name without spaces + _license_options
		$key = 'compathchoirrehearsalpro_license_options';
		$opts = get_option( $key, array() );
		return is_array( $opts ) ? $opts : array();
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

	/**
	 * Install and update help on Choir Rehearsal → Pro License.
	 */
	public static function license_screen_help(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'choir-rehearsal-pro-license' !== $page ) {
			return;
		}

		echo '<div class="wrap" style="max-width:640px;margin-top:1.5em">';
		echo '<h2>' . esc_html__( 'Installing and updating', 'choir-rehearsal-pro' ) . '</h2>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Download the Pro zip from your shop.compath.ee account.', 'choir-rehearsal-pro' ) . '</li>';
		echo '<li>' . esc_html__( 'Upload it beside Choir Rehearsal (Lite): Plugins → Add New → Upload Plugin. Do not replace Lite.', 'choir-rehearsal-pro' ) . '</li>';
		echo '<li>' . esc_html__( 'Activate the license on this screen.', 'choir-rehearsal-pro' ) . '</li>';
		echo '</ol>';
		echo '<p>' . esc_html__( 'From version 0.5.0, WordPress installs newer Pro versions automatically (Dashboard → Updates or Plugins) while this license is active. On the Plugins screen you can also turn on auto-updates for Compath Choir Rehearsal Pro.', 'choir-rehearsal-pro' ) . '</p>';
		echo '<p>' . esc_html__( 'If you are on a Pro version older than 0.5.0, download 0.5.0 from your shop account once and replace the plugin: deactivate and delete the old Pro, then upload the new zip, or use WordPress “Replace current with uploaded”. Activate the license here. After that, no manual updates.', 'choir-rehearsal-pro' ) . '</p>';
		echo '</div>';
	}
}
