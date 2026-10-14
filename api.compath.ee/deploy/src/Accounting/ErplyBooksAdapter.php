<?php

declare(strict_types=1);

namespace Compath\Hub\Accounting;

use Compath\Hub\Config;

final class ErplyBooksAdapter implements AccountingAdapterInterface {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function createPaidInvoice( array $order ): string {
		$token = $this->config->getString( 'erply_api_token' );
		if ( '' === $token ) {
			throw new \RuntimeException( 'ERPLY API token is not configured.' );
		}

		$partnerDocumentId = (string) ( $order['partner_document_id'] ?? '' );
		if ( '' === $partnerDocumentId ) {
			throw new \InvalidArgumentException( 'partner_document_id is required.' );
		}

		$vatPercent = $this->config->getFloat( 'vat_percent', 22.0 );
		$amountPaid = (float) ( $order['amount_paid'] ?? 0 );
		if ( $amountPaid <= 0 ) {
			throw new \InvalidArgumentException( 'amount_paid must be positive.' );
		}

		$netAmount = round( $amountPaid / ( 1 + ( $vatPercent / 100 ) ), 2 );
		$now       = gmdate( 'Y-m-d\TH:i:s' );

		$payload = array(
			'id'                       => 0,
			'typeCode'                 => 'DOCUMENT_SELL',
			'date'                     => $now,
			'transactionDate'          => $now,
			'currencyCode'             => strtoupper( (string) ( $order['currency'] ?? 'EUR' ) ) === 'EUR' ? 'CURRENCY_EUR' : 'CURRENCY_EUR',
			'languageCode'             => 'LANGUAGE_ET',
			'documentStatusTypeCode'   => 'STATUS_CONFIRMED',
			'partnerDocumentId'        => $partnerDocumentId,
			'customer'                 => array(
				'name'              => (string) ( $order['customer_name'] ?? 'Customer' ),
				'email'             => (string) ( $order['customer_email'] ?? '' ),
				'partnerCustomerId' => (string) ( $order['customer_id'] ?? '' ),
			),
			'rows'                     => array(
				array(
					'id'         => 0,
					'articleName'=> (string) ( $order['product_name'] ?? 'Digital service' ),
					'quantity'   => 1,
					'price'      => $netAmount,
					'vatPercent' => $vatPercent,
					'typeCode'   => 'ARTICLE_ROW_SELL',
				),
			),
			'payments'                 => array(
				array(
					'sum'             => $amountPaid,
					'paymentTypeName' => $this->config->getString( 'erply_payment_type', 'Stripe' ),
				),
			),
		);

		$response = $this->request( 'POST', '/invoices/partner', $payload );

		if ( isset( $response['id'] ) ) {
			return (string) $response['id'];
		}

		if ( isset( $response['invoiceId'] ) ) {
			return (string) $response['invoiceId'];
		}

		return $partnerDocumentId;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	private function request( string $method, string $path, array $body = array() ): array {
		$token = $this->config->getString( 'erply_api_token' );
		$url   = 'https://api.erplybooks.com/api' . $path;

		$ch = curl_init( $url );
		if ( false === $ch ) {
			throw new \RuntimeException( 'Failed to init cURL.' );
		}

		$headers = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'X-API-TOKEN: ' . $token,
		);

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_POSTFIELDS     => json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				CURLOPT_TIMEOUT        => 30,
			)
		);

		$raw      = curl_exec( $ch );
		$httpCode = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error    = curl_error( $ch );
		curl_close( $ch );

		if ( false === $raw ) {
			throw new \RuntimeException( 'ERPLY request failed: ' . $error );
		}

		/** @var array<string, mixed>|null $decoded */
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( 'ERPLY returned invalid JSON (HTTP ' . $httpCode . ').' );
		}

		if ( $httpCode >= 400 ) {
			$message = isset( $decoded['message'] ) ? (string) $decoded['message'] : $raw;
			throw new \RuntimeException( 'ERPLY error HTTP ' . $httpCode . ': ' . $message );
		}

		return $decoded;
	}
}
