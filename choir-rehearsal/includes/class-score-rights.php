<?php
/**
 * PDF score rights confirmation helpers (local library U0).
 */

declare(strict_types=1);

/**
 * Pure attach gate — safe to require outside WordPress bootstrap in unit tests.
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
}
