<?php
/**
 * Plugin Name:       Compath Choir Rehearsal
 * Plugin URI:        https://rehearsal.compath.ee
 * Description:       Private rehearsal library for choirs: songs, voice parts, audio tracks, and a sticky player.
 * Version:           0.4.48
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Compath OÜ
 * Author URI:        https://compath.ee
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       compath-choir-rehearsal
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * A second copy (e.g. old folder choir-rehearsal/ plus new compath-choir-rehearsal/)
 * must not fatally redeclare constants/functions. Show an admin notice instead.
 */
if ( defined( 'CHOIR_REHEARSAL_VERSION' ) || function_exists( 'choir_rehearsal' ) || class_exists( 'Choir_Rehearsal_Plugin', false ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			$paths = array();
			foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
				if ( ! is_string( $plugin_file ) ) {
					continue;
				}
				if ( str_ends_with( $plugin_file, '/choir-rehearsal.php' ) && ! str_contains( $plugin_file, 'choir-rehearsal-pro' ) ) {
					$paths[] = $plugin_file;
				}
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'Compath Choir Rehearsal is already loaded from another folder. Deactivate every duplicate Lite copy, then remove the extra folder via FTP (often compath-choir-rehearsal/). Keep only one folder — on existing sites usually wp-content/plugins/choir-rehearsal/. Do not use Delete in wp-admin if you need to keep songs.',
				'compath-choir-rehearsal'
			);
			if ( count( $paths ) > 1 ) {
				echo '</p><p><code>' . esc_html( implode( ', ', $paths ) ) . '</code>';
			}
			echo '</p></div>';
		}
	);
	return;
}

define( 'CHOIR_REHEARSAL_VERSION', '0.4.48' );
define( 'CHOIR_REHEARSAL_FILE', __FILE__ );
define( 'CHOIR_REHEARSAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHOIR_REHEARSAL_URL', plugin_dir_url( __FILE__ ) );
define( 'CHOIR_REHEARSAL_DOCS_URL', 'https://rehearsal.compath.ee/' );

// WordPress.org builds ship includes/distribution-wporg.php (disables GitHub self-updater).
if ( ! defined( 'CHOIR_REHEARSAL_DISTRIBUTION' ) ) {
	$choir_rehearsal_wporg = CHOIR_REHEARSAL_PATH . 'includes/distribution-wporg.php';
	if ( is_readable( $choir_rehearsal_wporg ) ) {
		require_once $choir_rehearsal_wporg;
	} else {
		define( 'CHOIR_REHEARSAL_DISTRIBUTION', 'github' );
	}
}

require_once CHOIR_REHEARSAL_PATH . 'includes/class-plugin.php';

/**
 * Returns the main plugin instance.
 */
function choir_rehearsal(): Choir_Rehearsal_Plugin {
	return Choir_Rehearsal_Plugin::instance();
}

choir_rehearsal();
