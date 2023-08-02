<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception;

/**
 *  Payment Processing Service.
 */
class PaymentProcessingService {

	/**
	 * Process payment.
	 *
	 * @param  int $order_id Order ID provided by WooCommerce core.
	 *
	 * @throws Exception
	 */
	public function process_payment( int $order_id ) {
		throw new Exception( 'Re-engineering payment process is in-progress. Sit tight, and wait more!' );
	}
}
