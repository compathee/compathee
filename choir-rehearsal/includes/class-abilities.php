<?php
/**
 * WordPress Abilities API registration for MCP exposure.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Abilities {

	public static function register(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'choir-rehearsal/list-songs',
			array(
				'label'             => __( 'List choir songs', 'choir-rehearsal' ),
				'description'       => __( 'Returns published rehearsal songs with track counts.', 'choir-rehearsal' ),
				'category'          => 'site',
				'execute_callback'  => static function () {
					$response = Choir_Rehearsal_REST::list_songs();
					return $response->get_data();
				},
				'permission_callback' => static fn() => Choir_Rehearsal_REST::can_read(),
				'meta'              => array(
					'mcp' => array(
						'public' => true,
					),
				),
			)
		);

		wp_register_ability(
			'choir-rehearsal/get-song',
			array(
				'label'             => __( 'Get choir song details', 'choir-rehearsal' ),
				'description'       => __( 'Returns one song with all voice tracks and audio URLs.', 'choir-rehearsal' ),
				'category'          => 'site',
				'input_schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Song post ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'  => static function ( $input = null ) {
					$input = is_array( $input ) ? $input : array();
					$request = new WP_REST_Request( 'GET', '/choir-rehearsal/v1/songs/' . absint( $input['id'] ?? 0 ) );
					$request->set_param( 'id', absint( $input['id'] ?? 0 ) );
					$result = Choir_Rehearsal_REST::get_song( $request );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					return $result->get_data();
				},
				'permission_callback' => static fn() => Choir_Rehearsal_REST::can_read(),
				'meta'              => array(
					'mcp' => array(
						'public' => true,
					),
				),
			)
		);
	}
}
