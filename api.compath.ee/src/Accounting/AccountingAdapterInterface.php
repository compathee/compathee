<?php

declare(strict_types=1);

namespace Compath\Hub\Accounting;

/** @param array<string, mixed> $payment */

interface AccountingAdapterInterface {

	/**
	 * Create a paid sales invoice after Stripe payment.
	 *
	 * @param array<string, mixed> $order
	 */
	public function createPaidInvoice( array $order ): string;
}
