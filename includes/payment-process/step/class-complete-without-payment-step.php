<?php
/**
 * Class Complete_Without_Payment_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Order;
use WC_Payment_Token_CC;
use WC_Payments;
use WC_Payments_Utils;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
 */
final class Complete_Without_Payment_Step extends Abstract_Step {
	/**
	 * The active gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Instantiates the step.
	 */
	public function __construct() {
		// @todo: Change this with proper dependencies.
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Checks if the step is applicable to a given payment.
	 *
	 * @param Payment $payment A payment, which will be processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return false;
		}

		$order                        = $payment->get_order();
		$payment_needed               = $order->get_total() > 0;
		$save_payment_method_to_store = $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE );

		return apply_filters( 'wcpay_confirm_without_payment_intent', ! $payment_needed && ! $save_payment_method_to_store );
	}

	/**
	 * Performs the main actions, related to the step.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$order          = $payment->get_order();
		$payment_method = $payment->get_payment_method();

		$order->payment_complete();
		$order->set_payment_method_title( __( 'Credit / Debit Card', 'woocommerce-payments' ) );

		if ( $payment_method instanceof Saved_Payment_Method ) {
			// We need to make sure the saved payment method is saved to the order so we can
			// charge the payment method for a future payment.
			$this->gateway->add_token_to_order( $order, $payment_method->get_token() );

			if ( $payment->is( Payment::CHANGING_SUBSCRIPTION_PAYMENT_METHOD ) ) {
				$this->add_subscription_payment_method_change_note( $order, $payment_method );
			}
		}

		$payment->complete(
			[
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url( $order ),
			]
		);
	}

	/**
	 * Adds a note whenever the payment method for a subscription is changed.
	 *
	 * @param WC_Order             $order          The order, where the note should be added.
	 * @param Saved_Payment_Method $payment_method The new payment method.
	 */
	protected function add_subscription_payment_method_change_note( WC_Order $order, Saved_Payment_Method $payment_method ) {
		$token = $payment_method->get_token();
		$last4 = $token instanceof WC_Payment_Token_CC ? $token->get_last4() : '----';

		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the last 4 digit of the credit card */
				__( 'Payment method is changed to: <strong>Credit card ending in %1$s</strong>.', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
				]
			),
			$last4
		);
		$order->add_order_note( $note );

		do_action( 'woocommerce_payments_changed_subscription_payment_method', $order, $token );
	}
}
