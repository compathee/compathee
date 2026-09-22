<?php
/**
 * Plugin Name:       Compath Choir Rehearsal
 * Plugin URI:        https://rehearsal.compath.ee
 * Description:       Private rehearsal library for choirs: songs, voice parts, audio or YouTube embeds, PDF scores, and a sticky player.
 * Version:           0.4.63
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
 * A second copy (e.g. choir-rehearsal/ plus compath-choir-rehearsal/) must not fatally
 * redeclare constants/functions. Track the primary loaded file globally (not VERSION alone).
 */
$choir_rehearsal_primary = isset( $GLOBALS['choir_rehearsal_lite_file'] )
	? (string) $GLOBALS['choir_rehearsal_lite_file']
	: '';
if ( '' !== $choir_rehearsal_primary && $choir_rehearsal_primary !== __FILE__ ) {
	if ( ! class_exists( 'Choir_Rehearsal_Migration', false ) ) {
		$choir_rehearsal_migration = __DIR__ . '/includes/class-migration.php';
		if ( is_readable( $choir_rehearsal_migration ) ) {
			require_once $choir_rehearsal_migration;
		}
	}
	if ( class_exists( 'Choir_Rehearsal_Migration', false ) ) {
		Choir_Rehearsal_Migration::register_duplicate_bootstrap( __FILE__ );
	}
	return;
}

define( 'CHOIR_REHEARSAL_VERSION', '0.4.63' );
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

$GLOBALS['choir_rehearsal_lite_file'] = __FILE__;
