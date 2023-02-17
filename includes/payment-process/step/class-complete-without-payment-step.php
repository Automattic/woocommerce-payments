<?php
namespace WCPay\Payment_Process\Step;

use WC_Payment_Token_CC;
use WC_Payments;
use WC_Payments_Utils;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
 */
class Complete_Without_Payment_Step extends Abstract_Step {
	/**
	 * The active gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	public function __construct() {
		// @todo: Change this with proper dependencies.
		$this->gateway = WC_Payments::get_gateway();
	}

	public function get_id() {
		return 'complete-without-payment';
	}

	public function is_applicable( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return false;
		}

		$order                        = $payment->get_order();
		$payment_needed               = $order->get_total() > 0;
		$save_payment_method_to_store = $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE );

		return apply_filters( 'wcpay_confirm_without_payment_intent', ! $payment_needed && ! $save_payment_method_to_store );
	}

	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$order = $payment->get_order();
		$order->payment_complete();

		$payment_method = $payment->get_payment_method();
		if ( $payment->is( Payment::CHANGING_SUBSCRIPTION_PAYMENT_METHOD ) && $payment_method instanceof Saved_Payment_Method ) {
			$token = $payment_method->get_token();
			$note  = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the last 4 digit of the credit card */
					__( 'Payment method is changed to: <strong>Credit card ending in %1$s</strong>.', 'woocommerce-payments' ),
					[
						'strong' => '<strong>',
					]
				),
				$token instanceof WC_Payment_Token_CC ? $token->get_last4() : '----'
			);
			$order->add_order_note( $note );

			do_action( 'woocommerce_payments_changed_subscription_payment_method', $order, $token );
		}

		$order->set_payment_method_title( __( 'Credit / Debit Card', 'woocommerce-payments' ) );
		$order->save();

		$payment->complete(
			[
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url( $order ),
			]
		);
	}
}
