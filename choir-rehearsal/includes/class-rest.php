<?php
/**
 * REST API routes for songs and tracks.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_REST {

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'choir-rehearsal/v1',
			'/songs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'list_songs' ),
					'permission_callback' => array( self::class, 'can_list' ),
				),
			)
		);

		register_rest_route(
			'choir-rehearsal/v1',
			'/songs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_song' ),
					'permission_callback' => array( self::class, 'can_read_song' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => static fn( $value ) => is_numeric( $value ),
						),
					),
				),
			)
		);
	}

	public static function can_list(): bool {
		return true;
	}

	public static function can_read_song( WP_REST_Request $request ): bool {
		return Choir_Rehearsal_Access::can_view_song( (int) $request['id'] );
	}

	public static function list_songs(): WP_REST_Response {
		$args = array(
			'post_type'      => Choir_Rehearsal_Post_Types::SONG,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		if ( Choir_Rehearsal_Access::requires_login() && ! is_user_logged_in() ) {
			$args['meta_query'] = Choir_Rehearsal_Post_Types::public_songs_meta_query();
		}

		$songs = get_posts( $args );

		$data = array_map(
			static fn( WP_Post $song ) => self::format_song_summary( $song ),
			$songs
		);

		return new WP_REST_Response( $data, 200 );
	}

	public static function get_song( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$song = get_post( (int) $request['id'] );
		if ( ! $song instanceof WP_Post || Choir_Rehearsal_Post_Types::SONG !== $song->post_type ) {
			return new WP_Error( 'not_found', __( 'Song not found.', 'choir-rehearsal' ), array( 'status' => 404 ) );
		}

		if ( ! Choir_Rehearsal_Access::can_view_song( (int) $song->ID ) ) {
			return new WP_Error( 'forbidden', __( 'You must sign in to view this song.', 'choir-rehearsal' ), array( 'status' => 401 ) );
		}

		return new WP_REST_Response( self::format_song_detail( $song ), 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format_song_summary( WP_Post $song ): array {
		return array(
			'id'          => (int) $song->ID,
			'title'       => get_the_title( $song ),
			'url'         => get_permalink( $song ),
			'track_count' => count( Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID ) ),
			'has_score'   => Choir_Rehearsal_Post_Types::get_score_pdf_id( (int) $song->ID ) > 0,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format_song_detail( WP_Post $song ): array {
		$tracks = array();
		foreach ( Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID ) as $track ) {
			$tracks[] = array(
				'id'         => (int) $track->ID,
				'voice'      => (string) get_post_meta( $track->ID, '_choir_voice_slug', true ),
				'voice_label'=> Choir_Rehearsal_Post_Types::get_voice_label( (int) $track->ID ),
				'audio_url'  => Choir_Rehearsal_Post_Types::get_audio_url( (int) $track->ID ),
			);
		}

		return array(
			'id'       => (int) $song->ID,
			'title'    => get_the_title( $song ),
			'notes'    => apply_filters( 'the_content', $song->post_content ),
			'url'      => get_permalink( $song ),
			'score_pdf_url' => Choir_Rehearsal_Post_Types::get_score_pdf_url( (int) $song->ID ),
			'tracks'   => $tracks,
		);
	}
}
