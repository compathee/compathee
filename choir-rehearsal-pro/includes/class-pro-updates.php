<?php
/**
 * SureCart update cache and the Settings "Check for plugin updates" result for Pro.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Pro_Updates {

	public static function register(): void {
		add_action( 'choir_rehearsal_clear_update_caches', array( self::class, 'clear_update_caches' ) );
		add_filter( 'choir_rehearsal_update_check_plugins', array( self::class, 'append_check_result' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'flush_release_cache' ), 1 );
		add_action( 'upgrader_process_complete', array( self::class, 'after_upgrade' ), 10, 2 );
	}

	/**
	 * Same key the SureCart SDK uses for its 3-hour release cache.
	 */
	public static function version_cache_key( string $slug ): string {
		return 'surecart_' . md5( $slug ) . '_version_info';
	}

	public static function plugin_slug(): string {
		$parts = explode( '/', self::plugin_basename() );
		return isset( $parts[0] ) && '' !== $parts[0] ? $parts[0] : 'choir-rehearsal-pro';
	}

	public static function plugin_basename(): string {
		if ( defined( 'CHOIR_REHEARSAL_PRO_FILE' ) && function_exists( 'plugin_basename' ) ) {
			return plugin_basename( CHOIR_REHEARSAL_PRO_FILE );
		}

		return 'choir-rehearsal-pro/choir-rehearsal-pro.php';
	}

	public static function clear_update_caches(): void {
		delete_transient( self::version_cache_key( self::plugin_slug() ) );
	}

	/**
	 * WordPress rebuilds update_plugins with a populated checked list.
	 * Drop the SureCart cache first so that rebuild cannot rewrite the old release.
	 *
	 * @param mixed $transient Update transient being saved.
	 * @return mixed
	 */
	public static function flush_release_cache( $transient ) {
		if ( is_object( $transient ) && ! empty( $transient->checked ) && is_array( $transient->checked ) ) {
			self::clear_update_caches();
		}

		return $transient;
	}

	/**
	 * @return array{status:string,installed:string,available:string}
	 */
	public static function summarize_pro_check( bool $licensed, string $installed, ?string $remote, bool $reachable ): array {
		if ( ! $licensed ) {
			return array(
				'status'    => 'unlicensed',
				'installed' => Choir_Rehearsal_Updater::sanitize_version_string( $installed ),
				'available' => '',
			);
		}

		if ( ! $reachable ) {
			return array(
				'status'    => 'failed',
				'installed' => Choir_Rehearsal_Updater::sanitize_version_string( $installed ),
				'available' => '',
			);
		}

		return Choir_Rehearsal_Updater::summarize_version_check( $installed, $remote );
	}

	/**
	 * Version from SureCart release_json. A missing slug means the payload is not this product.
	 *
	 * @param mixed $release_json Object, array, or JSON string.
	 */
	public static function release_version( $release_json ): string {
		if ( is_string( $release_json ) ) {
			$decoded      = json_decode( $release_json );
			$release_json = is_object( $decoded ) ? $decoded : null;
		} elseif ( is_array( $release_json ) ) {
			$release_json = (object) $release_json;
		}

		if ( ! is_object( $release_json ) || ! isset( $release_json->slug ) || ! is_scalar( $release_json->slug ) || '' === (string) $release_json->slug ) {
			return '';
		}

		if ( ! isset( $release_json->version ) || ! is_scalar( $release_json->version ) ) {
			return '';
		}

		return Choir_Rehearsal_Updater::sanitize_version_string( (string) $release_json->version );
	}

	/**
	 * @param list<array<string, mixed>> $plugins
	 * @return list<array<string, mixed>>
	 */
	public static function append_check_result( array $plugins ): array {
		$installed = defined( 'CHOIR_REHEARSAL_PRO_VERSION' ) ? (string) CHOIR_REHEARSAL_PRO_VERSION : '';
		$basename  = self::plugin_basename();
		$licensed  = class_exists( 'Choir_Rehearsal_Pro_Licensing', false ) && Choir_Rehearsal_Pro_Licensing::is_licensed();

		if ( ! $licensed ) {
			$summary = self::summarize_pro_check( false, $installed, null, false );
		} else {
			$remote    = null;
			$reachable = false;
			$transient = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_plugins' ) : null;
			if ( is_object( $transient ) ) {
				if ( isset( $transient->checked[ $basename ] ) && is_scalar( $transient->checked[ $basename ] ) ) {
					$installed = (string) $transient->checked[ $basename ];
				}
				if ( isset( $transient->response[ $basename ]->new_version ) ) {
					$remote    = (string) $transient->response[ $basename ]->new_version;
					$reachable = true;
				} elseif ( isset( $transient->no_update[ $basename ]->new_version ) ) {
					$remote    = (string) $transient->no_update[ $basename ]->new_version;
					$reachable = true;
				}
			}

			if ( ! $reachable && class_exists( 'Choir_Rehearsal_Pro_Licensing', false ) ) {
				$remote    = Choir_Rehearsal_Pro_Licensing::current_release_version();
				$reachable = '' !== $remote;
			}

			$summary = self::summarize_pro_check( true, $installed, $reachable ? $remote : null, $reachable );
		}

		$summary['id']       = 'pro';
		$summary['name']     = 'Compath Choir Rehearsal Pro';
		$summary['basename'] = $basename;
		$plugins[]           = class_exists( 'Choir_Rehearsal_Updater', false )
			? Choir_Rehearsal_Updater::sanitize_plugin_check_row( $summary )
			: $summary;

		return $plugins;
	}

	/**
	 * @param mixed                $upgrader Upgrader instance.
	 * @param array<string, mixed> $options  Upgrade context.
	 */
	public static function after_upgrade( $upgrader, array $options ): void {
		unset( $upgrader );

		if ( ( $options['type'] ?? '' ) !== 'plugin' ) {
			return;
		}

		$updated = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$updated = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
			$updated = array( $options['plugin'] );
		}

		if ( ! in_array( self::plugin_basename(), $updated, true ) ) {
			return;
		}

		self::clear_update_caches();
	}
}
