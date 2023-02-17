<?php
namespace WCPay\Payment_Process\Step;

use WC_Order;
use WC_Payment_Tokens;
use WC_Subscriptions;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
 */
class Add_Token_To_Order_Step extends Abstract_Step {
	public function get_id() {
		return 'add-token-to-order';
	}

	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment
			&& $payment->get_payment_method() instanceof Saved_Payment_Method;
	}

	public function complete( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$payment_method = $payment->get_payment_method();
		if ( ! $payment_method instanceof Saved_Payment_Method ) {
			return;
		}

		// We need to make sure the saved payment method is saved to the order so we can
		// charge the payment method for a future payment.
		$payment_token = $payment_method->get_token();
		$order         = $payment->get_order();

		// Load the existing token, if any.
		$order_token = $this->get_order_token( $order );

		// This could lead to tokens being saved twice in an order's payment tokens, but it is needed so that shoppers
		// may re-use a previous card for the same subscription, as we consider the last token to be the active one.
		// We can't remove the previous entry for the token because WC_Order does not support removal of tokens [1] and
		// we can't delete the token as it might be used somewhere else.
		// [1] https://github.com/woocommerce/woocommerce/issues/11857.
		if ( is_null( $order_token ) || $payment_token->get_id() !== $order_token->get_id() ) {
			$order->add_payment_token( $payment_token );
		}

		if ( $this->is_subscriptions_enabled() ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
			if ( is_array( $subscriptions ) ) {
				foreach ( $subscriptions as $subscription ) {
					$subscription_token = $this->get_order_token( $subscription );
					if ( is_null( $subscription_token ) || $payment_token->get_id() !== $subscription_token->get_id() ) {
						$subscription->add_payment_token( $payment_token );
					}
				}
			}
		}
	}

	/**
	 * Retrieves the payment token, associated with an order.
	 *
	 * @param WC_Order $order Order, which might have a token.
	 * @param WC_Payment_Token|null
	 */
	protected function get_order_token( WC_Order $order ) {
		$tokens   = $order->get_payment_tokens();
		$token_id = end( $tokens );
		$token    = $token_id ? null : WC_Payment_Tokens::get( $token_id );

		return $token;
	}

	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * Subscriptions functionality is enabled if the WC Subscriptions plugin is active and greater than v 2.2, or the base feature is turned on.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 * @todo: This should definitely not be here, temporarily copied from the subscriptions trait.
	 */
	public function is_subscriptions_enabled() {
		if ( $this->is_subscriptions_plugin_active() ) {
			return version_compare( $this->get_subscriptions_plugin_version(), '2.2.0', '>=' );
		}

		// TODO update this once we know how the base library feature will be enabled.
		return class_exists( 'WC_Subscriptions_Core_Plugin' );
	}

	/**
	 * Checks if the WC Subscriptions plugin is active.
	 *
	 * @return bool Whether the plugin is active or not.
	 */
	public function is_subscriptions_plugin_active() {
		return class_exists( 'WC_Subscriptions' );
	}

	/**
	 * Gets the version of WooCommerce Subscriptions that is active.
	 *
	 * @return null|string The plugin version. Returns null when WC Subscriptions is not active/loaded.
	 */
	public function get_subscriptions_plugin_version() {
		return class_exists( 'WC_Subscriptions' ) ? WC_Subscriptions::$version : null;
	}
}
