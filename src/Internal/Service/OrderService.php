<?php
/**
 * Class OrderService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payments_Order_Service;
use WCPay\Exceptions\Order_Not_Found_Exception;

/**
 * Service for managing orders.
 *
 * This service's public methods should only require order IDs rather than objects,
 * avoiding direct access to the `$order` object witnin `src` (except for this class).
 */
class OrderService {
	/**
	 * Legacy order service.
	 *
	 * TEMPORARY: The legacy order service should be migrated here
	 * once `WC_Order` parameters have been converted to order IDS.

	 * @todo: Add a GH issue link here.
	 * @var WC_Payments_Order_Service
	 */
	private $legacy_service;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_Order_Service $legacy_service The legacy order service.
	 */
	public function __construct( WC_Payments_Order_Service $legacy_service ) {
		$this->legacy_service = $legacy_service;
	}

	/**
	 * Set the payment metadata for payment method id.
	 *
	 * @param int    $order_id          ID of the order.
	 * @param string $payment_method_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_payment_method_id( int $order_id, string $payment_method_id ) {
		$this->legacy_service->set_payment_method_id_for_order( $order_id, $payment_method_id );
	}
}
