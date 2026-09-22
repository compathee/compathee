<?php
/**
 * GitHub distribution: Lite vs Pro. WordPress.org builds replace this file at package time.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Edition {

	public const LITE_MAX_TRACKS = 4;

	public static function is_pro(): bool {
		// Pro add-on may be installed but not licensed yet — always ask the filter.
		// Default true when CHOIR_REHEARSAL_PRO is defined (legacy unlock); Pro's
		// licensing filter overrides this when a SureCart public token is configured.
		$default = defined( 'CHOIR_REHEARSAL_PRO' ) && CHOIR_REHEARSAL_PRO;

		return (bool) apply_filters( 'choir_rehearsal_is_pro', $default );
	}

	private static function is_full_edition(): bool {
		return self::is_pro();
	}

	public static function shows_commercial_upgrade(): bool {
		return ! self::is_full_edition();
	}

	/**
	 * Maximum voice tracks per song. Zero means unlimited.
	 */
	public static function max_tracks(): int {
		return self::is_full_edition() ? 0 : self::LITE_MAX_TRACKS;
	}

	public static function can_record(): bool {
		return self::is_full_edition();
	}

	public static function can_play_in_editor(): bool {
		return self::is_full_edition();
	}

	/**
	 * Embedded PDF preview in the song editor (Lite and Pro).
	 */
	public static function can_view_score_in_editor(): bool {
		return true;
	}

	public static function can_search_songs(): bool {
		return self::is_full_edition();
	}

	public static function can_show_pdf_badge(): bool {
		return self::is_full_edition();
	}

	public static function upgrade_url(): string {
		return (string) apply_filters( 'choir_rehearsal_upgrade_url', 'https://shop.compath.ee/products/choir-rehearsal-pro/' );
	}

	public static function edition_label(): string {
		return self::is_pro()
			? __( 'Pro', 'compath-choir-rehearsal' )
			: __( 'Lite', 'compath-choir-rehearsal' );
	}
}
