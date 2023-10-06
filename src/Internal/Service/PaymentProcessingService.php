<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception; // Temporary exception! This service would have its own exception when more business logics are added.
use WCPay\Vendor\League\Container\Exception\ContainerException;
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
	 * @param int  $order_id       Order ID provided by WooCommerce core.
	 * @param bool $manual_capture Whether to only create an authorization instead of a charge (optional).
	 *
	 * @throws Exception
	 * @throws StateTransitionException In case a state cannot be initialized.
	 * @throws PaymentRequestException  When the request is malformed. This should be converted to a failure state.
	 * @throws ContainerException       When the dependency container cannot instantiate the state.
	 */
	public function process_payment( int $order_id, bool $manual_capture = false ) {
		// Start with a basis context.
		$context = $this->create_payment_context( $order_id, $manual_capture );

		$request         = new PaymentRequest( $this->legacy_proxy );
		$initial_state   = $this->state_factory->create_state( InitialState::class, $context );
		$completed_state = $initial_state->process( $request );

		return $completed_state;
	}

	/**
	 * Instantiates a new empty payment context.
	 *
	 * @param int  $order_id       ID of the order that the context belongs to.
	 * @param bool $manual_capture Whether manual capture is enabled.
	 * @return PaymentContext
	 */
	protected function create_payment_context( int $order_id, bool $manual_capture = false ): PaymentContext {
		$context = new PaymentContext( $order_id );
		$context->toggle_manual_capture( $manual_capture );
		return $context;
	}
}
