<?php
/**
 * Save or restore the Choir Rehearsal demo baseline.
 *
 * CLI only. The must-use plugin must already be installed.
 *
 *   WP_ROOT=/demo.rehearsal.compath.ee php scripts/compath-demo-restore.php save
 *   WP_ROOT=/demo.rehearsal.compath.ee php scripts/compath-demo-restore.php restore
 *
 * Default command is restore. WP_ROOT defaults to the demo WordPress root.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script is CLI only.\n" );
	exit( 1 );
}

$root    = getenv( 'WP_ROOT' );
$wp_root = is_string( $root ) && '' !== $root ? $root : '/demo.rehearsal.compath.ee';
$wp_load = rtrim( $wp_root, '/' ) . '/wp-load.php';

if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}\nSet WP_ROOT to the WordPress directory.\n" );
	exit( 1 );
}

require $wp_load;

if ( ! class_exists( 'Compath_Rehearsal_Demo_Guard' ) ) {
	fwrite( STDERR, "Must-use plugin compath-rehearsal-demo-guard.php is not loaded.\n" );
	exit( 1 );
}

$command = $argv[1] ?? 'restore';
if ( 'save' === $command ) {
	$result = Compath_Rehearsal_Demo_Guard::save_baseline();
} elseif ( 'restore' === $command ) {
	$result = Compath_Rehearsal_Demo_Guard::restore_baseline( 'cron' );
} else {
	fwrite( STDERR, "Usage: php compath-demo-restore.php [save|restore]\n" );
	exit( 1 );
}

$json = json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
fwrite( STDOUT, ( is_string( $json ) ? $json : '{}' ) . "\n" );
exit( ! empty( $result['ok'] ) ? 0 : 1 );
