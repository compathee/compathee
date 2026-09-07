<?php
/**
 * Compath Integration Hub — Stripe webhooks → ERPLY Books (Merit later).
 *
 * Upload this folder to api.compath.ee document root.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/src/bootstrap.php';

use Compath\Hub\Config;
use Compath\Hub\EventStore;
use Compath\Hub\StripeWebhookHandler;

$path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
$path = is_string( $path ) ? rtrim( $path, '/' ) : '';

if ( '' === $path || '/' === $path ) {
	$path = '/health';
}

try {
	$config = Config::load( dirname( __DIR__ ) . '/config/config.php' );
} catch ( Throwable $e ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode(
		array(
			'ok'      => false,
			'error'   => 'Configuration missing. Copy config/config.example.php to config/config.php',
			'details' => $e->getMessage(),
		)
	);
	exit;
}

if ( '/health' === $path ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 'ok compath-hub ' . gmdate( 'c' );
	exit;
}

if ( '/webhook/stripe' === $path ) {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		http_response_code( 405 );
		header( 'Allow: POST' );
		exit;
	}

	$payload = file_get_contents( 'php://input' );
	if ( ! is_string( $payload ) || '' === $payload ) {
		http_response_code( 400 );
		exit;
	}

	$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
	if ( ! is_string( $signature ) || '' === $signature ) {
		http_response_code( 400 );
		exit;
	}

	$store   = new EventStore( dirname( __DIR__ ) . '/data/events.sqlite' );
	$handler = new StripeWebhookHandler( $config, $store );

	try {
		$result = $handler->handle( $payload, $signature );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $result );
	} catch ( InvalidArgumentException $e ) {
		http_response_code( 400 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( array( 'ok' => false, 'error' => $e->getMessage() ) );
	} catch ( RuntimeException $e ) {
		http_response_code( 500 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( array( 'ok' => false, 'error' => $e->getMessage() ) );
	}
	exit;
}

http_response_code( 404 );
header( 'Content-Type: application/json; charset=utf-8' );
echo json_encode( array( 'ok' => false, 'error' => 'Not found' ) );
