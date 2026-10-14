<?php

declare(strict_types=1);

namespace Compath\Hub;

final class StripeWebhookHandler {

	private Config $config;
	private EventStore $store;
	private InvoiceService $invoices;

	public function __construct( Config $config, EventStore $store ) {
		$this->config   = $config;
		$this->store    = $store;
		$this->invoices = new InvoiceService( $config );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function handle( string $payload, string $signatureHeader ): array {
		$this->verifySignature( $payload, $signatureHeader );

		/** @var array<string, mixed>|null $event */
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			throw new \InvalidArgumentException( 'Invalid JSON payload.' );
		}

		$eventId   = (string) ( $event['id'] ?? '' );
		$eventType = (string) ( $event['type'] ?? '' );

		if ( '' === $eventId || '' === $eventType ) {
			throw new \InvalidArgumentException( 'Missing Stripe event id or type.' );
		}

		if ( $this->store->has( $eventId ) ) {
			return array(
				'ok'      => true,
				'status'  => 'duplicate',
				'eventId' => $eventId,
			);
		}

		$invoiceId = null;

		if ( in_array( $eventType, array( 'invoice.paid', 'checkout.session.completed' ), true ) ) {
			$order = $this->mapOrder( $eventType, $event );
			if ( null !== $order ) {
				$invoiceId = $this->invoices->createPaidInvoice( $order );
			}
		}

		$this->store->mark( $eventId, $eventType, $payload );

		return array(
			'ok'              => true,
			'status'          => 'processed',
			'eventId'         => $eventId,
			'eventType'       => $eventType,
			'accountingRef'   => $invoiceId,
		);
	}

	private function verifySignature( string $payload, string $signatureHeader ): void {
		$secret = $this->config->getString( 'stripe_webhook_secret' );
		if ( '' === $secret ) {
			throw new \RuntimeException( 'stripe_webhook_secret is not configured.' );
		}

		$timestamp = null;
		$signatures = array();

		foreach ( explode( ',', $signatureHeader ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = $pair[1];
			}
			if ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( null === $timestamp || array() === $signatures ) {
			throw new \InvalidArgumentException( 'Invalid Stripe-Signature header.' );
		}

		if ( abs( time() - (int) $timestamp ) > 300 ) {
			throw new \InvalidArgumentException( 'Stripe webhook timestamp is too old.' );
		}

		$signedPayload = $timestamp . '.' . $payload;
		$expected      = hash_hmac( 'sha256', $signedPayload, $secret );

		$valid = false;
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			throw new \InvalidArgumentException( 'Stripe signature verification failed.' );
		}
	}

	/**
	 * @param array<string, mixed> $event
	 * @return array<string, mixed>|null
	 */
	private function mapOrder( string $eventType, array $event ): ?array {
		/** @var array<string, mixed> $object */
		$object = is_array( $event['data']['object'] ?? null ) ? $event['data']['object'] : array();

		if ( 'invoice.paid' === $eventType ) {
			return $this->mapFromInvoice( $object );
		}

		if ( 'checkout.session.completed' === $eventType ) {
			return $this->mapFromCheckoutSession( $object );
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $invoice
	 * @return array<string, mixed>|null
	 */
	private function mapFromInvoice( array $invoice ): ?array {
		$status = (string) ( $invoice['status'] ?? '' );
		if ( 'paid' !== $status ) {
			return null;
		}

		$amountPaid = (int) ( $invoice['amount_paid'] ?? 0 );
		if ( $amountPaid <= 0 ) {
			return null;
		}

		$productName = $this->resolveProductName( $invoice );

		return array(
			'partner_document_id' => (string) ( $invoice['id'] ?? '' ),
			'customer_id'         => (string) ( $invoice['customer'] ?? '' ),
			'customer_email'      => (string) ( $invoice['customer_email'] ?? '' ),
			'customer_name'       => (string) ( $invoice['customer_name'] ?? $invoice['customer_email'] ?? 'Customer' ),
			'amount_paid'         => round( $amountPaid / 100, 2 ),
			'currency'            => strtoupper( (string) ( $invoice['currency'] ?? 'eur' ) ),
			'product_name'        => $productName,
		);
	}

	/**
	 * @param array<string, mixed> $session
	 * @return array<string, mixed>|null
	 */
	private function mapFromCheckoutSession( array $session ): ?array {
		$paymentStatus = (string) ( $session['payment_status'] ?? '' );
		if ( 'paid' !== $paymentStatus ) {
			return null;
		}

		$amountTotal = (int) ( $session['amount_total'] ?? 0 );
		if ( $amountTotal <= 0 ) {
			return null;
		}

		$metadata = is_array( $session['metadata'] ?? null ) ? $session['metadata'] : array();

		$details = is_array( $session['customer_details'] ?? null ) ? $session['customer_details'] : array();

		return array(
			'partner_document_id' => (string) ( $session['id'] ?? '' ),
			'customer_id'         => (string) ( $session['customer'] ?? '' ),
			'customer_email'      => (string) ( $details['email'] ?? $session['customer_email'] ?? '' ),
			'customer_name'       => (string) ( $details['name'] ?? 'Customer' ),
			'amount_paid'         => round( $amountTotal / 100, 2 ),
			'currency'            => strtoupper( (string) ( $session['currency'] ?? 'eur' ) ),
			'product_name'        => (string) ( $metadata['product_name'] ?? 'Choir Rehearsal Pro' ),
		);
	}

	/**
	 * @param array<string, mixed> $invoice
	 */
	private function resolveProductName( array $invoice ): string {
		$lines = is_array( $invoice['lines']['data'] ?? null ) ? $invoice['lines']['data'] : array();
		if ( isset( $lines[0] ) && is_array( $lines[0] ) ) {
			$description = (string) ( $lines[0]['description'] ?? '' );
			if ( '' !== $description ) {
				return $description;
			}
		}

		$mapping = $this->config->getArray( 'stripe_price_labels' );
		$priceId = '';
		if ( isset( $lines[0] ) && is_array( $lines[0] ) ) {
			$priceId = (string) ( $lines[0]['price']['id'] ?? '' );
		}

		if ( '' !== $priceId && isset( $mapping[ $priceId ] ) && is_string( $mapping[ $priceId ] ) ) {
			return $mapping[ $priceId ];
		}

		return 'Digital service';
	}
}
