<?php
/**
 * Custom post types for songs and tracks.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Post_Types {

	public const SONG  = 'choir_song';
	public const TRACK = 'choir_track';

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_types' ) );
		add_action( 'init', array( self::class, 'register_meta' ) );
		// Attachment GUIDs/siteurl may stay http:// while admin is served over HTTPS.
		add_filter( 'wp_get_attachment_url', array( self::class, 'align_attachment_url_scheme' ) );
	}

	/**
	 * Force HTTPS attachment URLs on SSL requests so PDF.js / audio are not mixed-content blocked.
	 *
	 * @param string|false $url Attachment URL.
	 * @return string|false
	 */
	public static function align_attachment_url_scheme( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}

		if ( is_ssl() ) {
			return set_url_scheme( $url, 'https' );
		}

		return $url;
	}

	public static function register_post_types(): void {
		register_post_type(
			self::SONG,
			array(
				'labels'              => array(
					'name'               => __( 'Songs', 'compath-choir-rehearsal' ),
					'singular_name'      => __( 'Song', 'compath-choir-rehearsal' ),
					'add_new'            => __( 'Add Song', 'compath-choir-rehearsal' ),
					'add_new_item'       => __( 'Add New Song', 'compath-choir-rehearsal' ),
					'edit_item'          => __( 'Edit Song', 'compath-choir-rehearsal' ),
					'new_item'           => __( 'New Song', 'compath-choir-rehearsal' ),
					'view_item'          => __( 'View Song', 'compath-choir-rehearsal' ),
					'search_items'       => __( 'Search Songs', 'compath-choir-rehearsal' ),
					'not_found'          => __( 'No songs found.', 'compath-choir-rehearsal' ),
					'not_found_in_trash' => __( 'No songs found in Trash.', 'compath-choir-rehearsal' ),
					'menu_name'          => __( 'Choir Rehearsal', 'compath-choir-rehearsal' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-format-audio',
				'menu_position'       => 26,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => 'rehearsal',
					'with_front' => false,
				),
				'supports'            => array( 'title' ),
				'capability_type'     => array( 'choir_song', 'choir_songs' ),
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
			)
		);

		register_post_type(
			self::TRACK,
			array(
				'labels'              => array(
					'name'          => __( 'Tracks', 'compath-choir-rehearsal' ),
					'singular_name' => __( 'Track', 'compath-choir-rehearsal' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => true,
				'hierarchical'        => true,
				'supports'            => array( 'title', 'author', 'page-attributes' ),
				'capability_type'     => array( 'choir_song', 'choir_songs' ),
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
			)
		);
	}

	public static function register_meta(): void {
		$can_edit_meta = static fn() => current_user_can( 'edit_choir_songs' ) || current_user_can( 'manage_options' );

		register_post_meta(
			self::TRACK,
			'_choir_audio_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $can_edit_meta,
				'sanitize_callback' => 'absint',
			)
		);

		register_post_meta(
			self::SONG,
			'_choir_score_pdf_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $can_edit_meta,
				'sanitize_callback' => 'absint',
			)
		);

		register_post_meta(
			self::SONG,
			'_choir_is_public',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $can_edit_meta,
				'sanitize_callback' => static fn( $value ) => (bool) $value,
				'default'           => false,
			)
		);

		register_post_meta(
			self::TRACK,
			'_choir_voice_slug',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $can_edit_meta,
				'sanitize_callback' => 'sanitize_key',
			)
		);

		register_post_meta(
			self::TRACK,
			'_choir_source',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $can_edit_meta,
				'sanitize_callback' => array( self::class, 'sanitize_track_source' ),
				'default'           => 'audio',
			)
		);

		register_post_meta(
			self::TRACK,
			'_choir_youtube_url',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $can_edit_meta,
				'sanitize_callback' => array( self::class, 'sanitize_youtube_url' ),
				'default'           => '',
			)
		);
	}

	public static function is_public( int $song_id ): bool {
		if ( $song_id <= 0 ) {
			return false;
		}

		return (bool) get_post_meta( $song_id, '_choir_is_public', true );
	}

	public static function set_public( int $song_id, bool $is_public ): void {
		if ( $song_id <= 0 ) {
			return;
		}

		if ( $is_public ) {
			update_post_meta( $song_id, '_choir_is_public', 1 );
			return;
		}

		delete_post_meta( $song_id, '_choir_is_public' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function public_songs_meta_query(): array {
		return array(
			array(
				'key'     => '_choir_is_public',
				'value'   => '1',
				'compare' => '=',
			),
		);
	}

	/**
	 * @return WP_Post[]
	 */
	public static function get_tracks_for_song( int $song_id ): array {
		$tracks = get_posts(
			array(
				'post_type'      => self::TRACK,
				'post_parent'    => $song_id,
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		return is_array( $tracks ) ? $tracks : array();
	}

	public static function get_audio_url( int $track_id ): string {
		$attachment_id = (int) get_post_meta( $track_id, '_choir_audio_id', true );
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? self::align_attachment_url_scheme( $url ) : '';
	}

	public static function get_score_pdf_id( int $song_id ): int {
		return (int) get_post_meta( $song_id, '_choir_score_pdf_id', true );
	}

	public static function get_score_pdf_url( int $song_id ): string {
		$attachment_id = self::get_score_pdf_id( $song_id );
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( 'application/pdf' !== $mime ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? self::align_attachment_url_scheme( $url ) : '';
	}

	/**
	 * Stored watch/share URL for a YouTube track source.
	 */
	public static function get_track_youtube_url( int $track_id ): string {
		if ( $track_id <= 0 ) {
			return '';
		}

		return self::sanitize_youtube_url( (string) get_post_meta( $track_id, '_choir_youtube_url', true ) );
	}

	/**
	 * Track media source: audio (default) or youtube.
	 */
	public static function get_track_source( int $track_id ): string {
		if ( $track_id <= 0 ) {
			return 'audio';
		}

		return self::sanitize_track_source( (string) get_post_meta( $track_id, '_choir_source', true ) );
	}

	public static function sanitize_track_source( mixed $value ): string {
		$source = is_string( $value ) ? sanitize_key( $value ) : 'audio';
		return 'youtube' === $source ? 'youtube' : 'audio';
	}

	/**
	 * 11-character YouTube video id for a track, or empty.
	 */
	public static function get_track_youtube_video_id( int $track_id ): string {
		return self::parse_youtube_video_id( self::get_track_youtube_url( $track_id ) );
	}

	/**
	 * Official embed URL for a YouTube track (https://www.youtube.com/embed/…).
	 */
	public static function get_track_youtube_embed_url( int $track_id ): string {
		$id = self::get_track_youtube_video_id( $track_id );
		if ( '' === $id ) {
			return '';
		}

		return 'https://www.youtube.com/embed/' . rawurlencode( $id );
	}

	/**
	 * Whether the song has at least one playable YouTube track.
	 */
	public static function song_has_youtube_track( int $song_id ): bool {
		foreach ( self::get_tracks_for_song( $song_id ) as $track ) {
			$track_id = (int) $track->ID;
			if ( 'youtube' === self::get_track_source( $track_id ) && '' !== self::get_track_youtube_video_id( $track_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keep only recognisable YouTube watch/share URLs.
	 */
	public static function sanitize_youtube_url( mixed $value ): string {
		$url = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $url ) {
			return '';
		}

		$url = esc_url_raw( $url );
		if ( '' === $url || '' === self::parse_youtube_video_id( $url ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Extract a YouTube video id from common URL shapes.
	 */
	public static function parse_youtube_video_id( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = strtolower( (string) $parts['host'] );
		$host = preg_replace( '/^www\./', '', $host ) ?? $host;
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		if ( 'youtu.be' === $host ) {
			$segment = trim( $path, '/' );
			$segment = explode( '/', $segment )[0] ?? '';
			return self::is_youtube_video_id( $segment ) ? $segment : '';
		}

		if ( ! in_array( $host, array( 'youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com' ), true ) ) {
			return '';
		}

		if ( preg_match( '#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $matches ) ) {
			return $matches[1];
		}

		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
		}

		if ( isset( $query['v'] ) && is_string( $query['v'] ) && self::is_youtube_video_id( $query['v'] ) ) {
			return $query['v'];
		}

		return '';
	}

	private static function is_youtube_video_id( string $id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $id );
	}

	public static function get_voice_label( int $track_id ): string {
		$slug = (string) get_post_meta( $track_id, '_choir_voice_slug', true );
		if ( '' === $slug ) {
			return __( 'Other', 'compath-choir-rehearsal' );
		}

		return Choir_Rehearsal_Voice_Types::get_label( $slug );
	}
}
