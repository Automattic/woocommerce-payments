<?php
/**
 * Class ProcessedState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Service\OrderService;
use WCPay\Vendor\League\Container\Exception\ContainerException;

/**
 * This state is used when payment is completed on the server, and we need to update date on the plugin side.
 */
class ProcessedState extends AbstractPaymentState {
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
	 * Process all needed verifications.
	 *
	 * @return AbstractPaymentState
	 * @throws Order_Not_Found_Exception
	 * @throws StateTransitionException
	 * @throws ContainerException
	 */
	public function complete() {
		$context = $this->get_context();

		// Complete processing.
		$this->order_service->update_order_from_successful_intent( $context->get_order_id(), $context->get_intent(), $context );

		// If everything went well, transition to the completed state.
		return $this->create_state( CompletedState::class );
	}
}
