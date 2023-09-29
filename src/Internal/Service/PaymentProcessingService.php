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
use WCPay\Internal\Payment\PaymentRequestException;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Proxy\LegacyProxy;

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
	 * Legacy Proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Service constructor.
	 *
	 * @param StateFactory $state_factory Factory for payment states.
	 * @param LegacyProxy  $legacy_proxy  Legacy proxy.
	 */
	public function __construct(
		StateFactory $state_factory,
		LegacyProxy $legacy_proxy
	) {
		$this->state_factory = $state_factory;
		$this->legacy_proxy  = $legacy_proxy;
	}

	/**
	 * Process payment.
	 *
	 * @param  int $order_id Order ID provided by WooCommerce core.
	 *
	 * @throws Exception
	 * @throws StateTransitionException In case a state cannot be initialized.
	 * @throws PaymentRequestException  When the request is malformed. This should be converted to a failure state.
	 */
	public function process_payment( int $order_id ) {
		// Start with a basis context.
		$context = new PaymentContext();
		$context->set_order_id( $order_id );

		// Add details from the request.
		$request = new PaymentRequest( $this->legacy_proxy );
		$request->populate_context( $context );

		$state = $this->state_factory->create_state( InitialState::class, $context );
		$state = $state->process();

		return $state;
	}
}
