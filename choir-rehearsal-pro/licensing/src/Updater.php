<?php

namespace SureCart\Licensing;

/**
 * This class will handle the updates.
 */
class Updater {
	/**
	 * SureCart\Licensing\Client.
	 *
	 * @var object
	 */
	protected $client;

	/**
	 * Holds the cache key for the version info.
	 *
	 * @var string
	 */
	private $cache_key; // Declared as private.

	/**
	 * Initialize the class.
	 *
	 * @param SureCart\Licensing\Client $client The client.
	 */
	public function __construct( Client $client ) {
		$this->client    = $client;
		$this->cache_key = 'surecart_' . md5( $this->client->slug ) . '_version_info';

		// Run hooks.
		if ( 'plugin' === $this->client->type ) {
			$this->run_plugin_hooks();
		} elseif ( 'theme' === $this->client->type ) {
			$this->run_theme_hooks();
		}
	}

	/**
	 * Set up WordPress filter to hooks to get update.
	 *
	 * @return void
	 */
	public function run_plugin_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
	}

	/**
	 * Set up WordPress filter to hooks to get update.
	 *
	 * @return void
	 */
	public function run_theme_hooks() {
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_theme_update' ) );
	}

	/**
	 * Check for Update for this specific project.
	 *
	 * @param Object $transient_data Transient data for update.
	 */
	public function check_plugin_update( $transient_data ) {
		global $pagenow;

		if ( ! is_object( $transient_data ) ) {
			$transient_data = new \stdClass();
		}

		if ( 'plugins.php' === $pagenow && is_multisite() ) {
			return $transient_data;
		}

		// WordPress sets an empty transient while clearing caches. Injecting then makes the update reappear after install.
		if ( empty( $transient_data->checked ) || ! is_array( $transient_data->checked ) ) {
			return $transient_data;
		}

		$version_info = $this->get_version_info();

		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {

			unset( $version_info->sections );

			// Installed folder wins over a release.json plugin path that does not match this site.
			$version_info->plugin = $this->client->basename;

			$installed = isset( $transient_data->checked[ $this->client->basename ] )
				? (string) $transient_data->checked[ $this->client->basename ]
				: (string) $this->client->project_version;

			// Replace a stale response or no_update entry with this release.
			if ( ! isset( $transient_data->response ) || ! is_array( $transient_data->response ) ) {
				$transient_data->response = array();
			}
			if ( ! isset( $transient_data->no_update ) || ! is_array( $transient_data->no_update ) ) {
				$transient_data->no_update = array();
			}
			if ( version_compare( $installed, (string) $version_info->new_version, '<' ) ) {
				unset( $transient_data->no_update[ $this->client->basename ] );
				$transient_data->response[ $this->client->basename ] = $version_info;
			} else {
				unset( $transient_data->response[ $this->client->basename ] );
				$transient_data->no_update[ $this->client->basename ] = $version_info;
			}

			$transient_data->last_checked                       = time();
			$transient_data->checked[ $this->client->basename ] = $installed;
		}

		return $transient_data;
	}

	/**
	 * Get version info from database
	 *
	 * @return Object or Boolean
	 */
	private function get_cached_version_info() {
		global $pagenow;
		// If updater page then force fetch.
		if ( 'update-core.php' === $pagenow ) {
			return false;
		}

		return get_transient( $this->cache_key );
	}

	/**
	 * Set version info to database.
	 *
	 * @param Object $value Version info to store in the transient.
	 */
	private function set_cached_version_info( $value ) {
		if ( ! $value ) {
			return;
		}
		// cache for 3 hours.
		set_transient( $this->cache_key, $value, 3 * HOUR_IN_SECONDS );
	}

	/**
	 * Get plugin info from SureCart\Licensing
	 */
	private function get_project_latest_version() {
		// The signed package URL must outlive WordPress' update_plugins transient (up to 12 hours).
		$current_release = $this->client->license()->get_current_release( 12 * HOUR_IN_SECONDS );

		if ( is_wp_error( $current_release ) || empty( $current_release ) ) {
			return false;
		}

		$release = isset( $current_release->release_json ) ? $current_release->release_json : null;
		if ( is_string( $release ) ) {
			$release = json_decode( $release );
		}

		// must have a slug.
		if ( ! is_object( $release ) || ! isset( $release->slug ) ) {
			return false;
		}

		// set the new version.
		$release->new_version = ltrim( (string) $release->version, 'vV' );

		if ( empty( $release->last_updated ) ) {
			$release->last_updated = date_i18n( get_option( 'date_format' ), isset( $current_release->updated_at ) ? $current_release->updated_at : time() );
		}

		if ( isset( $current_release->url ) ) {
			$release->package = $current_release->url;
		}

		if ( isset( $release->banners ) ) {
			$release->banners = (array) $release->banners;
		}

		if ( isset( $release->icons ) ) {
			$release->icons = (array) $release->icons;
		}

		// If there is asset path, then try to find the images from the asset path.
		if ( ! empty( $this->client->asset_path ) ) {
			if ( empty( $release->banners ) ) {
				$release->banners = $this->findImagesFromAssetPath( 'banner' );
			}

			if ( empty( $release->icons ) ) {
				$release->icons = $this->findImagesFromAssetPath( 'icon' );
			}
		}

		if ( isset( $release->sections ) ) {
			$release->sections = (array) $release->sections;
		}

		return $release;
	}

	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @param mixed  $data Plugin data.
	 * @param string $action The action type.
	 * @param object $args Arguments.
	 *
	 * @return object $data
	 */
	public function plugins_api_filter( $data, $action = '', $args = null ) {
		// must be requesting plugin info.
		if ( 'plugin_information' !== $action ) {
			return $data;
		}

		// slug must match.
		if ( ! isset( $args->slug ) || ( $args->slug !== $this->client->slug ) ) {
			return $data;
		}

		// get the version info.
		return $this->get_version_info();
	}

	/**
	 * Check theme update.
	 *
	 * @param Object $transient_data Transient data for the update.
	 */
	public function check_theme_update( $transient_data ) {
		global $pagenow;

		if ( ! is_object( $transient_data ) ) {
			$transient_data = new \stdClass();
		}

		if ( 'themes.php' === $pagenow && is_multisite() ) {
			return $transient_data;
		}

		if ( ! empty( $transient_data->response ) && ! empty( $transient_data->response[ $this->client->slug ] ) ) {
			return $transient_data;
		}

		$version_info = $this->get_version_info();

		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {

			// Ensure the 'theme' property is set.
			if ( ! isset( $version_info->theme ) ) {
				$version_info->theme = $this->client->slug;
			}

			// If new version available then set to `response`.
			if ( version_compare( $this->client->project_version, $version_info->new_version, '<' ) ) {
				$transient_data->response[ $this->client->slug ] = (array) $version_info;
			} else {
				// If new version is not available then set to `no_update`.
				$transient_data->no_update[ $this->client->slug ] = (array) $version_info;
			}

			$transient_data->last_checked                   = time();
			$transient_data->checked[ $this->client->slug ] = $this->client->project_version;
		}

		return $transient_data;
	}

	/**
	 * Get version information
	 */
	private function get_version_info() {
		$version_info = $this->get_cached_version_info();

		if ( false === $version_info ) {
			$version_info = $this->get_project_latest_version();
			$this->set_cached_version_info( $version_info );
		}

		return $this->normalize_version_info( $version_info );
	}

	/**
	 * Cached releases from older SDK copies stored icons and banners as objects.
	 *
	 * @param mixed $version_info Release payload.
	 * @return mixed
	 */
	private function normalize_version_info( $version_info ) {
		if ( ! is_object( $version_info ) ) {
			return $version_info;
		}

		if ( isset( $version_info->icons ) ) {
			$version_info->icons = (array) $version_info->icons;
		}

		if ( isset( $version_info->banners ) ) {
			$version_info->banners = (array) $version_info->banners;
		}

		if ( empty( $version_info->plugin ) ) {
			$version_info->plugin = $this->client->basename;
		}

		$file = isset( $this->client->file ) ? (string) $this->client->file : '';

		return self::apply_compatibility_fields( $version_info, $file );
	}

	/**
	 * SureCart release_json often has no WordPress "tested" field.
	 * The plugin details card then cannot show Compatible up to.
	 * Fill requires, tested, and requires_php from the plugin header and release.json.
	 * A remote tested value is kept when it is newer than this install.
	 *
	 * @param object $version_info Release payload.
	 * @param string $plugin_file  Main plugin file.
	 * @return object
	 */
	public static function apply_compatibility_fields( $version_info, $plugin_file ) {
		if ( ! is_object( $version_info ) ) {
			return $version_info;
		}

		$local = self::compatibility_from_install( (string) $plugin_file );
		if ( empty( $version_info->requires ) && '' !== $local['requires'] ) {
			$version_info->requires = $local['requires'];
		}
		if ( empty( $version_info->requires_php ) && '' !== $local['requires_php'] ) {
			$version_info->requires_php = $local['requires_php'];
		}

		$tested = isset( $version_info->tested ) ? trim( (string) $version_info->tested ) : '';
		if ( '' !== $local['tested'] && ( '' === $tested || version_compare( $local['tested'], $tested, '>' ) ) ) {
			$version_info->tested = $local['tested'];
		}

		return $version_info;
	}

	/**
	 * @param string $plugin_file Main plugin file.
	 * @return array{requires:string,tested:string,requires_php:string}
	 */
	public static function compatibility_from_install( $plugin_file ) {
		$compat = array(
			'requires'     => '',
			'tested'       => '',
			'requires_php' => '',
		);
		$plugin_file = (string) $plugin_file;
		if ( '' !== $plugin_file && is_readable( $plugin_file ) ) {
			self::read_compatibility_text( (string) file_get_contents( $plugin_file ), $compat );
		}

		$release = '' !== $plugin_file ? dirname( $plugin_file ) . '/release.json' : '';
		if ( '' !== $release && is_readable( $release ) ) {
			$json = json_decode( (string) file_get_contents( $release ), true );
			if ( is_array( $json ) ) {
				foreach ( array( 'requires', 'tested', 'requires_php' ) as $key ) {
					$value = isset( $json[ $key ] ) ? trim( (string) $json[ $key ] ) : '';
					if ( '' === $value ) {
						continue;
					}
					if ( 'tested' === $key && '' !== $compat['tested'] && version_compare( $value, $compat['tested'], '>' ) ) {
						$compat['tested'] = $value;
						continue;
					}
					if ( '' === $compat[ $key ] ) {
						$compat[ $key ] = $value;
					}
				}
			}
		}

		return $compat;
	}

	/**
	 * @param string               $text   Plugin header or readme text.
	 * @param array<string,string> $compat Compatibility values, updated in place.
	 */
	private static function read_compatibility_text( $text, array &$compat ) {
		$patterns = array(
			'requires'     => '/^\s*(?:\*\s*)?Requires at least:\s*(\d+\.\d+(?:\.\d+)?)/mi',
			'tested'       => '/^\s*(?:\*\s*)?Tested up to:\s*(\d+\.\d+(?:\.\d+)?)/mi',
			'requires_php' => '/^\s*(?:\*\s*)?Requires PHP:\s*(\d+\.\d+(?:\.\d+)?)/mi',
		);
		foreach ( $patterns as $key => $pattern ) {
			if ( preg_match( $pattern, $text, $match ) ) {
				$compat[ $key ] = $match[1];
			}
		}
	}

	/**
	 * Find images from the asset path.
	 *
	 * @param string $prefix The prefix of the image, eg: banner or icon.
	 * @return array         The images.
	 */
	public function findImagesFromAssetPath( $prefix ) {
		$images = array();

		if ( 'icon' === $prefix ) {
			$images['1x']  = esc_url_raw( $this->client->asset_path . '/icon-128x128.png' );
			$images['2x']  = esc_url_raw( $this->client->asset_path . '/icon-256x256.png' );
			$images['svg'] = esc_url_raw( $this->client->asset_path . '/icon.svg' );
		} elseif ( 'banner' === $prefix ) {
			$images['low']  = esc_url_raw( $this->client->asset_path . '/banner-772x250.png' );
			$images['high'] = esc_url_raw( $this->client->asset_path . '/banner-1544x500.png' );
		}

		return $images;
	}
}
