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
use WCPay\Internal\Payment\Storage;
use WCPay\Internal\Payment\Response\ResponseInterface;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Payment Processing Service.
 */
class PaymentProcessingService {
	/**
	 * Storage for payments.
	 *
	 * @var Storage
	 */
	protected $storage;

	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	protected $legacy_proxy;

	/**
	 * Class constructor.
	 *
	 * @param Storage     $storage       Payment storage.
	 * @param LegacyProxy $legacy_proxy  Legacy proxy.
	 */
	public function __construct(
		Storage $storage,
		LegacyProxy $legacy_proxy
	) {
		$this->storage      = $storage;
		$this->legacy_proxy = $legacy_proxy;
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
			$payment = $this->storage->get_order_payment( $order );
			$payment = $payment->process( $request );
			return $payment->get_processing_response();
		} catch ( Exception $e ) {
			return new Failure( $e->getMessage() );
		}
	}
}
