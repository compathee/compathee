<?php
/**
 * Distribution channel helpers (GitHub vs WordPress.org vs Demo).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Distribution {

	public const CHANNEL_GITHUB = 'github';
	public const CHANNEL_WPORG  = 'wporg';
	public const CHANNEL_DEMO   = 'demo';

	public static function channel(): string {
		if ( defined( 'CHOIR_REHEARSAL_DISTRIBUTION' ) ) {
			$channel = (string) CHOIR_REHEARSAL_DISTRIBUTION;
			if ( in_array( $channel, array( self::CHANNEL_WPORG, self::CHANNEL_GITHUB, self::CHANNEL_DEMO ), true ) ) {
				return $channel;
			}
		}

		return self::CHANNEL_GITHUB;
	}

	public static function is_wporg(): bool {
		return self::CHANNEL_WPORG === self::channel();
	}

	public static function is_demo(): bool {
		return self::CHANNEL_DEMO === self::channel();
	}

	/**
	 * Third-party (GitHub) self-updates are not allowed for WordPress.org or Demo packages.
	 */
	public static function uses_github_updater(): bool {
		return self::CHANNEL_GITHUB === self::channel();
	}

	public static function pdfjs_script_url(): string {
		return CHOIR_REHEARSAL_URL . 'assets/vendor/pdfjs/pdf.min.js';
	}

	public static function pdfjs_worker_url(): string {
		return CHOIR_REHEARSAL_URL . 'assets/vendor/pdfjs/pdf.worker.min.js';
	}
}
