<?php
/**
 * Class Standard_Payment_Strategy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use WCPay\Core\Server\Request\Create_And_Confirm_Setup_Intention;
use WCPay\Payment\Flags;
use WCPay\Payment\Payment;
use WCPay\Payment\State\Payment_State;
use WCPay\Payment\State\Processed_State;

/**
 * Represents a payment processing strategy.
 */
final class Setup_Payment_Strategy extends Strategy {
	/**
	 * Processes the payment.
	 *
	 * @param  Payment $payment The payment to process.
	 * @return Payment_State    The next state of the payment.
	 */
	public function process( Payment $payment ): Payment_State {
		$save_user_in_platform_checkout = false;

		if ( $payment->is( Flags::SAVE_PAYMENT_METHOD_TO_PLATFORM ) ) {
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

		return new Processed_State( $payment );
	}

	/**
	 * Performs a create and confirm intention request, returning an intent object.
	 *
	 * @param Payment $payment                        The payment, which should be directed to the server.
	 * @param bool    $save_user_in_platform_checkout Whether to save the user in platform checkout.
	 * @return array
	 */
	protected function request_intent_from_server( Payment $payment, bool $save_user_in_platform_checkout ) {
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
