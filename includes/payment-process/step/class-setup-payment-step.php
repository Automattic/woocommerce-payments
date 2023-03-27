<?php
/**
 * Class Setup_Payment_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payments;
use WCPay\Core\Server\Request\Create_And_Confirm_Setup_Intention;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Performs a payment, which only creates a setup intent to setup the payment method.
 */
class Setup_Payment_Step extends Abstract_Step {
	/**
	 * The active gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * WC_Payments_Order_Service instance
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payment_Account
	 */
	protected $account;

	/**
	 * Instantiates the step.
	 */
	public function __construct() {
		// @todo: Change this with proper dependencies.
		$this->gateway       = WC_Payments::get_gateway();
		$this->order_service = WC_Payments::$order_service;
		$this->account       = WC_Payments::get_account_service();
	}

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'setup-payment';
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return false;
		}

		// The total is a float, and we need to compare with a float.
		return 0.0 === $payment->get_order()->get_total();
	}

	/**
	 * Creates a new standard payment.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$save_user_in_platform_checkout = false;
		if ( $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_PLATFORM ) ) {
			$order                          = $payment->get_order();
			$metadata                       = $payment->get_metadata();
			$save_user_in_platform_checkout = true;

			$order_metadata = [
				'customer_email' => $order->get_billing_email(),
			];
			$order_metadata = apply_filters(
				'wcpay_metadata_from_order',
				$order_metadata,
				$order
			);
			// prioritize metadata from mobile app.
			$order_metadata = array_merge( (array) $order_metadata, (array) $metadata );
			$payment->set_metadata( $order_metadata );

			do_action( 'woocommerce_payments_save_user_in_platform_checkout' );
		}

		$intent = $this->request_intent_from_server( $payment, $save_user_in_platform_checkout );
		$payment->set_intent( $intent );
	}

	/**
	 * Performs a create and confirm intention request, returning an intent object.
	 *
	 * @param Order_Payment $payment                        The payment, which should be directed to the server.
	 * @param bool          $save_user_in_platform_checkout Whether to save the user in platform checkout.
	 * @return array
	 */
	protected function request_intent_from_server( Order_Payment $payment, bool $save_user_in_platform_checkout ) {
		$request = Create_And_Confirm_Setup_Intention::create();
		$request->set_customer( $payment->get_customer_id() );
		$request->set_payment_method( $payment->get_payment_method()->get_id() );
		$request->set_metadata( $payment->get_metadata() );

		/**
		 * Allows the create and confirm setup intention request to be modified.
		 *
		 * @param Payment_Information $payment_information The payment information object.
		 * @param bool                $save_in_platform_account @todo This one should be described better.
		 * @param bool                $save_payment_method_to_platform @todo This one should be described better.
		 */
		$intent = $request->send(
			'wcpay_create_and_confirm_setup_intention_request',
			$payment,
			false,
			$save_user_in_platform_checkout
		);

		return $intent->to_array();
	}
}
