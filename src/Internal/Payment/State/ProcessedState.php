<?php
/**
 * Class ProcessedState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Internal\Service\OrderService;
use WCPay\Vendor\League\Container\Exception\ContainerException;
use WCPay\Internal\Proxy\LegacyProxy;
use WC_Payment_Gateway_WCPay;

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
	 * Duplicate Payment Prevention service.
	 *
	 * @var DuplicatePaymentPreventionService
	 */
	private $dpps;

	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory                      $state_factory Factory for payment states.
	 * @param OrderService                      $order_service Service for order-related actions.
	 * @param DuplicatePaymentPreventionService $dpps          Service for preventing duplicate payments.
	 * @param LegacyProxy                       $legacy_proxy  Legacy proxy.
	 */
	public function __construct(
		StateFactory $state_factory,
		OrderService $order_service,
		DuplicatePaymentPreventionService $dpps,
		LegacyProxy $legacy_proxy
	) {
		parent::__construct( $state_factory );

		$this->order_service = $order_service;
		$this->dpps          = $dpps;
		$this->legacy_proxy  = $legacy_proxy;
	}

	/**
	 * Complete processing.
	 *
	 * @return AbstractPaymentState
	 * @throws Order_Not_Found_Exception
	 * @throws StateTransitionException
	 * @throws ContainerException
	 */
	public function complete_processing() {
		$context  = $this->get_context();
		$order_id = $context->get_order_id();

		// Complete processing.
		$this->dpps->remove_session_processing_order( $order_id );
		$this->order_service->update_order_from_successful_intent( $order_id, $context->get_intent(), $context );

		// cleaning up.
		$this->legacy_proxy->call_function( 'wc_reduce_stock_levels', $order_id );
		$this->clear_cart();

		// If everything went well, transition to the completed state.
		return $this->create_state( CompletedState::class );
	}

	/**
	 * Clear the cart.
	 *
	 * @return void
	 */
	private function clear_cart() {
		$cart = $this->legacy_proxy->call_function( 'wc' )->cart;
		if ( isset( $cart ) ) {
			$cart->empty_cart();
		}
	}
}
