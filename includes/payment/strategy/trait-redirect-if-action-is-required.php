<?php
/**
 * Trait Redirect_If_Action_Is_Required
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use WC_Payments_API_Intention;
use WC_Payments_Utils;
use WCPay\Payment\Payment;

/**
 * Used in steps, where the next action of an intent should be processed.
 *
 * The `redirect_if_action_is_required` redirects to external URLs when
 * external confirmation is needed, otherwise prepares a hash to transfer data.
 *
 * IMPORTANT!
 * This trait requires $this->account to be an instance of WC_Payment_Account.
 */
trait Redirect_If_Action_Is_Required {
	/**
	 * Redirects to the right screen for the next action. That could be just a redirect,
	 * or a more complicated hash change, which will trigger a modal on checkout.
	 *
	 * @param Payment                   $payment A payment, being processed.
	 * @param WC_Payments_API_Intention $intent  The intention, returned from the server.
	 */
	protected function redirect_if_action_is_required( Payment $payment, WC_Payments_API_Intention $intent ) {
		$next_action = $intent->get_next_action();

		if (
			isset( $next_action['type'] )
			&& 'redirect_to_url' === $next_action['type']
			&& ! empty( $next_action['redirect_to_url']['url'] )
		) {
			return [
				'result'   => 'success',
				'redirect' => $next_action['redirect_to_url']['url'],
			];
		}

		// @todo: Utils are static and hard to mock here. Replace with a standard method.
		$encrypted_secret = WC_Payments_Utils::encrypt_client_secret(
			$this->account->get_stripe_account_id(),
			$intent->get_client_secret()
		);

		$redirect = sprintf(
			'#wcpay-confirm-%s:%s:%s:%s',
			'pi', // @todo: Setup intents should have `si` here.
			$payment->get_order()->get_id(),
			$encrypted_secret,
			// Include a new nonce for update_order_status to ensure the update order
			// status call works when a guest user creates an account during checkout.
			wp_create_nonce( 'wcpay_update_order_status_nonce' )
		);

		return [
			'result'         => 'success',
			'redirect'       => $redirect,
			// Include the payment method ID so the Blocks integration can save cards.
			'payment_method' => $payment->get_payment_method()->get_id(),
		];
	}
}
