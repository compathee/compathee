<?php
/**
 * Distribution channel helpers (GitHub vs WordPress.org).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Distribution {

	public const CHANNEL_GITHUB = 'github';
	public const CHANNEL_WPORG  = 'wporg';

	public static function channel(): string {
		if ( defined( 'CHOIR_REHEARSAL_DISTRIBUTION' ) ) {
			$channel = (string) CHOIR_REHEARSAL_DISTRIBUTION;
			if ( self::CHANNEL_WPORG === $channel || self::CHANNEL_GITHUB === $channel ) {
				return $channel;
			}
		}

		return self::CHANNEL_GITHUB;
	}

	public static function is_wporg(): bool {
		return self::CHANNEL_WPORG === self::channel();
	}

	/**
	 * Third-party (GitHub) self-updates are not allowed for WordPress.org packages.
	 */
	public static function uses_github_updater(): bool {
		return ! self::is_wporg();
	}

	public static function pdfjs_script_url(): string {
		return CHOIR_REHEARSAL_URL . 'assets/vendor/pdfjs/pdf.min.js';
	}

	public static function pdfjs_worker_url(): string {
		return CHOIR_REHEARSAL_URL . 'assets/vendor/pdfjs/pdf.worker.min.js';
	}
}
