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
	}

	public static function register_post_types(): void {
		register_post_type(
			self::SONG,
			array(
				'labels'              => array(
					'name'               => __( 'Songs', 'choir-rehearsal' ),
					'singular_name'      => __( 'Song', 'choir-rehearsal' ),
					'add_new'            => __( 'Add Song', 'choir-rehearsal' ),
					'add_new_item'       => __( 'Add New Song', 'choir-rehearsal' ),
					'edit_item'          => __( 'Edit Song', 'choir-rehearsal' ),
					'new_item'           => __( 'New Song', 'choir-rehearsal' ),
					'view_item'          => __( 'View Song', 'choir-rehearsal' ),
					'search_items'       => __( 'Search Songs', 'choir-rehearsal' ),
					'not_found'          => __( 'No songs found.', 'choir-rehearsal' ),
					'not_found_in_trash' => __( 'No songs found in Trash.', 'choir-rehearsal' ),
					'menu_name'          => __( 'Choir Rehearsal', 'choir-rehearsal' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-format-audio',
				'menu_position'       => 26,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => array(
					'slug'       => 'rehearsal',
					'with_front' => false,
				),
				'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			)
		);

		register_post_type(
			self::TRACK,
			array(
				'labels'              => array(
					'name'          => __( 'Tracks', 'choir-rehearsal' ),
					'singular_name' => __( 'Track', 'choir-rehearsal' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => true,
				'hierarchical'        => true,
				'supports'            => array( 'title', 'author', 'page-attributes' ),
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			)
		);
	}

	public static function register_meta(): void {
		register_post_meta(
			self::TRACK,
			'_choir_audio_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
				'sanitize_callback' => 'absint',
			)
		);

		register_post_meta(
			self::TRACK,
			'_choir_voice_slug',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
				'sanitize_callback' => 'sanitize_key',
			)
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
		return is_string( $url ) ? $url : '';
	}

	public static function get_voice_label( int $track_id ): string {
		$slug = (string) get_post_meta( $track_id, '_choir_voice_slug', true );
		if ( '' === $slug ) {
			return __( 'Other', 'choir-rehearsal' );
		}

		return Choir_Rehearsal_Voice_Types::get_label( $slug );
	}
}
