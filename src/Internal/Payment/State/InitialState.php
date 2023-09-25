<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WC_Payments_Order_Service;
use WCPay\Internal\Payment\StateFactory;

/**
 * Initial payment state.
 */
class InitialState extends State {
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
	 * Processes a new payment.
	 *
	 * @return State Returns the next payment state.
	 */
	public function process() {
		$context = $this->get_context();
		$order   = $this->order_service->get_order( $context->get_order_id() );

		// Perform actions with the order.
		$order->get_total();

		return $this->create_state( CompletedState::class );
	}
}
