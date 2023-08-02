<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception; // Temporary exception! This service would have its own exception when more business logics are added.

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
