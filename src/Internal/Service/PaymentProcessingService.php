<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception; // Temporary exception! This service would have its own exception when more business logics are added.
use WC_Order;
use WCPay\Internal\Payment\Exception\MethodUnavailableException;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\Payment;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\StateFactory;
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
	 */
	public function process_payment( int $order_id ) {
		$order = wc_get_order( $order_id ); // ToDo: This function should not be called directly, but through a service!
		if ( ! $order instanceof WC_Order ) {
			throw new Exception( __( 'Processing payment failed: Order could not be loaded.', 'woocommerce-payments' ) );
		}

		try {
			$request = new PaymentRequest( $this->legacy_proxy, [ 'payment_method_id' => 'pm_XYZ' ] );
			$payment = new Payment( $order, $this->state_factory );
			$payment->prepare( $request->get_payment_method_id() );
			return $payment->get_gateway_response();
		} catch ( StateTransitionException $e ) {
			return false; // ToDo: We need a proper gateway response here.
		} catch ( MethodUnavailableException $e ) {
			return false; // ToDo: We need a proper gateway response here.
		}
	}
}
