<?php
/**
 * Copy to config/config.php and fill in secrets.
 * Do not commit config.php.
 */
return array(
	// erply | merit
	'accounting_provider' => 'erply',

	// Stripe → Developers → Webhooks → Signing secret (whsec_...)
	'stripe_webhook_secret' => '',

	// ERPLY Books → Settings → API → token
	'erply_api_token' => '',

	// Payment type name in ERPLY (create in ERPLY if needed)
	'erply_payment_type' => 'Stripe',

	// Estonian VAT % for B2C digital services (adjust with your accountant)
	'vat_percent' => 22.0,

	// Optional: map Stripe Price IDs to invoice line descriptions
	'stripe_price_labels' => array(
		// 'price_xxxxxxxx' => 'Choir Rehearsal Pro — annual subscription',
	),

	// Future Merit Aktiva credentials
	'merit_api_id'  => '',
	'merit_api_key' => '',
);
