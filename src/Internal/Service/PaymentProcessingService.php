<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payments_API_Abstract_Intention;
use WC_Payments_API_Setup_Intention;
use WCPay\Exceptions\Order_Not_Found_Exception;
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
	 * @param LegacyProxy  $legacy_proxy Legacy proxy.
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
	 * @param int  $order_id Order ID provided by WooCommerce core.
	 * @param bool $manual_capture Whether to only create an authorization instead of a charge (optional).
	 *
	 * @throws StateTransitionException  In case a state cannot be initialized.
	 * @throws PaymentRequestException   When the request is malformed. This should be converted to a failure state.
	 * @throws Order_Not_Found_Exception When order is not found.
	 * @throws ContainerException        When the dependency container cannot instantiate the state.
	 */
	public function process_payment( int $order_id, bool $manual_capture = false ) {
		// Start with a basis context.
		$context = $this->create_payment_context( $order_id, $manual_capture );

		$request       = new PaymentRequest( $this->legacy_proxy );
		$initial_state = $this->state_factory->create_state( InitialState::class, $context );

		return $initial_state->start_processing( $request );
	}

	/**
	 * Get redirect URL when authentication is required (3DS).
	 *
	 * @param WC_Payments_API_Abstract_Intention $intent Intent object.
	 * @param int                                $order_id Order id.
	 *
	 * @return string
	 */
	public function get_authentication_redirect_url( $intent, int $order_id ) {
		$next_action = $intent->get_next_action();

		if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
			return $next_action['redirect_to_url']['url'];
		}

		$client_secret = $intent->get_client_secret();

		if ( $this->legacy_proxy->call_static( \WC_Payments_Features::class, 'is_client_secret_encryption_enabled' ) ) {
			$client_secret = $this->legacy_proxy->call_function(
				'openssl_encrypt',
				$client_secret,
				'aes-128-cbc',
				substr( $intent->get_customer_id(), 5 ),
				0,
				str_repeat( 'WC', 8 )
			);
		}

		return sprintf(
			'#wcpay-confirm-%s:%s:%s:%s',
			$intent instanceof WC_Payments_API_Setup_Intention ? 'si' : 'pi',
			$order_id,
			$client_secret,
			$this->legacy_proxy->call_function( 'wp_create_nonce', 'wcpay_update_order_status_nonce' )
		);
	}

	/**
	 * Instantiates a new empty payment context.
	 *
	 * @param int  $order_id ID of the order that the context belongs to.
	 * @param bool $manual_capture Whether manual capture is enabled.
	 *
	 * @return PaymentContext
	 */
	protected function create_payment_context( int $order_id, bool $manual_capture = false ): PaymentContext {
		$context = new PaymentContext( $order_id );
		$context->toggle_manual_capture( $manual_capture );

		return $context;
	}
}
