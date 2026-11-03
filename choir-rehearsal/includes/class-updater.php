<?php
/**
 * WordPress-native plugin update integration.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Updater {

	private const LAST_CHECK_OPTION = 'choir_rehearsal_last_update_check';

	/**
	 * Installed plugin basename (folder may be choir-rehearsal or compath-choir-rehearsal).
	 */
	private static function plugin_basename_slug(): string {
		return plugin_basename( CHOIR_REHEARSAL_FILE );
	}

	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'inject_update' ) );
		add_filter( 'plugins_api', array( self::class, 'plugin_info' ), 10, 3 );
		add_action( 'admin_post_choir_rehearsal_check_updates', array( self::class, 'handle_check_updates' ) );
		add_action( 'admin_notices', array( self::class, 'render_check_notices' ) );
		add_action( 'upgrader_process_complete', array( self::class, 'after_upgrade' ), 10, 2 );
	}

	public static function register_settings(): void {
		register_setting(
			'choir_rehearsal_settings',
			'choir_rehearsal_update_json_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			'choir_rehearsal_settings',
			'choir_rehearsal_github_repo',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_github_repo' ),
				'default'           => 'compathee/compathee',
			)
		);
	}

	public static function sanitize_github_repo( string $value ): string {
		$value = trim( $value );
		if ( preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $value ) ) {
			return $value;
		}

		return 'compathee/compathee';
	}

	public static function handle_check_updates(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to update plugins.', 'compath-choir-rehearsal' ) );
		}

		check_admin_referer( 'choir_rehearsal_check_updates' );

		self::clear_update_caches();

		$remote          = self::fetch_remote_metadata();
		$installed       = self::get_installed_version();
		$remote_version  = is_array( $remote ) && isset( $remote['version'] ) ? (string) $remote['version'] : null;
		$lite            = self::summarize_version_check( $installed, $remote_version );
		$lite['id']       = 'lite';
		$lite['name']     = 'Compath Choir Rehearsal';
		$lite['basename'] = self::plugin_basename_slug();

		// Rebuild after both caches are cleared so Plugins shows Lite and Pro together.
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		$plugins = apply_filters(
			'choir_rehearsal_update_check_plugins',
			array( self::sanitize_plugin_check_row( $lite ) )
		);
		$clean = array();
		if ( is_array( $plugins ) ) {
			foreach ( $plugins as $row ) {
				if ( is_array( $row ) ) {
					$clean[] = self::sanitize_plugin_check_row( $row );
				}
			}
		}

		update_option(
			self::LAST_CHECK_OPTION,
			array(
				'time'           => time(),
				'status'         => (string) $lite['status'],
				'remote_version' => (string) ( $lite['available'] ?? '' ),
				'installed'      => (string) ( $lite['installed'] ?? '' ),
				'plugins'        => $clean,
			),
			false
		);

		$redirect = add_query_arg(
			array(
				'post_type'                    => Choir_Rehearsal_Post_Types::SONG,
				'page'                         => 'choir-rehearsal-settings',
				'choir_rehearsal_update_check' => 'done',
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render_check_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = isset( $_GET['choir_rehearsal_update_check'] )
			? sanitize_key( (string) wp_unslash( $_GET['choir_rehearsal_update_check'] ) )
			: '';

		if ( '' === $status ) {
			return;
		}

		$last    = self::get_last_check_result();
		$plugins = isset( $last['plugins'] ) && is_array( $last['plugins'] ) ? $last['plugins'] : array();
		if ( array() !== $plugins ) {
			self::render_plugin_check_notice( $plugins );
			return;
		}

		$version = isset( $_GET['choir_rehearsal_update_version'] )
			? sanitize_text_field( (string) wp_unslash( $_GET['choir_rehearsal_update_version'] ) )
			: '';

		if ( 'available' === $status ) {
			$message = '' !== $version
				? sprintf(
					/* translators: %s: new plugin version */
					__( 'Update available: Compath Choir Rehearsal %s. Use Update now below or on the Plugins screen.', 'compath-choir-rehearsal' ),
					$version
				)
				: __( 'An update is available for Compath Choir Rehearsal. Use Update now on the Plugins screen.', 'compath-choir-rehearsal' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			return;
		}

		if ( 'up_to_date' === $status ) {
			$last      = self::get_last_check_result();
			$installed = isset( $last['installed'] ) ? (string) $last['installed'] : '';

			if ( '' !== $installed && '' !== $version && version_compare( $installed, $version, '>' ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				printf(
					/* translators: 1: installed version, 2: version reported by update server */
					esc_html__( 'Your installed version is %1$s, but the update server only reported %2$s (likely stale metadata). Open Plugins and use Update now if shown, upload the latest zip, or set Update JSON URL to the latest GitHub release update.json.', 'compath-choir-rehearsal' ),
					esc_html( $installed ),
					esc_html( $version )
				);
				echo ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '"><strong>' . esc_html__( 'Open Plugins', 'compath-choir-rehearsal' ) . '</strong></a>';
				echo '</p></div>';
				return;
			}

			$display = '' !== $installed ? $installed : $version;
			$message = '' !== $display
				? sprintf(
					/* translators: %s: installed plugin version */
					__( 'Compath Choir Rehearsal is up to date (version %s).', 'compath-choir-rehearsal' ),
					$display
				)
				: __( 'Compath Choir Rehearsal is up to date.', 'compath-choir-rehearsal' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			return;
		}

		if ( 'failed' === $status ) {
			echo '<div class="notice notice-error is-dismissible"><p>' .
				esc_html__( 'Could not reach the update server. Check your connection or try again later. If this keeps happening, verify GitHub Releases are reachable from this site.', 'compath-choir-rehearsal' ) .
				'</p></div>';
		}
	}

	/**
	 * Compare an installed version with one reported by an update server.
	 *
	 * @return array{status:string,installed:string,available:string}
	 */
	public static function summarize_version_check( string $installed, ?string $remote ): array {
		$installed  = self::sanitize_version_string( $installed );
		$remote_raw = is_string( $remote ) ? self::sanitize_version_string( $remote ) : '';
		if ( '' === $remote_raw ) {
			return array(
				'status'    => 'failed',
				'installed' => $installed,
				'available' => '',
			);
		}

		if ( '' !== $installed && version_compare( $installed, $remote_raw, '>' ) ) {
			$status = 'ahead';
		} elseif ( '' !== $installed && version_compare( $installed, $remote_raw, '<' ) ) {
			$status = 'available';
		} else {
			$status = 'up_to_date';
			if ( '' === $installed ) {
				$installed = $remote_raw;
			}
		}

		return array(
			'status'    => $status,
			'installed' => $installed,
			'available' => $remote_raw,
		);
	}

	public static function sanitize_version_string( string $version ): string {
		$version = ltrim( trim( $version ), 'vV' );
		if ( 1 === preg_match( '/^(\d+(?:\.\d+){1,3})/', $version, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Keep only display fields. License keys, activation ids, and download URLs are dropped.
	 *
	 * @param array<string, mixed> $row
	 * @return array{id:string,name:string,status:string,installed:string,available:string,basename:string}
	 */
	public static function sanitize_plugin_check_row( array $row ): array {
		$status  = isset( $row['status'] ) ? (string) $row['status'] : 'failed';
		$allowed = array( 'available', 'up_to_date', 'failed', 'unlicensed', 'ahead' );
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'failed';
		}

		$name = isset( $row['name'] ) ? (string) $row['name'] : '';
		$name = trim( (string) preg_replace( '/[^A-Za-z0-9 .:_-]/', '', $name ) );
		if ( strlen( $name ) > 80 ) {
			$name = substr( $name, 0, 80 );
		}

		$id = isset( $row['id'] ) ? (string) $row['id'] : 'plugin';
		if ( 1 !== preg_match( '/^[a-z0-9_-]{1,40}$/', $id ) ) {
			$id = 'plugin';
		}

		$basename = isset( $row['basename'] ) ? (string) $row['basename'] : '';
		if ( 1 !== preg_match( '#^[a-z0-9_-]+/[a-z0-9_-]+\.php$#', $basename ) ) {
			$basename = '';
		}

		return array(
			'id'        => $id,
			'name'      => $name,
			'status'    => $status,
			'installed' => self::sanitize_version_string( isset( $row['installed'] ) ? (string) $row['installed'] : '' ),
			'available' => self::sanitize_version_string( isset( $row['available'] ) ? (string) $row['available'] : '' ),
			'basename'  => $basename,
		);
	}

	/**
	 * @param list<array<string, mixed>> $plugins
	 */
	public static function echo_plugin_check_lines( array $plugins ): void {
		$first = true;
		foreach ( $plugins as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! $first ) {
				echo '<br />';
			}
			$first = false;
			self::render_plugin_check_line( self::sanitize_plugin_check_row( $row ) );
		}
	}

	/**
	 * @param list<array<string, mixed>> $plugins
	 */
	private static function render_plugin_check_notice( array $plugins ): void {
		echo '<div class="notice notice-info is-dismissible"><p>';
		self::echo_plugin_check_lines( $plugins );
		echo '</p></div>';
	}

	/**
	 * @param array{id:string,name:string,status:string,installed:string,available:string,basename:string} $row
	 */
	private static function render_plugin_check_line( array $row ): void {
		$name      = (string) $row['name'];
		$installed = (string) $row['installed'];
		$available = (string) $row['available'];
		$status    = (string) $row['status'];
		$shown     = '' !== $installed ? $installed : '—';

		if ( 'unlicensed' === $status ) {
			echo esc_html(
				sprintf(
					/* translators: %s: installed Pro version */
					__( 'Compath Choir Rehearsal Pro is installed (version %s), but its license is not activated. Pro updates are not checked until you activate the license. The Lite check still runs.', 'compath-choir-rehearsal' ),
					$shown
				)
			);
			$license_url = admin_url( 'edit.php?post_type=' . Choir_Rehearsal_Post_Types::SONG . '&page=choir-rehearsal-pro-license' );
			echo ' <a href="' . esc_url( $license_url ) . '">' . esc_html__( 'Pro License', 'compath-choir-rehearsal' ) . '</a>';
			return;
		}

		if ( 'failed' === $status ) {
			echo esc_html(
				sprintf(
					/* translators: 1: plugin name, 2: installed version */
					__( '%1$s: installed %2$s. Could not check for updates.', 'compath-choir-rehearsal' ),
					$name,
					$shown
				)
			);
			return;
		}

		if ( 'ahead' === $status ) {
			echo esc_html(
				sprintf(
					/* translators: 1: installed version, 2: version reported by update server */
					__( 'Your installed version is %1$s, but the update server only reported %2$s (likely stale metadata). Open Plugins and use Update now if shown, upload the latest zip, or set Update JSON URL to the latest GitHub release update.json.', 'compath-choir-rehearsal' ),
					$shown,
					'' !== $available ? $available : '—'
				)
			);
			return;
		}

		echo esc_html(
			sprintf(
				/* translators: 1: plugin name, 2: installed version, 3: available version */
				__( '%1$s: installed %2$s, available %3$s.', 'compath-choir-rehearsal' ),
				$name,
				$shown,
				'' !== $available ? $available : '—'
			)
		);

		if ( 'available' === $status && '' !== (string) $row['basename'] ) {
			$update_url = wp_nonce_url(
				self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( (string) $row['basename'] ) ),
				'upgrade-plugin_' . (string) $row['basename']
			);
			echo ' <a href="' . esc_url( $update_url ) . '"><strong>' . esc_html__( 'Update', 'compath-choir-rehearsal' ) . '</strong></a>';
			return;
		}

		if ( 'up_to_date' === $status ) {
			echo ' ' . esc_html__( 'Up to date.', 'compath-choir-rehearsal' );
		}
	}

	/**
	 * @return array{time?:int,status?:string,remote_version?:string,installed?:string,plugins?:list<array<string, string>>}
	 */
	public static function get_last_check_result(): array {
		$stored = get_option( self::LAST_CHECK_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function get_check_updates_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=choir_rehearsal_check_updates' ),
			'choir_rehearsal_check_updates'
		);
	}

	/**
	 * @param WP_Upgrader $upgrader
	 * @param array<string, mixed> $options
	 */
	public static function after_upgrade( $upgrader, array $options ): void {
		if ( ( $options['type'] ?? '' ) !== 'plugin' ) {
			return;
		}

		$updated = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$updated = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
			$updated = array( $options['plugin'] );
		}

		if ( ! in_array( self::plugin_basename_slug(), $updated, true ) ) {
			return;
		}

		self::clear_update_caches();

		$transient = get_site_transient( 'update_plugins' );
		if ( is_object( $transient ) ) {
			if ( isset( $transient->response[ self::plugin_basename_slug() ] ) ) {
				unset( $transient->response[ self::plugin_basename_slug() ] );
			}
			$installed = self::get_installed_version();
			if ( '' !== $installed ) {
				$transient->no_update[ self::plugin_basename_slug() ] = (object) array(
					'slug'        => 'choir-rehearsal',
					'plugin'      => self::plugin_basename_slug(),
					'new_version' => $installed,
					'url'         => 'https://rehearsal.compath.ee',
				);
			}
			set_site_transient( 'update_plugins', $transient );
		}
	}

	private static function clear_update_caches(): void {
		delete_transient( 'choir_rehearsal_update_metadata' );
		delete_site_transient( 'update_plugins' );

		/**
		 * Pro deletes the SureCart release transient here, before wp_update_plugins() runs.
		 */
		do_action( 'choir_rehearsal_clear_update_caches' );

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}
	}

	/**
	 * Installed version from disk (not the in-memory constant — that stays old mid-upgrade).
	 */
	private static function get_installed_version(): string {
		$plugin_file = WP_PLUGIN_DIR . '/' . self::plugin_basename_slug();
		if ( ! is_readable( $plugin_file ) ) {
			return defined( 'CHOIR_REHEARSAL_VERSION' ) ? (string) CHOIR_REHEARSAL_VERSION : '';
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( $plugin_file, false, false );
		$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';

		return '' !== $version ? $version : ( defined( 'CHOIR_REHEARSAL_VERSION' ) ? (string) CHOIR_REHEARSAL_VERSION : '' );
	}

	/**
	 * @param mixed $transient
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		// WordPress briefly sets an empty transient; do not inject then or updates reappear after install.
		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		$current = isset( $transient->checked[ self::plugin_basename_slug() ] )
			? (string) $transient->checked[ self::plugin_basename_slug() ]
			: self::get_installed_version();

		if ( '' === $current ) {
			return $transient;
		}

		$remote = self::fetch_remote_metadata();
		if ( null === $remote ) {
			return $transient;
		}

		if ( version_compare( $current, $remote['version'], '>=' ) ) {
			unset( $transient->response[ self::plugin_basename_slug() ] );
			$transient->no_update[ self::plugin_basename_slug() ] = (object) array(
				'slug'        => 'choir-rehearsal',
				'plugin'      => self::plugin_basename_slug(),
				'new_version' => $current,
				'url'         => $remote['homepage'],
			);
			return $transient;
		}

		$transient->response[ self::plugin_basename_slug() ] = (object) array(
			'slug'         => 'choir-rehearsal',
			'plugin'       => self::plugin_basename_slug(),
			'new_version'  => $remote['version'],
			'url'          => $remote['homepage'],
			'package'      => $remote['download_url'],
			'icons'        => $remote['icons'],
			'banners'      => $remote['banners'],
			'requires'     => $remote['requires'],
			'tested'       => $remote['tested'],
			'requires_php' => $remote['requires_php'],
		);

		return $transient;
	}

	/**
	 * @param false|object|array<string, mixed> $result
	 * @param string                            $action
	 * @param object                            $args
	 * @return false|object|array<string, mixed>
	 */
	public static function plugin_info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'choir-rehearsal' !== $args->slug ) {
			return $result;
		}

		$remote = self::fetch_remote_metadata();
		if ( null === $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => $remote['name'],
			'slug'          => 'choir-rehearsal',
			'version'       => $remote['version'],
			'author'        => '<a href="https://compath.ee">Compath OÜ</a>',
			'homepage'      => $remote['homepage'],
			'download_link' => $remote['download_url'],
			'requires'      => $remote['requires'],
			'tested'        => $remote['tested'],
			'requires_php'  => $remote['requires_php'],
			'sections'      => $remote['sections'],
			'last_updated'  => $remote['last_updated'],
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function fetch_remote_metadata(): ?array {
		$cached = get_transient( 'choir_rehearsal_update_metadata' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$candidates = array();

		$json_url = trim( (string) get_option( 'choir_rehearsal_update_json_url', '' ) );
		if ( '' !== $json_url ) {
			$candidates[] = self::normalize_metadata( self::request_json( $json_url ) );
		}

		$candidates[] = self::fetch_from_github();
		$candidates[] = self::fetch_github_latest_release();

		foreach ( self::fallback_update_json_urls() as $fallback_url ) {
			$candidates[] = self::normalize_metadata( self::request_json( $fallback_url ) );
		}

		$normalized = self::pick_highest_version_metadata( $candidates );
		if ( null === $normalized ) {
			return null;
		}

		set_transient( 'choir_rehearsal_update_metadata', $normalized, 12 * HOUR_IN_SECONDS );
		return $normalized;
	}

	/**
	 * @param list<array<string, mixed>|null> $candidates
	 * @return array<string, mixed>|null
	 */
	private static function pick_highest_version_metadata( array $candidates ): ?array {
		$best      = null;
		$best_ver  = '';

		foreach ( $candidates as $item ) {
			if ( ! is_array( $item ) || empty( $item['version'] ) ) {
				continue;
			}

			$version = (string) $item['version'];
			if ( null === $best || version_compare( $version, $best_ver, '>' ) ) {
				$best     = $item;
				$best_ver = $version;
			}
		}

		return $best;
	}

	/**
	 * Durable fallbacks when the GitHub Releases API is unavailable.
	 * Prefer the latest release asset `update.json` (hosted with each Lite release).
	 *
	 * @return list<string>
	 */
	private static function fallback_update_json_urls(): array {
		$repo = (string) get_option( 'choir_rehearsal_github_repo', 'compathee/compathee' );
		if ( ! preg_match( '#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $repo, $matches ) ) {
			return array();
		}

		$owner = $matches[1];
		$name  = $matches[2];

		return array(
			sprintf(
				'https://github.com/%s/%s/releases/latest/download/update.json',
				$owner,
				$name
			),
			// Legacy path (only works if update.json exists on main).
			sprintf(
				'https://raw.githubusercontent.com/%s/%s/main/choir-rehearsal/update.json',
				$owner,
				$name
			),
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function fetch_from_github(): ?array {
		$repo = (string) get_option( 'choir_rehearsal_github_repo', 'compathee/compathee' );
		if ( ! preg_match( '#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $repo, $matches ) ) {
			return null;
		}

		$owner = $matches[1];
		$name  = $matches[2];

		// Prefer the full list: /releases/latest can point at an unrelated non-plugin release.
		return self::fetch_latest_choir_release_from_list( $owner, $name );
	}

	/**
	 * Single-release GitHub API fallback when the paginated list is blocked or rate-limited.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function fetch_github_latest_release(): ?array {
		$repo = (string) get_option( 'choir_rehearsal_github_repo', 'compathee/compathee' );
		if ( ! preg_match( '#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $repo, $matches ) ) {
			return null;
		}

		$owner = $matches[1];
		$name  = $matches[2];
		$url   = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			rawurlencode( $owner ),
			rawurlencode( $name )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Choir-Rehearsal-Updater',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! empty( $body['draft'] ) || ! empty( $body['prerelease'] ) ) {
			return null;
		}

		$tag = (string) ( $body['tag_name'] ?? '' );
		if ( str_contains( $tag, '-pro-v' ) || 1 !== preg_match( '/^(?:compath-)?choir-rehearsal-v\d/', $tag ) ) {
			return null;
		}

		return self::map_github_release( $body );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function fetch_latest_choir_release_from_list( string $owner, string $name ): ?array {
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases?per_page=100',
			rawurlencode( $owner ),
			rawurlencode( $name )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Choir-Rehearsal-Updater',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return null;
		}

		$releases = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $releases ) || ! array_is_list( $releases ) ) {
			return null;
		}

		$best_release = null;
		$best_version = '';

		foreach ( $releases as $release ) {
			if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
				continue;
			}

			$tag     = (string) ( $release['tag_name'] ?? '' );
			// Lite only: (compath-)choir-rehearsal-vX.Y.Z (exclude *-pro-v…).
			if ( str_contains( $tag, '-pro-v' ) ) {
				continue;
			}
			if ( 1 !== preg_match( '/^(?:compath-)?choir-rehearsal-v\d/', $tag ) ) {
				continue;
			}
			$version = self::parse_release_version( $tag );
			if ( '' === $version ) {
				continue;
			}

			if ( null === $best_release || version_compare( $version, $best_version, '>' ) ) {
				$best_release = $release;
				$best_version = $version;
			}
		}

		return is_array( $best_release ) ? self::map_github_release( $best_release ) : null;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|null
	 */
	private static function map_github_release( array $body ): ?array {
		$tag          = (string) ( $body['tag_name'] ?? '' );
		$version      = self::parse_release_version( $tag );
		$download_url = '';

		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}
				$asset_name = (string) ( $asset['name'] ?? '' );
				if ( in_array( $asset_name, array( 'compath-choir-rehearsal.zip', 'choir-rehearsal.zip' ), true ) ) {
					$download_url = (string) ( $asset['browser_download_url'] ?? '' );
					if ( 'compath-choir-rehearsal.zip' === $asset_name ) {
						break;
					}
					// Keep scanning so branded zip wins over legacy name.
				}
			}
		}

		if ( '' === $version || '' === $download_url ) {
			return null;
		}

		return array(
			'name'         => 'Compath Choir Rehearsal',
			'version'      => $version,
			'download_url' => $download_url,
			'homepage'     => 'https://rehearsal.compath.ee',
			'requires'     => '6.4',
			'tested'       => '6.9',
			'requires_php' => '8.0',
			'last_updated' => isset( $body['published_at'] ) ? substr( (string) $body['published_at'], 0, 10 ) : gmdate( 'Y-m-d' ),
			'sections'     => array(
				'description' => 'Private rehearsal library for choirs.',
				'changelog'   => (string) ( $body['body'] ?? '' ),
			),
			'icons'        => array(),
			'banners'      => array(),
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function request_json( string $url ): ?array {
		if ( '' === $url ) {
			return null;
		}

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'Choir-Rehearsal-Updater',
			),
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	private static function parse_release_version( string $tag_name ): string {
		if ( preg_match( '/(\d+\.\d+\.\d+)/', $tag_name, $matches ) ) {
			return $matches[1];
		}

		return ltrim( $tag_name, 'v' );
	}

	/**
	 * @param array<string, mixed>|null $data
	 * @return array<string, mixed>|null
	 */
	private static function normalize_metadata( ?array $data ): ?array {
		if ( null === $data ) {
			return null;
		}

		$version = (string) ( $data['version'] ?? '' );
		$package = (string) ( $data['download_url'] ?? $data['package'] ?? '' );

		if ( '' === $version || '' === $package ) {
			return null;
		}

		$sections = isset( $data['sections'] ) && is_array( $data['sections'] ) ? $data['sections'] : array();

		return array(
			'name'         => (string) ( $data['name'] ?? 'Compath Choir Rehearsal' ),
			'version'      => ltrim( $version, 'v' ),
			'download_url' => $package,
			'homepage'     => (string) ( $data['homepage'] ?? 'https://rehearsal.compath.ee' ),
			'requires'     => (string) ( $data['requires'] ?? '6.4' ),
			'tested'       => (string) ( $data['tested'] ?? '6.9' ),
			'requires_php' => (string) ( $data['requires_php'] ?? '8.0' ),
			'last_updated' => (string) ( $data['last_updated'] ?? gmdate( 'Y-m-d' ) ),
			'sections'     => array(
				'description' => (string) ( $sections['description'] ?? '' ),
				'changelog'   => (string) ( $sections['changelog'] ?? '' ),
			),
			'icons'        => isset( $data['icons'] ) && is_array( $data['icons'] ) ? $data['icons'] : array(),
			'banners'      => isset( $data['banners'] ) && is_array( $data['banners'] ) ? $data['banners'] : array(),
		);
	}
}
