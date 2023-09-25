<?php
/**
 * Class CompletedState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WC_Payments_Order_Service;
use WCPay\Internal\Payment\StateFactory;

/**
 * Represents the payment in complete (successful) state.
 */
class CompletedState extends State {
	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory              $state_factory State factory.
	 * @param WC_Payments_Order_Service $order_service Order service.
	 */
	public function __construct(
		StateFactory $state_factory,
		WC_Payments_Order_Service $order_service
	) {
		parent::__construct( $state_factory );

		$this->order_service = $order_service;
	}

	/**
	 * Returns the response, expected from a gateway's `process_payment()` method.
	 *
	 * @return string URL to redirect to.
	 */
	public function get_processing_response() {
		$order = $this->order_service->get_order( $this->get_context()->get_order_id() );
		return $order->get_checkout_order_received_url();
	}
}
