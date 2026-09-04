<?php
/**
 * Plugin Name:       Choir Rehearsal Pro
 * Plugin URI:        https://rehearsal.compath.ee
 * Description:       Unlocks song search, unlimited voice tracks, and microphone recording for Choir Rehearsal.
 * Version:           0.4.7
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Requires Plugins:  choir-rehearsal
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
define( 'CHOIR_REHEARSAL_PRO_VERSION', '0.4.7' );
define( 'CHOIR_REHEARSAL_PRO_FILE', __FILE__ );

/**
 * Ensure the base plugin is active.
 */
add_action( 'plugins_loaded', static function (): void {
	if ( ! defined( 'CHOIR_REHEARSAL_VERSION' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Choir Rehearsal Pro requires the Choir Rehearsal plugin to be installed and active.', 'choir-rehearsal-pro' );
				echo '</p></div>';
			}
		);
	}
}, 5 );

/**
 * Future: SureCart Licensing SDK validates the purchase and toggles Pro features.
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
