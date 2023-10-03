<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception; // Temporary exception! This service would have its own exception when more business logics are added.
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Payment\Exception\StateTransitionException;

/**
 * Payment Processing Service.
 */
class PaymentProcessingService {
	/**
	 * Factory for states.
	 *
	 * @var StateFactory
	 */
	private $state_factory;

	/**
	 * Service constructor.
	 *
	 * @param StateFactory $state_factory Factory for payment states.
	 */
	public function __construct( StateFactory $state_factory ) {
		$this->state_factory = $state_factory;
	}

	/**
	 * Process payment.
	 *
	 * @param  int $order_id Order ID provided by WooCommerce core.
	 *
	 * @throws Exception
	 * @throws StateTransitionException In case a state cannot be initialized.
	 * @throws ContainerException       When the dependency container cannot instantiate the state.
	 */
	public function process_payment( int $order_id ) {
		$context = new PaymentContext();
		$state   = $this->state_factory->create_state( InitialState::class, $context );
		$state   = $state->process();

		return $state;
	}
}
