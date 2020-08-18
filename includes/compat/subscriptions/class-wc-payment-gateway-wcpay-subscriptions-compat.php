<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Compat
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;

/**
 * Gateway class for WooCommerce Payments, with added compatibility with WooCommerce Subscriptions.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Compat extends WC_Payment_Gateway_WCPay {

	/**
	 * WC_Payment_Gateway_WCPay_Subscriptions_Compat constructor.
	 *
	 * @param array ...$args Arguments passed to the main gateway's constructor.
	 */
	public function __construct( ...$args ) {
		parent::__construct( ...$args );

		$this->supports = array_merge(
			$this->supports,
			[
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
			]
		);

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
		add_filter( 'wcs_new_order_created', [ $this, 'copy_token_from_parent_order' ], 10, 2 );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, [ $this, 'update_failing_payment_method' ], 10, 2 );
		add_filter( 'wc_payments_display_save_payment_method_checkbox', [ $this, 'display_save_payment_method_checkbox' ], 10 );
	}

	public function process_payment( $order_id, $is_recurring_payment = false ) {
		return parent::process_payment( $order_id, wcs_order_contains_subscription( $order_id ) );
	}

	public function display_save_payment_method_checkbox( $display ) {
		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}
		// Only render the "Save payment method" checkbox if there are no subscription products in the cart.
		return $display;
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount, $renewal_order ) {
		$order_tokens = WC_Payment_Tokens::get_order_tokens( $renewal_order );
		$token        = empty( $order_tokens ) ? false : end( $order_tokens );
		if ( ! $token ) {
			Logger::error( 'There is no saved payment token for order #' . $renewal_order->get_id() );
			$renewal_order->update_status( 'failed' );
			return;
		}

		try {
			$this->process_payment_for_order(
				[
					'order'           => $renewal_order,
					'cart'            => null,
					'manual_capture'  => false,
					'payment_method'  => $token->get_token(),
					'token'           => $token,
					'is_saved_method' => true,
					'off_session'     => true,
					'is_recurring'    => true,
				]
			);
		} catch ( WC_Payments_API_Exception $e ) {
			Logger::error( 'Error processing subscription renewal: ' . $e->getMessage() );

			$renewal_order->update_status( 'failed' );
		}
	}

	public function copy_token_from_parent_order( $order, $subscription ) {
		$parent = $subscription->get_parent();
		if ( $parent ) {
			$order->update_meta_data( '_payment_tokens', $parent->get_payment_tokens() );
		}
		return $order;
	}

	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$renewal_order_tokens = WC_Payment_Tokens::get_order_tokens( $renewal_order );
		$renewal_token        = end( $renewal_order_tokens );
		$subscription->add_payment_token( $renewal_token );
	}
}
