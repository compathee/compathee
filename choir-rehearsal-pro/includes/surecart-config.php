<?php
/**
 * SureCart store credentials for Choir Rehearsal Pro.
 *
 * public_token: from SureCart → Settings → API (starts with pt_).
 * Safe to ship in the customer zip — it is a public token, not the secret key.
 *
 * Override without editing this file:
 *   define( 'CHOIR_REHEARSAL_PRO_PUBLIC_TOKEN', 'pt_…' ); in wp-config.php
 *   or environment variable SURECART_PUBLIC_TOKEN.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	/**
	 * SureCart Public Token (app.surecart.com → API → Public).
	 * Safe to ship in the customer zip — public token, not the secret Connection token.
	 */
	'public_token' => 'pt_UVGK3voHWKRPSVmVWmxKgZnX',
);
