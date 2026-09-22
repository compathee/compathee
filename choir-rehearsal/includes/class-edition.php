<?php
/**
 * GitHub distribution: Lite vs Pro vs Demo. WordPress.org builds replace this file at package time.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Edition {

	public const LITE_MAX_TRACKS = 4;

	/** Demo sandbox: tracks per song. */
	public const DEMO_MAX_TRACKS = 10;

	/** Demo sandbox: songs site-wide. */
	public const DEMO_MAX_SONGS = 50;

	/** Demo sandbox: max score PDF size (5 MB). */
	public const DEMO_MAX_PDF_BYTES = 5242880;

	public static function is_pro(): bool {
		if ( defined( 'CHOIR_REHEARSAL_PRO' ) && CHOIR_REHEARSAL_PRO ) {
			return true;
		}

		return (bool) apply_filters( 'choir_rehearsal_is_pro', false );
	}

	public static function is_demo(): bool {
		return Choir_Rehearsal_Distribution::is_demo();
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
	 * Maximum songs site-wide. Zero means unlimited.
	 */
	public static function max_songs(): int {
		return self::is_demo() ? self::DEMO_MAX_SONGS : 0;
	}

	/**
	 * Maximum score PDF upload size in bytes. Zero means no Demo-specific cap.
	 */
	public static function max_pdf_bytes(): int {
		return self::is_demo() ? self::DEMO_MAX_PDF_BYTES : 0;
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
		if ( self::is_demo() ) {
			return __( 'Demo', 'compath-choir-rehearsal' );
		}

		return self::is_pro()
			? __( 'Pro', 'compath-choir-rehearsal' )
			: __( 'Lite', 'compath-choir-rehearsal' );
	}
}
