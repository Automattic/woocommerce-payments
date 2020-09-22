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
use WCPay\Constants\Payment_Initiated_By;

/**
 * Gateway class for WooCommerce Payments, with added compatibility with WooCommerce Subscriptions.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Compat extends WC_Payment_Gateway_WCPay {

	const PAYMENT_METHOD_META_TABLE = 'wc_order_tokens';
	const PAYMENT_METHOD_META_KEY   = 'token';

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
		// Enqueue JS hack when Subscriptions does not provide the meta input filter.
		if ( version_compare( WC_Subscriptions::$version, '3.0.7', '<=' ) ) {
			add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'add_payment_method_select_to_subscription_edit' ] );
		}
	}

	/**
	 * Returns whether this user is changing the payment method for a subscription.
	 *
	 * @return bool
	 */
	private function is_changing_payment_method_for_subscription() {
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wcs_is_subscription( wc_clean( wp_unslash( $_GET['change_payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return false;
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
		$force_save_payment_method = wcs_order_contains_subscription( $order_id ) || $this->is_changing_payment_method_for_subscription();
		return parent::process_payment( $order_id, $force_save_payment_method );
	}

	/**
	 * Returns a boolean value indicating whether the save payment checkbox should be
	 * displayed during checkout.
	 *
	 * Returns `false` if the cart currently has a subscriptions or if the request has a
	 * `change_payment_method` GET parameter. Returns the value in `$display` otherwise.
	 *
	 * @param bool $display Bool indicating whether to show the save payment checkbox in the absence of subscriptions.
	 *
	 * @return bool Indicates whether the save payment method checkbox should be displayed or not.
	 */
	public function display_save_payment_method_checkbox( $display ) {
		if ( WC_Subscriptions_Cart::cart_contains_subscription() || $this->is_changing_payment_method_for_subscription() ) {
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

		$payment_information = new Payment_Information( '', $renewal_order, $token, Payment_Initiated_By::MERCHANT() );

		try {
			// TODO: make `force_saved_card` and adding the 'recurring' metadata 2 distinct features.
			$this->process_payment_for_order( null, $payment_information, true );
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

		$payment_meta[ $this->id ] = [
			self::PAYMENT_METHOD_META_TABLE => [
				self::PAYMENT_METHOD_META_KEY => [
					'label' => __( 'Saved payment method', 'woocommerce-payments' ),
					'value' => empty( $active_token ) ? '' : strval( $active_token->get_id() ),
				],
			],
		];

		// Display select element on newer Subscriptions versions.
		add_action(
			sprintf(
				'woocommerce_subscription_payment_meta_input_%s_%s_%s',
				WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::PAYMENT_METHOD_META_TABLE,
				self::PAYMENT_METHOD_META_KEY
			),
			[ $this, 'render_custom_payment_meta_input' ],
			10,
			3
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param string          $payment_gateway_id The ID of the payment gateway to validate.
	 * @param array           $payment_meta       Associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription       The subscription order.
	 *
	 * @throws Exception When $payment_meta is not valid.
	 */
	public function validate_subscription_payment_meta( $payment_gateway_id, $payment_meta, $subscription ) {
		if ( $this->id !== $payment_gateway_id ) {
			return;
		}

		if ( empty( $payment_meta[ self::PAYMENT_METHOD_META_TABLE ][ self::PAYMENT_METHOD_META_KEY ]['value'] ) ) {
			throw new Exception( __( 'A customer saved payment method was not selected for this order.', 'woocommerce-payments' ) );
		}

		$token = WC_Payment_Tokens::get( $payment_meta[ self::PAYMENT_METHOD_META_TABLE ][ self::PAYMENT_METHOD_META_KEY ]['value'] );

		if ( empty( $token ) ) {
			throw new Exception( __( 'The saved payment method selected is invalid or does not exist.', 'woocommerce-payments' ) );
		}

		if ( $subscription->get_user_id() !== $token->get_user_id() ) {
			throw new Exception( __( 'The saved payment method selected does not belong to this order\'s customer.', 'woocommerce-payments' ) );
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
		if ( self::PAYMENT_METHOD_META_TABLE !== $table || self::PAYMENT_METHOD_META_KEY !== $meta_key ) {
			return;
		}

		$token = WC_Payment_Tokens::get( $meta_value );

		if ( empty( $token ) ) {
			return;
		}

		$this->add_token_to_order( $subscription, $token );
	}

	/**
	 * Loads the subscription edit page script with user cards to hijack the payment method input and
	 * transform it into a select element.
	 *
	 * @param WC_Order $order The WC Order.
	 */
	public function add_payment_method_select_to_subscription_edit( $order ) {
		// Do not load the script if the order is not a subscription.
		if ( ! wcs_is_subscription( $order ) ) {
			return;
		}

		$script_src_url    = plugins_url( 'dist/subscription-edit-page.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/subscription-edit-page.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			'WCPAY_SUBSCRIPTION_EDIT_PAGE',
			$script_src_url,
			$script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/subscription-edit-page.js' ),
			true
		);

		wp_localize_script(
			'WCPAY_SUBSCRIPTION_EDIT_PAGE',
			'wcpaySubscriptionEdit',
			[
				'gateway'           => $this->id,
				'table'             => self::PAYMENT_METHOD_META_TABLE,
				'metaKey'           => self::PAYMENT_METHOD_META_KEY,
				'tokens'            => $this->get_user_formatted_tokens_array( $order->get_user_id() ),
				'defaultOptionText' => __( 'Please select a payment method', 'woocommerce-payments' ),
			]
		);

		wp_enqueue_script( 'WCPAY_SUBSCRIPTION_EDIT_PAGE' );
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

	/**
	 * Outputs a select element to be used for the Subscriptions payment meta token selection.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @param string          $field_id     The field_id to add to the select element.
	 * @param string          $field_value  The field_value to be selected by default.
	 */
	public function render_custom_payment_meta_input( $subscription, $field_id, $field_value ) {
		$tokens         = $this->get_user_formatted_tokens_array( $subscription->get_user_id() );
		$is_valid_value = false;

		foreach ( $tokens as $token ) {
			$is_valid_value = $is_valid_value || intval( $field_value ) === $token['tokenId'];
		}

		echo '<select name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '">';
		// If no token matches the selected ID, add a default option.
		if ( ! $is_valid_value ) {
			echo '<option value="" selected disabled>' . esc_html__( 'Please select a payment method', 'woocommerce-payments' ) . '</option>';
		}
		foreach ( $tokens as $token ) {
			$is_selected = intval( $field_value ) === $token['tokenId'] ? 'selected' : '';
			echo '<option value="' . esc_attr( $token['tokenId'] ) . '" ' . esc_attr( $is_selected ) . '>' . esc_html( $token['displayName'] ) . '</option>';
		}
		echo '</select>';
	}
}
