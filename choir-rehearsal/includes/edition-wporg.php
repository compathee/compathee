<?php
/**
 * WordPress.org edition helpers — full feature set, no commercial gates.
 *
 * The multi-track mixer is not part of this package. The build deletes
 * includes/class-mixer.php and the mixer scripts so no gated mixer code ships.
 *
 * Copied over class-edition.php in the WordPress.org build (see build-wporg-zip.sh).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Edition {

	public static function is_pro(): bool {
		return false;
	}

	public static function is_demo(): bool {
		return false;
	}

	public static function can_use_mixer(): bool {
		return false;
	}

	public static function max_tracks(): int {
		return 0;
	}

	public static function can_record(): bool {
		return true;
	}

	public static function can_play_in_editor(): bool {
		return true;
	}

	public static function can_view_score_in_editor(): bool {
		return true;
	}

	public static function can_search_songs(): bool {
		return true;
	}

	public static function can_show_pdf_badge(): bool {
		return true;
	}

	public static function shows_commercial_upgrade(): bool {
		return false;
	}

	public static function upgrade_url(): string {
		return '';
	}

	public static function edition_label(): string {
		return __( 'Standard', 'compath-choir-rehearsal' );
	}
}
