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
use WC_Payments_Subscription_Service;
use WCPay\Internal\Payment\Flag;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\Storage;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\WooPay\WooPay_Utilities;

/**
 * Payment Processing Service.
 */
class PaymentProcessingService {
	/**
	 * Storage for payments.
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Subscriptions service.
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private $subscriptions_service;

	/**
	 * Class constructor.
	 *
	 * @param Storage                          $storage               Payment storage.
	 * @param LegacyProxy                      $legacy_proxy          Legacy proxy.
	 * @param WC_Payments_Subscription_Service $subscriptions_service Subscriptions service.
	 */
	public function __construct(
		Storage $storage,
		LegacyProxy $legacy_proxy,
		WC_Payments_Subscription_Service $subscriptions_service
	) {
		$this->storage               = $storage;
		$this->legacy_proxy          = $legacy_proxy;
		$this->subscriptions_service = $subscriptions_service;
	}

	/**
	 * Process payment.
	 *
	 * @param int  $order_id       Order ID provided by WooCommerce core.
	 * @param bool $manual_capture Whether the payment should be captured manually. Defaults to false.
	 * @return string              The response from processing the payment.
	 * @throws Exception
	 */
	public function process_payment( int $order_id, bool $manual_capture = false ) {
		$order = wc_get_order( $order_id ); // ToDo: This function should not be called directly, but through a service!

		if ( ! $order instanceof WC_Order ) {
			throw new Exception( __( 'Processing payment failed: Order could not be loaded.', 'woocommerce-payments' ) );
		}

		try {
			// Warning: Dev mode only! Will be deleted soon.
			$this->storage->enable();
			$this->storage->cleanup_order_payment( $order );

			$post_data = [
				'payment_method'       => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				'wcpay-payment-method' => 'pm_XYZ',
				'wc-woocommerce_payments-new-payment-method' => true,
			];

			// Initialize the request and payment.
			$request = new PaymentRequest( $this->legacy_proxy, $post_data );
			$state   = $this->storage->get_order_payment( $order );
			$context = $state->get_context();

			// Setup flags.
			if ( $request->should_save_payment_method() ) {
				$state->get_context()->add_flag( Flag::SAVE_PAYMENT_METHOD_TO_STORE );
			}
			if ( $manual_capture ) {
				$state->get_context()->add_flag( Flag::MANUAL_CAPTURE );
			}
			if ( $this->subscriptions_service->is_payment_recurring( $order ) ) {
				// Subs-specific behavior starts here.
				$state->get_context()->add_flag( Flag::RECURRING );

				// The payment method is always saved for subscriptions, unless already saved.
				$state->get_context()->add_flag( Flag::SAVE_PAYMENT_METHOD_TO_STORE );

				if ( $this->subscriptions_service->is_changing_payment_method_for_subscription() ) {
					$state->get_context()->add_flag( Flag::CHANGING_SUBSCRIPTION_PAYMENT_METHOD );
				}
			}

			// Transfer the necessary data from the request into the payment object.
			$payment_method = $request->get_payment_method();
			$context->set_payment_method( $payment_method );

			// Call the main method, proceeding to the next state.
			$state = $state->process();

			// Temporary: Store the order payment, and load it immediately, ensuring that everything is stored.
			$this->storage->save_order_payment( $state );
			// Not very pleasant, but we're dealing with different orders here.
			$state = $this->storage->get_order_payment( wc_get_order( $order->get_id() ) );

			return $state->get_processing_response();
		} catch ( Exception $e ) {
			throw $e; // Keep some GH comments from losing context.
		}
	}
}
