<?php
/**
 * Optional public appearance. Classic stays the default so existing sites do not change.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Appearance {

	public const OPTION    = 'choir_rehearsal_appearance';
	public const CLASSIC   = 'classic';
	public const NEW_LOOK  = 'new';

	public static function register(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	public static function register_settings(): void {
		register_setting(
			'choir_rehearsal_settings',
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::CLASSIC,
			)
		);
	}

	/**
	 * @param mixed $value Raw option value.
	 */
	public static function sanitize( $value ): string {
		return self::NEW_LOOK === $value ? self::NEW_LOOK : self::CLASSIC;
	}

	public static function current(): string {
		if ( ! function_exists( 'get_option' ) ) {
			return self::CLASSIC;
		}

		return self::sanitize( get_option( self::OPTION, self::CLASSIC ) );
	}

	public static function is_new(): bool {
		return self::NEW_LOOK === self::current();
	}

	/**
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::is_new() && Choir_Rehearsal_Frontend::is_public_choir_view() ) {
			$classes[] = 'choir-appearance-wave';
		}

		return $classes;
	}
}
