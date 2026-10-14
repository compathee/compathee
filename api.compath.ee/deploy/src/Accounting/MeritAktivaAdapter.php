<?php

declare(strict_types=1);

namespace Compath\Hub\Accounting;

use Compath\Hub\Config;

/**
 * Merit Aktiva adapter (stub for future migration).
 * See https://api.merit.ee/connecting-robots/reference-manual/
 */
final class MeritAktivaAdapter implements AccountingAdapterInterface {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function createPaidInvoice( array $order ): string {
		throw new \RuntimeException(
			'Merit Aktiva adapter is not implemented yet. Set accounting_provider to "erply" in config.php.'
		);
	}
}
