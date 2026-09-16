<?php
/**
 * Replace an existing Lite folder on upload/update instead of adding a second copy.
 *
 * Works for choir-rehearsal/ and compath-choir-rehearsal/ (GitHub and WordPress.org zips).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Install_Replace {

	private const KNOWN_FOLDERS = array( 'choir-rehearsal', 'compath-choir-rehearsal' );

	/** @var bool */
	private static bool $handling_lite_package = false;

	/** @var string Folder slug under wp-content/plugins (no slashes). */
	private static string $target_folder = '';

	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'upgrader_source_selection', array( self::class, 'upgrader_source_selection' ), 10, 4 );
		add_filter( 'upgrader_clear_destination', array( self::class, 'upgrader_clear_destination' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( self::class, 'after_process_complete' ), 10, 2 );
	}

	/**
	 * @param string      $source        Path to unpacked package (trailing slash).
	 * @param string      $remote_source Parent of $source.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $hook_extra    Extra data (plugin basename on updates).
	 * @return string|\WP_Error
	 */
	public static function upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		self::$handling_lite_package = false;
		self::$target_folder           = '';

		if ( ! is_string( $source ) || '' === $source || ! is_object( $wp_filesystem ) ) {
			return $source;
		}

		if ( ! is_array( $hook_extra ) ) {
			$hook_extra = array();
		}

		if ( ! self::is_lite_package_source( $source, $wp_filesystem ) ) {
			return $source;
		}

		$desired = self::resolve_target_folder( $hook_extra, $wp_filesystem );
		if ( '' === $desired ) {
			return $source;
		}

		self::$handling_lite_package = true;
		self::$target_folder         = $desired;

		$current = basename( untrailingslashit( str_replace( '\\', '/', $source ) ) );
		if ( $current === $desired ) {
			return $source;
		}

		$new_source = trailingslashit( $remote_source ) . $desired;
		if ( $wp_filesystem->exists( $new_source ) ) {
			$wp_filesystem->delete( $new_source, true );
		}

		if ( ! $wp_filesystem->move( $source, $new_source ) ) {
			self::$handling_lite_package = false;
			self::$target_folder         = '';

			return new WP_Error(
				'choir_rehearsal_upgrade_folder',
				sprintf(
					/* translators: 1: folder from zip, 2: installed folder */
					__( 'Could not map update folder %1$s to installed folder %2$s.', 'compath-choir-rehearsal' ),
					$current,
					$desired
				)
			);
		}

		return trailingslashit( $new_source );
	}

	/**
	 * Replace contents of an existing Lite folder instead of failing with "folder already exists".
	 *
	 * @param bool   $clear             Whether to clear the destination.
	 * @param string $local_destination Local filesystem path.
	 * @param string $remote_destination Remote filesystem path.
	 * @param array  $hook_extra        Hook extra.
	 */
	public static function upgrader_clear_destination( $clear, $local_destination, $remote_destination, $hook_extra = array() ): bool {
		if ( $clear || ! self::$handling_lite_package || '' === self::$target_folder ) {
			return (bool) $clear;
		}

		if ( ! is_array( $hook_extra ) ) {
			$hook_extra = array();
		}

		$type = (string) ( $hook_extra['type'] ?? '' );
		if ( 'plugin' !== $type ) {
			return (bool) $clear;
		}

		global $wp_filesystem;
		if ( ! is_object( $wp_filesystem ) ) {
			return (bool) $clear;
		}

		$dest_folder = basename( untrailingslashit( str_replace( '\\', '/', (string) $local_destination ) ) );
		if ( $dest_folder !== self::$target_folder ) {
			return (bool) $clear;
		}

		if ( $wp_filesystem->exists( $remote_destination ) ) {
			return true;
		}

		return (bool) $clear;
	}

	/**
	 * @param WP_Upgrader $upgrader
	 * @param array<string, mixed> $options
	 */
	public static function after_process_complete( $upgrader, array $options ): void {
		$was_lite = self::$handling_lite_package;
		$target   = self::$target_folder;

		self::$handling_lite_package = false;
		self::$target_folder         = '';

		if ( ( $options['type'] ?? '' ) !== 'plugin' ) {
			return;
		}

		$action = (string) ( $options['action'] ?? '' );
		if ( ! in_array( $action, array( 'install', 'update' ), true ) ) {
			return;
		}

		if ( ! $was_lite && '' === $target ) {
			$installed = self::installed_lite_basenames_from_options( $options );
			if ( empty( $installed ) ) {
				return;
			}
			$target = dirname( $installed[0] );
		}

		if ( '' === $target ) {
			return;
		}

		self::remove_other_lite_folders( $target );

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}
	}

	/**
	 * @param array<string, mixed> $hook_extra
	 */
	private static function resolve_target_folder( array $hook_extra, $wp_filesystem ): string {
		$plugin = (string) ( $hook_extra['plugin'] ?? '' );
		if ( '' !== $plugin && self::is_lite_plugin_basename( $plugin ) ) {
			return dirname( $plugin );
		}

		if ( defined( 'CHOIR_REHEARSAL_FILE' ) ) {
			$installed = plugin_basename( CHOIR_REHEARSAL_FILE );
			if ( '' !== $plugin && $plugin === $installed ) {
				return dirname( $installed );
			}
		}

		$installs = Choir_Rehearsal_Migration::find_lite_installs();
		if ( ! empty( $installs ) ) {
			return Choir_Rehearsal_Migration::suggested_keep_folder( $installs );
		}

		foreach ( self::KNOWN_FOLDERS as $folder ) {
			$main = trailingslashit( WP_PLUGIN_DIR ) . $folder . '/choir-rehearsal.php';
			if ( $wp_filesystem->exists( $main ) ) {
				return $folder;
			}
		}

		return '';
	}

	/**
	 * @param object $wp_filesystem
	 */
	private static function is_lite_package_source( string $source, $wp_filesystem ): bool {
		$main_file = trailingslashit( $source ) . 'choir-rehearsal.php';
		if ( ! $wp_filesystem->exists( $main_file ) ) {
			return false;
		}

		if ( $wp_filesystem->exists( trailingslashit( $source ) . 'choir-rehearsal-pro.php' ) ) {
			return false;
		}

		return true;
	}

	private static function is_lite_plugin_basename( string $plugin ): bool {
		return str_ends_with( $plugin, '/choir-rehearsal.php' ) && ! str_contains( $plugin, 'choir-rehearsal-pro' );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<string>
	 */
	private static function installed_lite_basenames_from_options( array $options ): array {
		$candidates = array();

		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$candidates = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
			$candidates = array( $options['plugin'] );
		}

		$lite = array();
		foreach ( $candidates as $plugin ) {
			$plugin = (string) $plugin;
			if ( self::is_lite_plugin_basename( $plugin ) ) {
				$lite[] = $plugin;
			}
		}

		return $lite;
	}

	private static function remove_other_lite_folders( string $keep_folder ): void {
		$installs = Choir_Rehearsal_Migration::find_lite_installs();
		if ( count( $installs ) < 2 ) {
			return;
		}

		$keep_file = $keep_folder . '/choir-rehearsal.php';

		foreach ( $installs as $row ) {
			if ( $row['file'] === $keep_file || $row['folder'] === $keep_folder ) {
				continue;
			}

			Choir_Rehearsal_Migration::delete_plugin_folder( $row['dir'] );
		}

		$active = (array) get_option( 'active_plugins', array() );
		$next   = array();
		foreach ( $active as $plugin ) {
			$plugin = (string) $plugin;
			if ( self::is_lite_plugin_basename( $plugin ) && $plugin !== $keep_file ) {
				continue;
			}
			$next[] = $plugin;
		}
		update_option( 'active_plugins', array_values( $next ) );
	}
}
