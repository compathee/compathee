<?php
/**
 * WordPress-native plugin update integration.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Updater {

	private const PLUGIN_SLUG = 'choir-rehearsal/choir-rehearsal.php';

	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'inject_update' ) );
		add_filter( 'plugins_api', array( self::class, 'plugin_info' ), 10, 3 );
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

		register_setting(
			'choir_rehearsal_settings',
			'choir_rehearsal_license_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
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

	/**
	 * @param mixed $transient
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = self::fetch_remote_metadata();
		if ( null === $remote ) {
			return $transient;
		}

		if ( version_compare( CHOIR_REHEARSAL_VERSION, $remote['version'], '>=' ) ) {
			return $transient;
		}

		$transient->response[ self::PLUGIN_SLUG ] = (object) array(
			'slug'         => 'choir-rehearsal',
			'plugin'       => self::PLUGIN_SLUG,
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
			'author'        => '<a href="https://veneta.ee">Cappella Veneta</a>',
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

		$json_url = trim( (string) get_option( 'choir_rehearsal_update_json_url', '' ) );
		$data     = null;

		if ( '' !== $json_url ) {
			$data = self::request_json( $json_url );
		} else {
			$data = self::fetch_from_github();
		}

		$normalized = self::normalize_metadata( $data );
		if ( null === $normalized ) {
			return null;
		}

		set_transient( 'choir_rehearsal_update_metadata', $normalized, 12 * HOUR_IN_SECONDS );
		return $normalized;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function fetch_from_github(): ?array {
		$repo = (string) get_option( 'choir_rehearsal_github_repo', 'compathee/compathee' );
		$url  = 'https://api.github.com/repos/' . rawurlencode( $repo ) . '/releases/latest';

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

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return null;
		}

		$tag = isset( $body['tag_name'] ) ? ltrim( (string) $body['tag_name'], 'v' ) : '';
		$download_url = '';

		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}
				if ( ( $asset['name'] ?? '' ) === 'choir-rehearsal.zip' ) {
					$download_url = (string) ( $asset['browser_download_url'] ?? '' );
					break;
				}
			}
		}

		if ( '' === $download_url && isset( $body['zipball_url'] ) ) {
			$download_url = (string) $body['zipball_url'];
		}

		return array(
			'name'         => 'Choir Rehearsal',
			'version'      => $tag,
			'download_url' => $download_url,
			'homepage'     => 'https://veneta.ee',
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
		$license = trim( (string) get_option( 'choir_rehearsal_license_key', '' ) );

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( '' !== $license ) {
			$args['headers']['Authorization'] = 'Bearer ' . $license;
		}

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
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
			'name'         => (string) ( $data['name'] ?? 'Choir Rehearsal' ),
			'version'      => ltrim( $version, 'v' ),
			'download_url' => $package,
			'homepage'     => (string) ( $data['homepage'] ?? 'https://veneta.ee' ),
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
