<?php
/**
 * Nightly Demo library reset (HTTP secret, WP-CLI, admin button).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Demo_Reset {

	public const QUERY_VAR = 'choir_demo_reset';

	private static bool $running = false;

	public static function register(): void {
		if ( ! Choir_Rehearsal_Distribution::is_demo() ) {
			return;
		}

		add_action( 'init', array( self::class, 'maybe_handle_http_reset' ), 1 );
		add_action( 'admin_post_choir_rehearsal_demo_reset_now', array( self::class, 'handle_admin_reset_now' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command( 'choir-rehearsal demo-reset', array( self::class, 'cli_reset' ) );
		}
	}

	public static function reset_key(): string {
		if ( defined( 'CHOIR_REHEARSAL_DEMO_RESET_KEY' ) ) {
			return (string) CHOIR_REHEARSAL_DEMO_RESET_KEY;
		}

		return (string) get_option( 'choir_rehearsal_demo_reset_key', '' );
	}

	/**
	 * @return array{ok:bool,message:string,deleted:array{songs:int,tracks:int,media:int},loaded:array{songs:int,tracks:int}}
	 */
	public static function run( string $source = 'manual' ): array {
		if ( self::$running ) {
			return array(
				'ok'      => false,
				'message' => __( 'Reset already in progress.', 'compath-choir-rehearsal' ),
				'deleted' => array( 'songs' => 0, 'tracks' => 0, 'media' => 0 ),
				'loaded'  => array( 'songs' => 0, 'tracks' => 0 ),
			);
		}

		self::$running = true;

		$baseline = self::restore_saved_baseline( $source );
		if ( null !== $baseline ) {
			self::$running = false;
			return $baseline;
		}

		$deleted = Choir_Rehearsal_Demo_Data::delete_all_songs();
		Choir_Rehearsal_Demo_Log::add(
			'reset_deleted',
			sprintf(
				/* translators: 1: songs, 2: tracks, 3: media */
				__( 'Deleted before reset — %1$d songs, %2$d tracks, %3$d media', 'compath-choir-rehearsal' ),
				(int) $deleted['songs'],
				(int) $deleted['tracks'],
				(int) $deleted['media']
			),
			array( 'source' => $source )
		);

		$loaded = Choir_Rehearsal_Demo_Data::load_demo_songs_with_score();
		if ( '' !== ( $loaded['error'] ?? '' ) ) {
			Choir_Rehearsal_Demo_Log::add(
				'reset_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Reset failed — %s', 'compath-choir-rehearsal' ),
					(string) $loaded['error']
				),
				array( 'source' => $source )
			);
			self::restore_demo_accounts();
			self::$running = false;

			return array(
				'ok'      => false,
				'message' => (string) $loaded['error'],
				'deleted' => $deleted,
				'loaded'  => array( 'songs' => 0, 'tracks' => 0 ),
			);
		}

		Choir_Rehearsal_Demo_Log::add(
			'reset_done',
			sprintf(
				/* translators: 1: songs, 2: tracks */
				__( 'Cron reset completed — default library installed (%1$d songs, %2$d tracks, PDF attached)', 'compath-choir-rehearsal' ),
				(int) $loaded['songs'],
				(int) $loaded['tracks']
			),
			array( 'source' => $source )
		);

		self::restore_demo_accounts();
		self::$running = false;

		return array(
			'ok'      => true,
			'message' => __( 'Default Demo library installed.', 'compath-choir-rehearsal' ),
			'deleted' => $deleted,
			'loaded'  => array(
				'songs'  => (int) $loaded['songs'],
				'tracks' => (int) $loaded['tracks'],
			),
		);
	}

	/**
	 * When a must-use baseline exists, restore that snapshot (content and demo accounts).
	 * Returns null when the guard or the baseline is not installed.
	 *
	 * @return array{ok:bool,message:string,deleted:array{songs:int,tracks:int,media:int},loaded:array{songs:int,tracks:int}}|null
	 */
	private static function restore_saved_baseline( string $source ): ?array {
		if ( ! class_exists( 'Compath_Rehearsal_Demo_Guard' ) || ! Compath_Rehearsal_Demo_Guard::baseline_is_ready() ) {
			return null;
		}

		$result = Compath_Rehearsal_Demo_Guard::restore_baseline( $source );
		Choir_Rehearsal_Demo_Log::add(
			! empty( $result['ok'] ) ? 'reset_done' : 'reset_failed',
			(string) ( $result['message'] ?? '' ),
			array(
				'source' => $source,
				'mode'   => 'baseline',
			)
		);

		return array(
			'ok'      => ! empty( $result['ok'] ),
			'message' => (string) ( $result['message'] ?? '' ),
			'deleted' => array(
				'songs'  => (int) ( $result['songs'] ?? 0 ),
				'tracks' => (int) ( $result['tracks'] ?? 0 ),
				'media'  => (int) ( $result['media'] ?? 0 ),
			),
			'loaded'  => array(
				'songs'  => (int) ( $result['songs'] ?? 0 ),
				'tracks' => (int) ( $result['tracks'] ?? 0 ),
			),
		);
	}

	/**
	 * Put the shared demo accounts back when the library was reseeded without a snapshot.
	 */
	private static function restore_demo_accounts(): void {
		if ( class_exists( 'Compath_Rehearsal_Demo_Guard' ) ) {
			Compath_Rehearsal_Demo_Guard::restore_accounts_from_config();
		}
	}

	public static function maybe_handle_http_reset(): void {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$expected = self::reset_key();

		if ( '' === $expected || ! hash_equals( $expected, $key ) ) {
			status_header( 403 );
			nocache_headers();
			wp_die( esc_html__( 'Invalid Demo reset key.', 'compath-choir-rehearsal' ), 403 );
		}

		$result = self::run( 'cron' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $result );
		exit;
	}

	public static function handle_admin_reset_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'compath-choir-rehearsal' ), 403 );
		}

		check_admin_referer( 'choir_rehearsal_demo_reset_now' );
		$result = self::run( 'admin' );

		$redirect = add_query_arg(
			array(
				'post_type'           => Choir_Rehearsal_Post_Types::SONG,
				'page'                => 'choir-rehearsal-demo-data',
				'choir_demo_reset'    => $result['ok'] ? '1' : '0',
				'choir_demo_msg'      => rawurlencode( $result['message'] ),
			),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @param array<int, string> $args
	 * @param array<string, string> $assoc_args
	 */
	public static function cli_reset( array $args = array(), array $assoc_args = array() ): void {
		$result = self::run( 'cli' );
		if ( $result['ok'] ) {
			\WP_CLI::success( $result['message'] );
			return;
		}
		\WP_CLI::error( $result['message'] );
	}

	public static function cron_curl_example(): string {
		$url = add_query_arg(
			array(
				self::QUERY_VAR => '1',
				'key'           => self::reset_key() !== '' ? self::reset_key() : 'YOUR_SECRET_KEY',
			),
			home_url( '/' )
		);

		return 'curl -fsS ' . escapeshellarg( $url );
	}
}
