<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception; // Temporary exception! This service would have its own exception when more business logics are added.
use WC_Order;
use WCPay\Internal\Payment\Payment;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\Response\Failure;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\StateFactory;
use WCPay\Internal\Payment\Response\ResponseInterface;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Payment Processing Service.
 */
class PaymentProcessingService {
	/**
	 * State factory.
	 *
	 * @var StateFactory
	 */
	protected $state_factory;

	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	protected $legacy_proxy;

	/**
	 * Class constructor.
	 *
	 * @param StateFactory $state_factory Factory for states.
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
	 * @param int $order_id Order ID provided by WooCommerce core.
	 * @throws Exception If the order was not found.
	 * @return ResponseInterface The response from processing the payment.
	 */
	public function process_payment( int $order_id ) {
		$order = wc_get_order( $order_id ); // ToDo: This function should not be called directly, but through a service!

		try {
			if ( ! $order instanceof WC_Order ) {
				throw new Exception( __( 'Processing payment failed: Order could not be loaded.', 'woocommerce-payments' ) );
			}

			$request = new PaymentRequest( $this->legacy_proxy, [ 'payment_method_id' => 'pm_XYZ' ] );
			$payment = new Payment( $order );
			$state   = new InitialState( $payment );
			$state   = $state->process( $request );
			return $state->get_processing_response();
		} catch ( Exception $e ) {
			return new Failure( $e->getMessage() );
		}
	}
}
