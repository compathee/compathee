<?php
/**
 * Plugin Name:       Compath Choir Rehearsal Pro
 * Plugin URI:        https://rehearsal.compath.ee
 * Description:       Unlocks song search, unlimited voice tracks, microphone recording, Play preview, PDF score badges, and song library backup for Choir Rehearsal. Requires a SureCart license from shop.compath.ee.
 * Version:           0.5.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Compath OÜ
 * Author URI:        https://compath.ee
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       choir-rehearsal-pro
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHOIR_REHEARSAL_PRO', true );
define( 'CHOIR_REHEARSAL_PRO_VERSION', '0.5.0' );
define( 'CHOIR_REHEARSAL_PRO_FILE', __FILE__ );
define( 'CHOIR_REHEARSAL_PRO_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Load optional Pro modules when present (avoids fatals after partial uploads).
 */
function choir_rehearsal_pro_load_includes(): void {
	$licensing = CHOIR_REHEARSAL_PRO_PATH . 'includes/class-licensing.php';
	if ( is_readable( $licensing ) ) {
		require_once $licensing;
	}

	$backup_file = CHOIR_REHEARSAL_PRO_PATH . 'includes/class-backup.php';
	if ( is_readable( $backup_file ) ) {
		require_once $backup_file;
	}
}

choir_rehearsal_pro_load_includes();

/**
 * Ensure the base plugin is active; register Pro modules.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( 'Choir_Rehearsal_Pro_Licensing', false ) ) {
			Choir_Rehearsal_Pro_Licensing::register();
		}

		if ( class_exists( 'Choir_Rehearsal_Pro_Backup', false ) ) {
			Choir_Rehearsal_Pro_Backup::register();
		} else {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'manage_options' ) ) {
						return;
					}
					echo '<div class="notice notice-warning"><p>';
					esc_html_e( 'Compath Choir Rehearsal Pro is missing includes/class-backup.php. Re-upload the full Pro plugin folder to enable backup.', 'choir-rehearsal-pro' );
					echo '</p></div>';
				}
			);
		}

		if ( defined( 'CHOIR_REHEARSAL_VERSION' ) ) {
			return;
		}

		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Compath Choir Rehearsal Pro requires Compath Choir Rehearsal to be installed and active.', 'choir-rehearsal-pro' );
				echo '</p></div>';
			}
		);
	},
	20
);

/**
 * Pro features require an active SureCart license (local activation_id).
 * Lite consults this filter; see Choir_Rehearsal_Edition::is_pro().
 */
add_filter(
	'choir_rehearsal_is_pro',
	static function ( bool $is_pro ): bool {
		if ( class_exists( 'Choir_Rehearsal_Pro_Licensing', false ) ) {
			return Choir_Rehearsal_Pro_Licensing::is_licensed();
		}

		return $is_pro;
	}
);

add_filter(
	'choir_rehearsal_upgrade_url',
	static function (): string {
		return 'https://shop.compath.ee/products/choir-rehearsal-pro/';
	}
);
