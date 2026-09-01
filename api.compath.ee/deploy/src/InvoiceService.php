<?php

declare(strict_types=1);

namespace Compath\Hub;

use Compath\Hub\Accounting\AccountingAdapterInterface;
use Compath\Hub\Accounting\ErplyBooksAdapter;
use Compath\Hub\Accounting\MeritAktivaAdapter;

final class InvoiceService {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param array<string, mixed> $order
	 */
	public function createPaidInvoice( array $order ): string {
		return $this->adapter()->createPaidInvoice( $order );
	}

	private function adapter(): AccountingAdapterInterface {
		return match ( $this->config->accountingProvider() ) {
			'merit' => new MeritAktivaAdapter( $this->config ),
			default => new ErplyBooksAdapter( $this->config ),
		};
	}
}
