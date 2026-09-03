<?php
/**
 * Lite vs Pro edition helpers.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Edition {

	public const LITE_MAX_TRACKS = 4;

	public static function is_pro(): bool {
		if ( defined( 'CHOIR_REHEARSAL_PRO' ) && CHOIR_REHEARSAL_PRO ) {
			return true;
		}

		return (bool) apply_filters( 'choir_rehearsal_is_pro', false );
	}

	/**
	 * Maximum voice tracks per song. Zero means unlimited (Pro).
	 */
	public static function max_tracks(): int {
		return self::is_pro() ? 0 : self::LITE_MAX_TRACKS;
	}

	public static function can_record(): bool {
		return self::is_pro();
	}

	public static function upgrade_url(): string {
		return (string) apply_filters( 'choir_rehearsal_upgrade_url', 'https://shop.compath.ee/products/choir-rehearsal-pro/' );
	}

	public static function edition_label(): string {
		return self::is_pro()
			? __( 'Pro', 'choir-rehearsal' )
			: __( 'Lite', 'choir-rehearsal' );
	}
}
