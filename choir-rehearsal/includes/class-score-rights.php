<?php
/**
 * Score / public-publish rights confirmation helpers (local library U0).
 */

declare(strict_types=1);

/**
 * Pure attach/publish gates — safe to require outside WordPress bootstrap in unit tests.
 */
final class Choir_Rehearsal_Score_Rights {

	/**
	 * Whether a score PDF attachment change may be saved.
	 *
	 * Unchanged or removed PDFs do not require a fresh confirmation.
	 * Attaching a different non-zero PDF requires rights_confirmed=true.
	 */
	public static function may_attach_pdf( int $existing_pdf_id, int $new_pdf_id, bool $rights_confirmed ): bool {
		if ( $new_pdf_id <= 0 ) {
			return true;
		}
		if ( $new_pdf_id === $existing_pdf_id ) {
			return true;
		}

		return $rights_confirmed;
	}

	/**
	 * Whether a song may be saved as public (no-login access).
	 *
	 * Staying private/public or switching to private does not require confirmation.
	 * Switching private → public requires rights_confirmed=true.
	 */
	public static function may_publish_public( bool $was_public, bool $want_public, bool $rights_confirmed ): bool {
		if ( ! $want_public ) {
			return true;
		}
		if ( $was_public ) {
			return true;
		}

		return $rights_confirmed;
	}
}
