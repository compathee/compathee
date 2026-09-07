<?php
/**
 * Voice part taxonomy and default terms.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Voice_Types {

	public const TAXONOMY = 'choir_voice_type';

	/**
	 * Default voice slugs => English labels (translatable on output).
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'backing'     => __( 'Backing track', 'choir-rehearsal' ),
			'bass-1'      => __( 'Bass 1', 'choir-rehearsal' ),
			'bass-2'      => __( 'Bass 2', 'choir-rehearsal' ),
			'baritone-1'  => __( 'Baritone 1', 'choir-rehearsal' ),
			'baritone-2'  => __( 'Baritone 2', 'choir-rehearsal' ),
			'tenor-1'     => __( 'Tenor 1', 'choir-rehearsal' ),
			'tenor-2'     => __( 'Tenor 2', 'choir-rehearsal' ),
			'alto-1'      => __( 'Alto 1', 'choir-rehearsal' ),
			'alto-2'      => __( 'Alto 2', 'choir-rehearsal' ),
			'soprano-1'   => __( 'Soprano 1', 'choir-rehearsal' ),
			'soprano-2'   => __( 'Soprano 2', 'choir-rehearsal' ),
			'other'       => __( 'Other', 'choir-rehearsal' ),
		);
	}

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_taxonomy' ) );
	}

	public static function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( Choir_Rehearsal_Post_Types::TRACK ),
			array(
				'labels'            => array(
					'name'          => __( 'Voice Types', 'choir-rehearsal' ),
					'singular_name' => __( 'Voice Type', 'choir-rehearsal' ),
				),
				'public'            => false,
				'show_ui'           => false,
				'show_in_rest'      => true,
				'hierarchical'      => false,
			)
		);
	}

	public static function seed_default_terms(): void {
		foreach ( self::defaults() as $slug => $label ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term( $label, self::TAXONOMY, array( 'slug' => $slug ) );
			}
		}
	}

	public static function get_label( string $slug ): string {
		$defaults = self::defaults();
		if ( isset( $defaults[ $slug ] ) ) {
			return $defaults[ $slug ];
		}

		$term = get_term_by( 'slug', $slug, self::TAXONOMY );
		if ( $term instanceof WP_Term ) {
			return $term->name;
		}

		return $slug;
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function choices(): array {
		return self::defaults();
	}
}
