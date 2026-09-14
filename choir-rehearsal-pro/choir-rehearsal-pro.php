<?php
/**
 * Plugin Name:       Compath Choir Rehearsal Pro
 * Plugin URI:        https://rehearsal.compath.ee
 * Description:       Unlocks song search, unlimited voice tracks, microphone recording, Play preview, floating PDF score view, and song library backup for Choir Rehearsal.
 * Version:           0.4.48
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
define( 'CHOIR_REHEARSAL_PRO_VERSION', '0.4.48' );
define( 'CHOIR_REHEARSAL_PRO_FILE', __FILE__ );
define( 'CHOIR_REHEARSAL_PRO_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Load optional Pro modules when present (avoids fatals after partial uploads).
 */
function choir_rehearsal_pro_load_includes(): void {
	$backup_file = CHOIR_REHEARSAL_PRO_PATH . 'includes/class-backup.php';
	if ( is_readable( $backup_file ) ) {
		require_once $backup_file;
	}
}

choir_rehearsal_pro_load_includes();

/**
 * Ensure the base plugin is active.
 */
add_action(
	'plugins_loaded',
	static function (): void {
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
 * Future: shop.compath.ee licensing validates the purchase and toggles Pro features.
 * Until then, installing this plugin on a licensed site unlocks Pro for the whole network.
 */
add_filter(
	'choir_rehearsal_is_pro',
	static function ( bool $is_pro ): bool {
		return true;
	}
);

add_filter(
	'choir_rehearsal_upgrade_url',
	static function (): string {
		return 'https://shop.compath.ee/products/choir-rehearsal-pro/';
	}
);
