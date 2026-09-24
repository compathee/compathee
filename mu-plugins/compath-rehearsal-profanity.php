<?php
/**
 * Demo profanity filter for Choir Rehearsal.
 *
 * Install this file at wp-content/mu-plugins/compath-rehearsal-profanity.php
 * and the bundled class at wp-content/mu-plugins/compath-rehearsal-profanity/class-profanity.php.
 * WordPress loads only PHP files in the mu-plugins directory itself, so the
 * class file in the subdirectory is not loaded on its own.
 *
 * When a plugin release that contains includes/class-profanity.php is installed,
 * that copy is used. Until then, the bundled class enforces the filter for every
 * user who cannot manage site settings.
 *
 * @package Compath_Rehearsal_Demo_Guard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Choir_Rehearsal_Profanity', false ) ) {
	$plugin_class = dirname( __DIR__ ) . '/plugins/choir-rehearsal/includes/class-profanity.php';
	if ( is_readable( $plugin_class ) ) {
		require_once $plugin_class;
	}
}

if ( ! class_exists( 'Choir_Rehearsal_Profanity', false ) ) {
	$bundled = __DIR__ . '/compath-rehearsal-profanity/class-profanity.php';
	if ( is_readable( $bundled ) ) {
		require_once $bundled;
	}
}

if ( class_exists( 'Choir_Rehearsal_Profanity', false ) ) {
	Choir_Rehearsal_Profanity::register_demo();
}
