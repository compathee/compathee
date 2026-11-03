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

	/**
	 * Demo sandbox cap. The drop-in is not shipped in GitHub or WordPress.org zips.
	 */
	public const DEMO_MAX_TRACKS = 10;

	public static function is_pro(): bool {
		// Pro may be installed but not licensed. Always ask the filter.
		// Default true when CHOIR_REHEARSAL_PRO is defined (add-on present, no
		// license filter). Pro's SureCart filter overrides that default.
		$default = defined( 'CHOIR_REHEARSAL_PRO' ) && CHOIR_REHEARSAL_PRO;

		return (bool) apply_filters( 'choir_rehearsal_is_pro', $default );
	}

	/**
	 * Demo build (distribution-demo.php). No SureCart license.
	 */
	public static function is_demo(): bool {
		return defined( 'CHOIR_REHEARSAL_DISTRIBUTION' ) && 'demo' === (string) CHOIR_REHEARSAL_DISTRIBUTION;
	}

	private static function is_full_edition(): bool {
		return self::is_pro() || self::is_demo();
	}

	public static function shows_commercial_upgrade(): bool {
		return ! self::is_full_edition();
	}

	/**
	 * Maximum voice tracks per song. Zero means unlimited.
	 */
	public static function max_tracks(): int {
		if ( self::is_demo() ) {
			return self::DEMO_MAX_TRACKS;
		}

		return self::is_full_edition() ? 0 : self::LITE_MAX_TRACKS;
	}

	/**
	 * Song-page multi-track mixer. Licensed Pro or the Demo distribution only.
	 */
	public static function can_use_mixer(): bool {
		return self::is_full_edition();
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

	/**
	 * Song notes and per-track recommendations.
	 * Licensed Pro, or the Demo distribution. Off for GitHub Lite without a license.
	 */
	public static function can_use_song_notes(): bool {
		return self::is_pro() || self::is_demo();
	}

	public static function upgrade_url(): string {
		return (string) apply_filters( 'choir_rehearsal_upgrade_url', 'https://shop.compath.ee/products/choir-rehearsal-pro/' );
	}

	public static function edition_label(): string {
		if ( self::is_demo() ) {
			return __( 'Demo', 'compath-choir-rehearsal' );
		}

		return self::is_pro()
			? __( 'Pro', 'compath-choir-rehearsal' )
			: __( 'Lite', 'compath-choir-rehearsal' );
	}
}
