<?php
/**
 * Detect and clean up duplicate Lite plugin folders (admin wizard).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Migration {

	private const PAGE_SLUG = 'choir-rehearsal-migration';

	private const OPTION_KEEP = 'choir_rehearsal_migration_keep';

	/** @var string Absolute path to the choir-rehearsal.php that registered the duplicate bootstrap. */
	private static string $bootstrap_file = '';

	/** @var bool */
	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		add_action( 'plugins_loaded', array( self::class, 'repair_active_plugins_early' ), 0 );
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 30 );
		add_action( 'admin_notices', array( self::class, 'maybe_notice' ) );
		add_action( 'admin_notices', array( self::class, 'maybe_repair_notice' ), 2 );
		add_action( 'admin_post_choir_rehearsal_migration_set_keep', array( self::class, 'handle_set_keep' ) );
		add_action( 'admin_post_choir_rehearsal_migration_deactivate_others', array( self::class, 'handle_deactivate_others' ) );
		add_action( 'admin_post_choir_rehearsal_migration_delete_others', array( self::class, 'handle_delete_others' ) );
		add_action( 'admin_post_choir_rehearsal_migration_activate_keep', array( self::class, 'handle_activate_keep' ) );
		add_action( 'admin_post_choir_rehearsal_migration_repair_active', array( self::class, 'handle_repair_active' ) );
	}

	/**
	 * Register wizard when this file is the duplicate copy (full plugin already loaded elsewhere).
	 */
	public static function register_duplicate_bootstrap( string $plugin_file ): void {
		self::$bootstrap_file = $plugin_file;
		self::repair_active_plugins( true );
		self::register();
		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				$url = self::wizard_url();
				echo '<div class="notice notice-error"><p>';
				echo esc_html__(
					'Compath Choir Rehearsal is loaded from more than one folder. Open the migration wizard to keep one copy and remove the extras. Songs stay in the database.',
					'compath-choir-rehearsal'
				);
				echo ' <a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Open migration wizard', 'compath-choir-rehearsal' ) . '</strong></a>';
				echo '</p></div>';
			},
			1
		);
	}

	public static function wizard_url( array $args = array() ): string {
		$args = array_merge(
			array(
				'page' => self::PAGE_SLUG,
			),
			$args
		);

		// Prefer CPT menu parent when Lite is fully loaded; otherwise top-level tools-style URL.
		if ( class_exists( 'Choir_Rehearsal_Post_Types', false ) ) {
			return add_query_arg( $args, admin_url( 'edit.php?post_type=' . Choir_Rehearsal_Post_Types::SONG ) );
		}

		return add_query_arg( $args, admin_url( 'tools.php' ) );
	}

	public static function register_menu(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$installs = self::find_lite_installs();
		if ( count( $installs ) < 2 && ! self::is_duplicate_bootstrap() && ! self::needs_active_plugins_repair() ) {
			return;
		}

		if ( class_exists( 'Choir_Rehearsal_Post_Types', false ) ) {
			add_submenu_page(
				'edit.php?post_type=' . Choir_Rehearsal_Post_Types::SONG,
				__( 'Fix duplicate install', 'compath-choir-rehearsal' ),
				__( 'Fix duplicate install', 'compath-choir-rehearsal' ),
				'activate_plugins',
				self::PAGE_SLUG,
				array( self::class, 'render_wizard' )
			);
			return;
		}

		add_management_page(
			__( 'Choir Rehearsal migration', 'compath-choir-rehearsal' ),
			__( 'Choir Rehearsal migration', 'compath-choir-rehearsal' ),
			'activate_plugins',
			self::PAGE_SLUG,
			array( self::class, 'render_wizard' )
		);
	}

	public static function maybe_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( self::is_duplicate_bootstrap() ) {
			return; // Dedicated notice already registered.
		}

		$installs = self::find_lite_installs();
		if ( count( $installs ) < 2 ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $_GET['page'] ) && self::PAGE_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'Multiple Compath Choir Rehearsal (Lite) folders were found. Use the migration wizard before relying on Pro or updates.',
			'compath-choir-rehearsal'
		);
		echo ' <a href="' . esc_url( self::wizard_url() ) . '"><strong>' . esc_html__( 'Open migration wizard', 'compath-choir-rehearsal' ) . '</strong></a>';
		echo '</p></div>';
	}

	public static function is_lite_basename( string $plugin ): bool {
		return str_ends_with( $plugin, '/choir-rehearsal.php' ) && ! str_contains( $plugin, 'choir-rehearsal-pro' );
	}

	/**
	 * @return list<array{file: string, dir: string, folder: string, version: string, name: string, active: bool, on_disk: bool}>
	 */
	public static function find_lite_installs(): array {
		$root = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
		if ( '' === $root || ! is_dir( $root ) ) {
			return array();
		}

		$active = array_map( 'strval', (array) get_option( 'active_plugins', array() ) );
		$by_file = array();

		$matches = glob( $root . '/*/choir-rehearsal.php' );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $main ) {
				$row = self::row_from_main_file( (string) $main, $active );
				if ( null !== $row ) {
					$by_file[ $row['file'] ] = $row;
				}
			}
		}

		foreach ( $active as $plugin ) {
			if ( ! self::is_lite_basename( $plugin ) || isset( $by_file[ $plugin ] ) ) {
				continue;
			}
			$main = $root . '/' . $plugin;
			$row  = self::row_from_main_file( $main, $active, false );
			if ( null !== $row ) {
				$by_file[ $row['file'] ] = $row;
			}
		}

		$found = array_values( $by_file );
		usort(
			$found,
			static function ( array $a, array $b ): int {
				if ( 'choir-rehearsal' === $a['folder'] ) {
					return -1;
				}
				if ( 'choir-rehearsal' === $b['folder'] ) {
					return 1;
				}
				return strnatcasecmp( $a['folder'], $b['folder'] );
			}
		);

		return $found;
	}

	/**
	 * @param list<string> $active
	 * @return array{file: string, dir: string, folder: string, version: string, name: string, active: bool, on_disk: bool}|null
	 */
	private static function row_from_main_file( string $main, array $active, bool $on_disk = true ): ?array {
		$main = str_replace( '\\', '/', $main );
		if ( str_contains( $main, 'choir-rehearsal-pro' ) ) {
			return null;
		}

		$folder = basename( dirname( $main ) );
		if ( str_contains( $folder, 'choir-rehearsal-pro' ) ) {
			return null;
		}

		$file         = $folder . '/choir-rehearsal.php';
		$file_exists  = is_readable( $main );
		$version      = '';
		$name         = '';

		if ( $file_exists ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$data = get_plugin_data( $main, false, false );
			$name = is_array( $data ) ? (string) ( $data['Name'] ?? '' ) : '';
			if ( '' !== $name && false === stripos( $name, 'Choir Rehearsal' ) ) {
				return null;
			}
			$version = is_array( $data ) ? (string) ( $data['Version'] ?? '' ) : '';
		}

		return array(
			'file'    => $file,
			'dir'     => dirname( $main ),
			'folder'  => $folder,
			'version' => $version,
			'name'    => '' !== $name ? $name : __( 'Missing or unreadable', 'compath-choir-rehearsal' ),
			'active'  => in_array( $file, $active, true ),
			'on_disk' => $file_exists && $on_disk,
		);
	}

	/**
	 * @return list<string>
	 */
	public static function find_active_lite_basenames(): array {
		$lite = array();
		foreach ( array_map( 'strval', (array) get_option( 'active_plugins', array() ) ) as $plugin ) {
			if ( self::is_lite_basename( $plugin ) ) {
				$lite[] = $plugin;
			}
		}
		return $lite;
	}

	public static function repair_active_plugins_early(): void {
		self::repair_active_plugins( true );
	}

	/**
	 * Keep one Lite row in active_plugins; drop ghosts and duplicates.
	 */
	public static function repair_active_plugins( bool $persist = true ): bool {
		$active   = array_map( 'strval', (array) get_option( 'active_plugins', array() ) );
		$installs = self::find_lite_installs();
		$keep     = self::suggested_keep_file( $installs );

		$disk_keep = '';
		foreach ( $installs as $row ) {
			if ( $row['on_disk'] && is_readable( WP_PLUGIN_DIR . '/' . $row['file'] ) ) {
				$disk_keep = $row['file'];
				break;
			}
		}
		if ( '' !== $disk_keep ) {
			$keep = $disk_keep;
		}

		$changed  = false;
		$next     = array();
		$lite_kept = false;

		foreach ( $active as $plugin ) {
			if ( ! self::is_lite_basename( $plugin ) ) {
				$next[] = $plugin;
				continue;
			}

			$readable = is_readable( WP_PLUGIN_DIR . '/' . $plugin );
			if ( ! $readable ) {
				$changed = true;
				continue;
			}

			if ( '' !== $keep && $plugin !== $keep ) {
				$changed = true;
				continue;
			}

			if ( $lite_kept ) {
				$changed = true;
				continue;
			}

			$lite_kept = true;
			$next[]    = $plugin;
		}

		if ( '' !== $keep && ! $lite_kept && is_readable( WP_PLUGIN_DIR . '/' . $keep ) ) {
			$next[]    = $keep;
			$lite_kept = true;
			$changed   = true;
		}

		if ( ! $changed ) {
			return false;
		}

		if ( $persist ) {
			update_option( 'active_plugins', array_values( $next ) );
			if ( function_exists( 'wp_clean_plugins_cache' ) ) {
				wp_clean_plugins_cache( true );
			}
			update_option( 'choir_rehearsal_active_repaired', time(), false );
		}

		return true;
	}

	public static function needs_active_plugins_repair(): bool {
		$lite_active = self::find_active_lite_basenames();
		if ( count( $lite_active ) > 1 ) {
			return true;
		}

		foreach ( $lite_active as $plugin ) {
			if ( ! is_readable( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				return true;
			}
		}

		if ( self::is_duplicate_bootstrap() ) {
			return count( self::find_lite_installs() ) <= 1;
		}

		return false;
	}

	public static function maybe_repair_notice(): void {
		if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$repaired = (int) get_option( 'choir_rehearsal_active_repaired', 0 );
		if ( $repaired > 0 && ( time() - $repaired ) < 120 ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Choir Rehearsal: duplicate Lite entries were removed from active plugins. Reload this page if Pro or features still look inactive.', 'compath-choir-rehearsal' );
			echo '</p></div>';
		}
	}

	public static function suggested_keep_folder( array $installs = array() ): string {
		if ( empty( $installs ) ) {
			$installs = self::find_lite_installs();
		}

		$keep = self::suggested_keep_file( $installs );
		if ( '' === $keep ) {
			return '';
		}

		return dirname( $keep );
	}

	public static function suggested_keep_file( array $installs ): string {
		foreach ( $installs as $row ) {
			if ( 'choir-rehearsal' === $row['folder'] ) {
				return $row['file'];
			}
		}

		if ( defined( 'CHOIR_REHEARSAL_FILE' ) ) {
			$current = plugin_basename( CHOIR_REHEARSAL_FILE );
			foreach ( $installs as $row ) {
				if ( $row['file'] === $current ) {
					return $current;
				}
			}
		}

		$best      = '';
		$best_ver  = '0';
		foreach ( $installs as $row ) {
			$ver = $row['version'] !== '' ? $row['version'] : '0';
			if ( '' === $best || version_compare( $ver, $best_ver, '>' ) ) {
				$best     = $row['file'];
				$best_ver = $ver;
			}
		}

		return $best;
	}

	private static function is_duplicate_bootstrap(): bool {
		return '' !== self::$bootstrap_file;
	}

	private static function require_cap(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage plugins.', 'compath-choir-rehearsal' ) );
		}
	}

	public static function render_wizard(): void {
		self::require_cap();

		$installs = self::find_lite_installs();
		$keep     = (string) get_option( self::OPTION_KEEP, '' );
		if ( '' === $keep || ! self::install_exists( $installs, $keep ) ) {
			$keep = self::suggested_keep_file( $installs );
		}

		$status = isset( $_GET['choir_mig'] ) ? sanitize_key( (string) wp_unslash( $_GET['choir_mig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error  = isset( $_GET['choir_mig_error'] ) ? rawurldecode( sanitize_text_field( wp_unslash( (string) $_GET['choir_mig_error'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Fix duplicate Choir Rehearsal install', 'compath-choir-rehearsal' ) . '</h1>';

		if ( '' !== $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		if ( 'done' === $status ) {
			echo '<div class="notice notice-success"><p>';
			esc_html_e( 'Migration finished. Only one Lite folder should remain. You can activate Pro now if needed.', 'compath-choir-rehearsal' );
			echo '</p></div>';
		}

		if ( 'repaired' === $status ) {
			echo '<div class="notice notice-success"><p>';
			esc_html_e( 'active_plugins repaired. Reload this page (F5) so Lite and Pro load cleanly.', 'compath-choir-rehearsal' );
			echo '</p></div>';
		}

		echo '<p class="description">';
		esc_html_e( 'After 0.4.39 the package folder was renamed for WordPress.org. Uploading a newer zip beside an existing choir-rehearsal/ folder creates a second copy. This wizard keeps one folder and removes the extras. Song data in the database is not deleted.', 'compath-choir-rehearsal' );
		echo '</p>';

		$lite_active = self::find_active_lite_basenames();
		if ( count( $installs ) < 2 && ! self::needs_active_plugins_repair() ) {
			echo '<div class="notice notice-success"><p>';
			esc_html_e( 'Only one Lite install was found on disk. No cleanup needed.', 'compath-choir-rehearsal' );
			echo '</p></div></div>';
			return;
		}

		if ( count( $installs ) < 2 && self::needs_active_plugins_repair() ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'Only one Lite folder exists on disk, but WordPress still lists more than one Lite plugin as active (or a missing path). This breaks Pro and updates. Use the repair button below, then reload wp-admin.', 'compath-choir-rehearsal' );
			echo '</p></div>';
		}

		echo '<h2>' . esc_html__( 'Step 1 — Installs found', 'compath-choir-rehearsal' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr>';
		echo '<th>' . esc_html__( 'Folder', 'compath-choir-rehearsal' ) . '</th>';
		echo '<th>' . esc_html__( 'Version', 'compath-choir-rehearsal' ) . '</th>';
		echo '<th>' . esc_html__( 'Plugin name', 'compath-choir-rehearsal' ) . '</th>';
		echo '<th>' . esc_html__( 'On disk', 'compath-choir-rehearsal' ) . '</th>';
		echo '<th>' . esc_html__( 'Active', 'compath-choir-rehearsal' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $installs as $row ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $row['folder'] ) . '</code></td>';
			echo '<td>' . esc_html( $row['version'] ) . '</td>';
			echo '<td>' . esc_html( $row['name'] ) . '</td>';
			echo '<td>' . ( $row['on_disk'] ? esc_html__( 'Yes', 'compath-choir-rehearsal' ) : esc_html__( 'Missing', 'compath-choir-rehearsal' ) ) . '</td>';
			echo '<td>' . ( $row['active'] ? esc_html__( 'Yes', 'compath-choir-rehearsal' ) : esc_html__( 'No', 'compath-choir-rehearsal' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( ! empty( $lite_active ) ) {
			echo '<h3>' . esc_html__( 'Lite rows in active_plugins', 'compath-choir-rehearsal' ) . '</h3>';
			echo '<ul style="list-style:disc;margin-left:1.5em">';
			foreach ( $lite_active as $plugin ) {
				$missing = ! is_readable( WP_PLUGIN_DIR . '/' . $plugin );
				echo '<li><code>' . esc_html( $plugin ) . '</code>';
				if ( $missing ) {
					echo ' — <strong>' . esc_html__( 'file missing', 'compath-choir-rehearsal' ) . '</strong>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '<h2>' . esc_html__( 'Repair active_plugins', 'compath-choir-rehearsal' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Keeps choir-rehearsal/ (or the only folder on disk), removes ghost or duplicate Lite entries. Does not delete songs.', 'compath-choir-rehearsal' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="choir_rehearsal_migration_repair_active" />';
		wp_nonce_field( 'choir_rehearsal_migration_repair_active' );
		submit_button( __( 'Repair active plugins list', 'compath-choir-rehearsal' ), 'primary', 'submit', false );
		echo '</form>';

		if ( count( $installs ) < 2 ) {
			echo '</div>';
			return;
		}

		echo '<h2>' . esc_html__( 'Step 2 — Choose folder to keep', 'compath-choir-rehearsal' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="choir_rehearsal_migration_set_keep" />';
		wp_nonce_field( 'choir_rehearsal_migration_set_keep' );
		foreach ( $installs as $row ) {
			echo '<p><label><input type="radio" name="keep_file" value="' . esc_attr( $row['file'] ) . '" ' . checked( $keep, $row['file'], false ) . ' /> ';
			echo '<code>' . esc_html( $row['folder'] ) . '</code> — ' . esc_html( $row['version'] );
			if ( 'choir-rehearsal' === $row['folder'] ) {
				echo ' <em>(' . esc_html__( 'recommended for existing sites', 'compath-choir-rehearsal' ) . ')</em>';
			}
			echo '</label></p>';
		}
		submit_button( __( 'Save choice', 'compath-choir-rehearsal' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Step 3 — Deactivate other copies', 'compath-choir-rehearsal' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Turns off every Lite copy except the one you chose. Does not delete files.', 'compath-choir-rehearsal' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="choir_rehearsal_migration_deactivate_others" />';
		wp_nonce_field( 'choir_rehearsal_migration_deactivate_others' );
		submit_button( __( 'Deactivate other Lite plugins', 'compath-choir-rehearsal' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Step 4 — Delete other folders', 'compath-choir-rehearsal' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Deletes extra plugin folders from disk only. Does not run uninstall and does not delete songs.', 'compath-choir-rehearsal' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return window.confirm(\'' . esc_js( __( 'Delete every Lite folder except the one you chose? Songs in the database will NOT be deleted.', 'compath-choir-rehearsal' ) ) . '\');">';
		echo '<input type="hidden" name="action" value="choir_rehearsal_migration_delete_others" />';
		wp_nonce_field( 'choir_rehearsal_migration_delete_others' );
		submit_button( __( 'Delete other folders', 'compath-choir-rehearsal' ), 'delete', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Step 5 — Activate kept copy', 'compath-choir-rehearsal' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="choir_rehearsal_migration_activate_keep" />';
		wp_nonce_field( 'choir_rehearsal_migration_activate_keep' );
		submit_button( __( 'Activate kept Lite plugin', 'compath-choir-rehearsal' ), 'primary', 'submit', false );
		echo '</form>';

		echo '</div>';
	}

	/**
	 * @param list<array{file: string}> $installs
	 */
	private static function install_exists( array $installs, string $file ): bool {
		foreach ( $installs as $row ) {
			if ( $row['file'] === $file ) {
				return true;
			}
		}
		return false;
	}

	public static function handle_set_keep(): void {
		self::require_cap();
		check_admin_referer( 'choir_rehearsal_migration_set_keep' );

		$keep     = isset( $_POST['keep_file'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['keep_file'] ) ) : '';
		$installs = self::find_lite_installs();
		if ( ! self::install_exists( $installs, $keep ) ) {
			self::redirect( array( 'choir_mig_error' => rawurlencode( __( 'Invalid keep folder.', 'compath-choir-rehearsal' ) ) ) );
		}

		update_option( self::OPTION_KEEP, $keep, false );
		self::redirect( array( 'choir_mig' => 'keep_saved' ) );
	}

	public static function handle_deactivate_others(): void {
		self::require_cap();
		check_admin_referer( 'choir_rehearsal_migration_deactivate_others' );

		$installs = self::find_lite_installs();
		$keep     = (string) get_option( self::OPTION_KEEP, self::suggested_keep_file( $installs ) );
		if ( ! self::install_exists( $installs, $keep ) ) {
			self::redirect( array( 'choir_mig_error' => rawurlencode( __( 'Choose which folder to keep first.', 'compath-choir-rehearsal' ) ) ) );
		}

		$active = (array) get_option( 'active_plugins', array() );
		$next   = array();
		foreach ( $active as $plugin ) {
			$plugin = (string) $plugin;
			if ( self::is_lite_basename( $plugin ) && $plugin !== $keep ) {
				continue;
			}
			$next[] = $plugin;
		}
		update_option( 'active_plugins', array_values( $next ) );

		self::redirect( array( 'choir_mig' => 'deactivated' ) );
	}

	public static function handle_delete_others(): void {
		self::require_cap();
		check_admin_referer( 'choir_rehearsal_migration_delete_others' );

		$installs = self::find_lite_installs();
		$keep     = (string) get_option( self::OPTION_KEEP, self::suggested_keep_file( $installs ) );
		if ( ! self::install_exists( $installs, $keep ) ) {
			self::redirect( array( 'choir_mig_error' => rawurlencode( __( 'Choose which folder to keep first.', 'compath-choir-rehearsal' ) ) ) );
		}

		// Ensure extras are not active before delete.
		$active = (array) get_option( 'active_plugins', array() );
		$next   = array();
		foreach ( $active as $plugin ) {
			$plugin  = (string) $plugin;
			if ( self::is_lite_basename( $plugin ) && $plugin !== $keep ) {
				continue;
			}
			$next[] = $plugin;
		}
		update_option( 'active_plugins', array_values( $next ) );

		$errors = array();
		foreach ( $installs as $row ) {
			if ( $row['file'] === $keep ) {
				continue;
			}
			$result = self::delete_plugin_folder( $row['dir'] );
			if ( is_wp_error( $result ) ) {
				$errors[] = $row['folder'] . ': ' . $result->get_error_message();
			}
		}

		if ( ! empty( $errors ) ) {
			self::redirect(
				array(
					'choir_mig_error' => rawurlencode(
						__( 'Could not delete some folders (use FTP). ', 'compath-choir-rehearsal' ) . implode( ' | ', $errors )
					),
				)
			);
		}

		self::redirect( array( 'choir_mig' => 'deleted' ) );
	}

	public static function handle_repair_active(): void {
		self::require_cap();
		check_admin_referer( 'choir_rehearsal_migration_repair_active' );

		self::repair_active_plugins( true );
		self::redirect( array( 'choir_mig' => 'repaired' ) );
	}

	public static function handle_activate_keep(): void {
		self::require_cap();
		check_admin_referer( 'choir_rehearsal_migration_activate_keep' );

		$installs = self::find_lite_installs();
		$keep     = (string) get_option( self::OPTION_KEEP, self::suggested_keep_file( $installs ) );
		if ( ! self::install_exists( $installs, $keep ) ) {
			self::redirect( array( 'choir_mig_error' => rawurlencode( __( 'Keep folder is missing. Re-upload Lite into choir-rehearsal/.', 'compath-choir-rehearsal' ) ) ) );
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Deactivate other lites first.
		foreach ( $installs as $row ) {
			if ( $row['file'] !== $keep && $row['active'] ) {
				deactivate_plugins( $row['file'], true );
			}
		}

		$result = activate_plugin( $keep, '', false, true );
		if ( is_wp_error( $result ) ) {
			self::redirect( array( 'choir_mig_error' => rawurlencode( $result->get_error_message() ) ) );
		}

		delete_option( self::OPTION_KEEP );
		self::redirect( array( 'choir_mig' => 'done' ) );
	}

	/**
	 * Delete a plugin directory without running uninstall.php.
	 *
	 * @return true|\WP_Error
	 */
	public static function delete_plugin_folder( string $dir ) {
		$dir = wp_normalize_path( $dir );
		$root = wp_normalize_path( WP_PLUGIN_DIR );

		if ( '' === $dir || ! str_starts_with( $dir, $root . '/' ) || $dir === $root ) {
			return new WP_Error( 'bad_dir', __( 'Refusing to delete that path.', 'compath-choir-rehearsal' ) );
		}

		if ( ! is_dir( $dir ) ) {
			return true;
		}

		// Never call uninstall.php — songs would be wiped.
		$uninstall = $dir . '/uninstall.php';
		if ( is_readable( $uninstall ) ) {
			// Rename aside so a later WP delete_plugins() cannot find it during this request.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@rename( $uninstall, $dir . '/uninstall.php.bak-migration' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return self::recursive_rmdir( $dir );
		}

		$deleted = $wp_filesystem->rmdir( $dir, true );
		if ( ! $deleted ) {
			return self::recursive_rmdir( $dir );
		}

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function recursive_rmdir( string $dir ) {
		$dir = rtrim( $dir, '/\\' );
		if ( ! is_dir( $dir ) ) {
			return true;
		}

		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'scan', __( 'Could not read plugin folder.', 'compath-choir-rehearsal' ) );
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				$result = self::recursive_rmdir( $path );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				if ( ! @unlink( $path ) ) {
					return new WP_Error( 'unlink', sprintf( /* translators: %s: file path */ __( 'Could not delete %s', 'compath-choir-rehearsal' ), $path ) );
				}
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		if ( ! @rmdir( $dir ) ) {
			return new WP_Error( 'rmdir', sprintf( /* translators: %s: directory path */ __( 'Could not remove %s — delete via FTP.', 'compath-choir-rehearsal' ), $dir ) );
		}

		return true;
	}

	/**
	 * @param array<string, string> $args
	 */
	private static function redirect( array $args ): void {
		wp_safe_redirect( self::wizard_url( $args ) );
		exit;
	}
}
