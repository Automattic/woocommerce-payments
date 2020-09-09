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
use WCPay\Payment_Information;

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
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, [ $this, 'update_failing_payment_method' ], 10, 2 );
		add_filter( 'wc_payments_display_save_payment_method_checkbox', [ $this, 'display_save_payment_method_checkbox' ], 10 );

		// Display the credit card used for a subscription in the "My Subscriptions" table.
		add_filter( 'woocommerce_my_subscriptions_payment_method', [ $this, 'maybe_render_subscription_payment_method' ], 10, 2 );

		// Allow store managers to manually set Stripe as the payment method on a subscription.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );
		add_filter( 'woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 3 );
		add_action( 'wcs_save_other_payment_meta', [ $this, 'save_meta_in_order_tokens' ], 10, 4 );
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param int  $order_id Order ID to process the payment for.
	 * @param bool $is_recurring_payment Whether this is a one-off payment (false) or it's the first installment of a recurring payment (true).
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id, $is_recurring_payment = false ) {
		return parent::process_payment( $order_id, wcs_order_contains_subscription( $order_id ) );
	}

	/**
	 * Returns a boolean value indicating whether the save payment checkbox should be
	 * displayed during checkout.
	 *
	 * Returns `false` if the cart currently has a subscriptions. Returns the value in
	 * `$display` otherwise.
	 *
	 * @param bool $display Bool indicating whether to show the save payment checkbox in the absence of subscriptions.
	 *
	 * @return bool Indicates whether the save payment method checkbox should be displayed or not.
	 */
	public function display_save_payment_method_checkbox( $display ) {
		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}
		// Only render the "Save payment method" checkbox if there are no subscription products in the cart.
		return $display;
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float    $amount The amount to charge.
	 * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount, $renewal_order ) {
		$token = $this->get_payment_token( $renewal_order );
		if ( is_null( $token ) ) {
			Logger::error( 'There is no saved payment token for order #' . $renewal_order->get_id() );
			$renewal_order->update_status( 'failed' );
			return;
		}

		$payment_information = new Payment_Information( '', $token, true );

		try {
			// TODO: make `force_saved_card` and adding the 'recurring' metadata 2 distinct features.
			$this->process_payment_for_order( $renewal_order, null, $payment_information, false, true );
		} catch ( WC_Payments_API_Exception $e ) {
			Logger::error( 'Error processing subscription renewal: ' . $e->getMessage() );

			$renewal_order->update_status( 'failed' );
		}
	}

	/**
	 * Adds the payment token from a failed renewal order to the provided subscription.
	 *
	 * @param WC_Subscription $subscription The subscription to be updated.
	 * @param WC_Order        $renewal_order The failed renewal order.
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$renewal_token = $this->get_payment_token( $renewal_order );
		if ( is_null( $renewal_token ) ) {
			Logger::error( 'Failing subscription could not be updated: there is no saved payment token for order #' . $renewal_order->get_id() );
			return;
		}
		$this->add_token_to_order( $subscription, $renewal_token );
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param array           $payment_meta Associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription The subscription order.
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$active_token = $this->get_payment_token( $subscription );

		$payment_meta[ WC_Payment_Gateway_WCPay::GATEWAY_ID ] = [
			'wc_order_tokens' => [
				'payment_method_id' => [
					'label' => __( 'Saved payment method ID', 'woocommerce-payments' ),
					'value' => empty( $active_token ) ? '' : strval( $active_token->get_token() ),
				],
			],
		];
		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param string          $payment_method_id The ID of the payment gateway to validate.
	 * @param array           $payment_meta      Associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription      The subscription order.
	 *
	 * @throws Exception When $payment_meta is not valid.
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $payment_method_id ) {
			return;
		}

		if ( empty( $payment_meta['wc_order_tokens']['payment_method_id']['value'] ) ) {
			throw new Exception( __( 'A customer saved payment method was not selected for this order.', 'woocommerce-payments' ) );
		}

		$payment_method_id = $payment_meta['wc_order_tokens']['payment_method_id']['value'];
		$token             = $this->get_token_from_payment_method_id( $subscription->get_user_id(), $payment_method_id );

		if ( empty( $token ) ) {
			throw new Exception( __( 'The saved payment method selected is invalid or does not exist for this customer.', 'woocommerce-payments' ) );
		}
	}

	/**
	 * Save subscriptions payment_method metadata to the order tokens when its type is wc_order_tokens.
	 *
	 * @param WC_Subscription $subscription The subscription to be updated.
	 * @param string          $table        Where to store and retrieve the metadata.
	 * @param string          $meta_key     Meta key to be updated.
	 * @param string          $meta_value   Meta value to be updated.
	 */
	public function save_meta_in_order_tokens( $subscription, $table, $meta_key, $meta_value ) {
		if ( 'wc_order_tokens' !== $table || 'payment_method_id' !== $meta_key ) {
			return;
		}

		$token = $this->get_token_from_payment_method_id( $subscription->get_user_id(), $meta_value );

		if ( empty( $token ) ) {
			return;
		}

		$this->add_token_to_order( $subscription, $token );
	}

	/**
	 * Saves the payment token to the order.
	 *
	 * @param WC_Order         $order The order.
	 * @param WC_Payment_Token $token The token to save.
	 */
	public function add_token_to_order( $order, $token ) {
		parent::add_token_to_order( $order, $token );

		// Set payment token for subscriptions, so it can be used for renewals.
		$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
		foreach ( $subscriptions as $subscription ) {
			parent::add_token_to_order( $subscription, $token );
		}
	}

	/**
	 * Render the payment method used for a subscription in My Account pages
	 *
	 * @param string          $payment_method_to_display Default payment method to display.
	 * @param WC_Subscription $subscription              Subscription object.
	 *
	 * @return string Payment method string to display in UI.
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		try {
			if ( $subscription->get_payment_method() !== $this->id ) {
				return $payment_method_to_display;
			}

			$token = $this->get_payment_token( $subscription );

			if ( is_null( $token ) ) {
				Logger::info( 'There is no saved payment token for subscription #' . $subscription->get_id() );
				return $payment_method_to_display;
			}
			return $token->get_display_name();
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get payment method for subscription  #' . $subscription->get_id() . ' ' . $e );
			return $payment_method_to_display;
		}
	}
}
