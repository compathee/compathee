<?php
/**
 * Plugin Name:       Choir Rehearsal
 * Plugin URI:        https://veneta.ee
 * Description:       Private rehearsal library for choirs: songs, voice parts, audio tracks, and a sticky player.
 * Version:           0.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Cappella Veneta
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       choir-rehearsal
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHOIR_REHEARSAL_VERSION', '0.3.0' );
define( 'CHOIR_REHEARSAL_FILE', __FILE__ );
define( 'CHOIR_REHEARSAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHOIR_REHEARSAL_URL', plugin_dir_url( __FILE__ ) );

require_once CHOIR_REHEARSAL_PATH . 'includes/class-plugin.php';

/**
 * Returns the main plugin instance.
 */
function choir_rehearsal(): Choir_Rehearsal_Plugin {
	return Choir_Rehearsal_Plugin::instance();
}

choir_rehearsal();
