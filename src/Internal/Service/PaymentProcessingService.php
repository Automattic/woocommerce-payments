<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception; // Temporary exception! This service would have its own exception when more business logics are added.
use WC_Order;
use WC_Payment_Gateway_WCPay;
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
	 * @return ResponseInterface The response from processing the payment.
	 */
	public function process_payment( int $order_id ) {
		$order = wc_get_order( $order_id ); // ToDo: This function should not be called directly, but through a service!

		if ( ! $order instanceof WC_Order ) {
			return new Failure( __( 'Processing payment failed: Order could not be loaded.', 'woocommerce-payments' ) );
		}

		try {
			// Warning: Dev mode only! Will be deleted soon.
			$this->storage->cleanup_order_payment( $order );

			$post_data = [
				'payment_method'       => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				'wcpay-payment-method' => 'pm_XYZ',
			];

			$payment = $this->storage->get_order_payment( $order );

			$request = new PaymentRequest( $this->legacy_proxy, $post_data );
			$payment = $payment->process( $request );

			// Temporary: Store the order payment, and load it immediately, ensuring that everything is stored.
			$this->storage->save_order_payment( $payment );
			$payment = $this->storage->get_order_payment( $order );

			return $payment->get_processing_response();
		} catch ( Exception $e ) {
			return new Failure( $e->getMessage() );
		}
	}
}
