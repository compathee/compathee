<?php
/**
 * Main plugin bootstrap.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CHOIR_REHEARSAL_PATH . 'includes/class-edition.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-post-types.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-voice-types.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-pages.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-access.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-admin.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-recording.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-frontend.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-rest.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-abilities.php';
require_once CHOIR_REHEARSAL_PATH . 'includes/class-updater.php';

final class Choir_Rehearsal_Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( CHOIR_REHEARSAL_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( CHOIR_REHEARSAL_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
		add_action( 'init', array( $this, 'init' ), 5 );
		Choir_Rehearsal_Updater::register();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'choir-rehearsal',
			false,
			dirname( plugin_basename( CHOIR_REHEARSAL_FILE ) ) . '/languages'
		);
	}

	public function init(): void {
		Choir_Rehearsal_Post_Types::register();
		Choir_Rehearsal_Voice_Types::register();
		Choir_Rehearsal_Pages::register();
		Choir_Rehearsal_Access::register();
		Choir_Rehearsal_Admin::register();
		Choir_Rehearsal_Recording::register();
		Choir_Rehearsal_Frontend::register();
		Choir_Rehearsal_REST::register();
		Choir_Rehearsal_Abilities::register();
	}

	public function maybe_upgrade(): void {
		Choir_Rehearsal_Pages::maybe_upgrade();
	}

	public function activate(): void {
		$this->init();
		Choir_Rehearsal_Voice_Types::seed_default_terms();
		Choir_Rehearsal_Post_Types::register();
		Choir_Rehearsal_Pages::ensure_library_page();
		flush_rewrite_rules( false );
		update_option( Choir_Rehearsal_Pages::OPTION_VERSION, CHOIR_REHEARSAL_VERSION );
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}
}
