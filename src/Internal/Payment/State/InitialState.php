<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Service\OrderService;

/**
 * Initial state, representing a freshly created payment.
 */
class InitialState extends PaymentState {
	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private $order_service;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory $state_factory Factory for payment states.
	 * @param OrderService $order_service Service for order-related actions.
	 */
	public function __construct(
		StateFactory $state_factory,
		OrderService $order_service
	) {
		parent::__construct( $state_factory );

		$this->order_service = $order_service;
	}

	/**
	 * Initialtes the payment process.
	 *
	 * @return CompletedState The next state.
	 * @throws StateTransitionException Completed state could not be initialized.
	 * @throws Order_Not_Found_Exception Order could not be found.
	 */
	public function process() {
		$order_id       = $this->get_context()->get_order_id();
		$payment_method = $this->get_context()->get_payment_method();
		$this->order_service->set_payment_method_id( $order_id, $payment_method->get_id() );

		return $this->create_state( CompletedState::class );
	}
}
