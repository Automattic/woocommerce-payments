<?php
/**
 * Trait WC_Payment_Gateway_WCPay_Subscriptions_Trait
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\API_Merchant_Exception;
use WCPay\Exceptions\Invalid_Payment_Method_Exception;
use WCPay\Exceptions\Add_Payment_Method_Exception;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\PaymentMethods\Configs\Definitions\AmazonPayDefinition;

/**
 * Gateway class for WooPayments, with added compatibility with WooCommerce Subscriptions.
 */
trait WC_Payment_Gateway_WCPay_Subscriptions_Trait {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Retrieve payment token from a subscription or order.
	 *
	 * @param WC_Order $order Order or subscription object.
	 *
	 * @return null|WC_Payment_Token Last token associated with order or subscription.
	 */
	abstract protected function get_payment_token( $order );

	/**
	 * Process the payment for a given order.
	 *
	 * @param WC_Cart|null              $cart Cart.
	 * @param WCPay\Payment_Information $payment_information Payment info.
	 * @param bool                      $scheduled_subscription_payment Used to determinate is scheduled subscription payment to add more fields into API request.
	 *
	 * @return array|null                   An array with result of payment and redirect URL, or nothing.
	 * @throws API_Exception                Error processing the payment.
	 * @throws Add_Payment_Method_Exception When $0 order processing failed.
	 */
	abstract public function process_payment_for_order( $cart, $payment_information, $scheduled_subscription_payment = false );


	/**
	 * Get the payment method to use for the intent.
	 *
	 * @return string The payment method to use for the intent (e.g. 'card')
	 */
	abstract public function get_payment_method_to_use_for_intent();

	/**
	 * Saves the payment token to the order.
	 *
	 * @param WC_Order         $order The order.
	 * @param WC_Payment_Token $token The token to save.
	 */
	abstract public function add_token_to_order( $order, $token );

	/**
	 * Returns a formatted token list for a user.
	 *
	 * @param int         $user_id    The user ID.
	 * @param string|null $gateway_id Optional gateway ID to filter tokens. Defaults to card gateway.
	 */
	abstract protected function get_user_formatted_tokens_array( $user_id, $gateway_id = null );

	/**
	 * Prepares the payment information object.
	 *
	 * @param WC_Order $order The order whose payment will be processed.
	 * @return Payment_Information An object, which describes the payment.
	 */
	abstract protected function prepare_payment_information( $order );

	/**
	 * Stores the payment method meta table name
	 *
	 * @var string
	 */
	private static $payment_method_meta_table = 'wc_order_tokens';

	/**
	 * Stores the payment method meta key name
	 *
	 * @var string
	 */
	private static $payment_method_meta_key = 'token';

	/**
	 * Stores a flag to indicate if the subscription integration hooks have been attached.
	 *
	 * The callbacks attached as part of maybe_init_subscriptions() only need to be attached once to avoid duplication.
	 *
	 * @var bool False by default, true once the callbacks have been attached.
	 */
	private static $has_attached_integration_hooks = false;

	/**
	 * Get the list of WCPay gateway IDs that support reusable payment methods for subscriptions.
	 * These gateways can be charged for recurring payments.
	 *
	 * @return array List of reusable gateway IDs.
	 */
	private function get_reusable_wcpay_gateway_ids() {
		return [
			WC_Payment_Gateway_WCPay::GATEWAY_ID, // Card gateway.
			WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . AmazonPayDefinition::get_id(),
		];
	}

	/**
	 * Check if a gateway ID is a reusable WCPay gateway that supports subscription renewals.
	 *
	 * @param string $gateway_id The gateway ID to check.
	 * @return bool True if the gateway is reusable.
	 */
	private function is_reusable_wcpay_gateway( $gateway_id ) {
		return in_array( $gateway_id, $this->get_reusable_wcpay_gateway_ids(), true );
	}

	/**
	 * Get a descriptive payment method title from a token.
	 *
	 * For credit cards, returns "{title} ending in {last4}".
	 * For Amazon Pay, returns "{title} ({redacted_email})".
	 * For Stripe Link, returns "{title} ({redacted_email})".
	 * For other tokens, returns the default title.
	 *
	 * @param WC_Payment_Token|null $token   The payment token.
	 * @param string                $default The default title to return if token cannot be processed.
	 * @return string The payment method title with identifying details.
	 */
	private function get_payment_method_title_from_token( $token, $default ) {
		if ( ! $token ) {
			return $default;
		}

		if ( $token instanceof WC_Payment_Token_CC ) {
			$last4 = $token->get_last4();
			// Avoid duplication if the title already contains the last4.
			if ( ! empty( $last4 ) && false === strpos( $default, $last4 ) ) {
				// Use the specific card brand (e.g. "Visa") when available instead of $default,
				// which may refer to a different payment method type (e.g. "Link" from a previous subscription payment).
				$card_type = $token->get_card_type();
				$title     = ! empty( $card_type ) ? wc_get_credit_card_type_label( $card_type ) : $default;
				// translators: 1: payment method likely credit card, 2: last 4 digit.
				return sprintf( __( '%1$s ending in %2$s', 'woocommerce-payments' ), $title, $last4 );
			}
		}

		if ( $token instanceof WC_Payment_Token_WCPay_Amazon_Pay ) {
			$email = $token->get_email();
			// Avoid duplication if the title already contains the email.
			if ( ! empty( $email ) && false === strpos( $default, $email ) ) {
				// translators: 1: payment method (Amazon Pay), 2: redacted customer email.
				return sprintf( __( '%1$s (%2$s)', 'woocommerce-payments' ), $default, $email );
			}
		}

		if ( $token instanceof WC_Payment_Token_WCPay_Link ) {
			$email = $token->get_redacted_email();
			// Avoid duplication if the title already contains the email.
			if ( ! empty( $email ) && false === strpos( $default, $email ) ) {
				// Link uses the card gateway, so $default is "Card". Use "Stripe Link" instead.
				// translators: 1: payment method (Stripe Link), 2: redacted customer email.
				return sprintf( __( '%1$s (%2$s)', 'woocommerce-payments' ), __( 'Stripe Link', 'woocommerce-payments' ), $email );
			}
		}

		return $default;
	}

	/**
	 * Check if a subscription or order belongs to this specific gateway or a related WCPay gateway
	 * that this gateway should handle (e.g., card gateway handling Amazon Pay display).
	 *
	 * @param WC_Order|WC_Subscription $order The order or subscription to check.
	 * @return bool True if this gateway should handle the order.
	 */
	private function should_handle_order( $order ) {
		$payment_method = $order->get_payment_method();

		// Direct match - order uses this gateway.
		if ( $payment_method === $this->id ) {
			return true;
		}

		// Subscriptions' hooks are only registered to the base card gateway.
		// The main gateway should be used for all reusable payment methods.
		if ( in_array( $payment_method, $this->get_reusable_wcpay_gateway_ids(), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize subscription support and hooks.
	 */
	public function maybe_init_subscriptions() {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		/*
		 * Base set of subscription features to add.
		 * The WCPay payment gateway supports these features
		 * for both WCPay Subscriptions and WooCommerce Subscriptions.
		 */
		$payment_gateway_features = [
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change',
			'subscription_reactivation',
			'subscription_suspension',
			'subscriptions',
		];

		if ( ! WC_Payments_Features::should_use_stripe_billing() ) {
			/*
			 * Subscription amount & date changes are only supported
			 * when Stripe Billing is not in use.
			 */
			$payment_gateway_features = array_merge(
				$payment_gateway_features,
				[
					'subscription_amount_changes',
					'subscription_date_changes',
				]
			);
		} else {
			/*
			 * The gateway_scheduled_payments feature is only supported
			 * for WCPay Subscriptions.
			 */
			$payment_gateway_features[] = 'gateway_scheduled_payments';
		}

		$this->supports = array_merge( $this->supports, $payment_gateway_features );
	}

	/**
	 * Initializes this trait's WP hooks.
	 *
	 * Generic hooks are registered once by the base gateway.
	 * Gateway-specific hooks (for renewals) are registered by each reusable gateway.
	 * Non-reusable gateways (iDEAL, Bancontact, etc.) do not register subscription hooks.
	 *
	 * @return void
	 */
	public function maybe_init_subscriptions_hooks() {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		if ( self::$has_attached_integration_hooks ) {
			return;
		}

		// Only the base gateway registers hooks to avoid duplication.
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $this->id ) {
			return;
		}

		self::$has_attached_integration_hooks = true;

		add_filter( 'woocommerce_email_classes', [ $this, 'add_emails' ], 20 );

		// Switch Amazon Pay ECE subscriptions to the correct gateway (priority 10, before manual renewal check).
		add_action( 'woocommerce_checkout_subscription_created', [ $this, 'maybe_switch_subscription_to_amazon_pay_gateway' ], 10, 1 );
		// Force non-reusable payment methods to manual renewal (priority 11, after gateway switch).
		add_action( 'woocommerce_checkout_subscription_created', [ $this, 'maybe_force_subscription_to_manual' ], 11, 1 );

		// Register gateway-specific hooks for all reusable gateways.
		foreach ( $this->get_reusable_wcpay_gateway_ids() as $gateway_id ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $gateway_id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $gateway_id, [ $this, 'update_failing_payment_method' ], 10, 2 );
		}

		add_filter( 'wc_payments_display_save_payment_method_checkbox', [ $this, 'display_save_payment_method_checkbox' ], 10 );

		// Display the payment method used for a subscription in the "My Subscriptions" table.
		add_filter( 'woocommerce_my_subscriptions_payment_method', [ $this, 'maybe_render_subscription_payment_method' ], 10, 2 );

		// Override the payment method display for Link subscriptions in all contexts (admin + customer).
		// The gateway title is "Card" for Link subscriptions because Link uses the card gateway.
		add_filter( 'woocommerce_subscription_payment_method_to_display', [ $this, 'maybe_override_link_subscription_payment_method_display' ], 10, 2 );

		// Hide "Change payment" button for manual subscriptions with non-reusable payment methods.
		add_filter( 'wcs_view_subscription_actions', [ $this, 'maybe_hide_change_payment_for_manual_subscriptions' ], 10, 2 );

		// Hide "Auto-renew" toggle for manual subscriptions with non-reusable payment methods.
		add_filter( 'user_has_cap', [ $this, 'maybe_hide_auto_renew_toggle_for_manual_subscriptions' ], 100, 3 );

		// Used to filter out unwanted metadata on new renewal orders.
		if ( ! class_exists( 'WC_Subscriptions_Data_Copier' ) ) {
			add_filter( 'wcs_renewal_order_meta_query', [ $this, 'update_renewal_meta_data' ], 10, 3 );
		} else {
			add_filter( 'wc_subscriptions_renewal_order_data', [ $this, 'remove_data_renewal_order' ], 10, 3 );
		}

		// Allow store managers to manually set Stripe as the payment method on a subscription.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );
		add_filter( 'woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 3 );
		add_action( 'wcs_save_other_payment_meta', [ $this, 'save_meta_in_order_tokens' ], 10, 4 );

		// To make sure payment meta is copied from subscription to order.
		add_filter( 'wcs_copy_payment_meta_to_order', [ $this, 'append_payment_meta' ], 10, 3 );

		add_filter( 'woocommerce_subscription_note_old_payment_method_title', [ $this, 'get_specific_old_payment_method_title' ], 10, 3 );
		add_filter( 'woocommerce_subscription_note_new_payment_method_title', [ $this, 'get_specific_new_payment_method_title' ], 10, 3 );

		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'add_payment_method_select_to_subscription_edit' ] );

		// Update subscriptions token when user sets a default payment method.
		add_filter( 'woocommerce_subscriptions_update_subscription_token', [ $this, 'update_subscription_token' ], 10, 3 );
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', [ $this, 'update_payment_method_for_subscriptions' ], 10, 3 );

		// AJAX handler for fetching payment tokens when customer changes.
		add_action( 'wp_ajax_wcpay_get_user_payment_tokens', [ $this, 'ajax_get_user_payment_tokens' ] );
	}

	/**
	 * Stops WC Subscriptions from updating the payment method for subscriptions.
	 *
	 * @param bool            $update_payment_method Whether to update the payment method.
	 * @param string          $new_payment_method The new payment method.
	 * @param WC_Subscription $subscription The subscription.
	 * @return bool
	 */
	public function update_payment_method_for_subscriptions( $update_payment_method, $new_payment_method, $subscription ) {
		// Skip if the change payment method request was not made yet.
		if ( ! isset( $_POST['_wcsnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wcsnonce'] ), 'wcs_change_payment_method' ) ) {
			return $update_payment_method;
		}

		// Avoid interfering with use-cases not related to updating payment method for subscriptions.
		if ( ! $this->is_changing_payment_method_for_subscription() ) {
			return $update_payment_method;
		}

		// Avoid interfering with other payment gateways' operations.
		if ( $new_payment_method !== $this->id ) {
			return $update_payment_method;
		}

		// If the payment method is a saved payment method, we don't need to stop WC Subscriptions from updating it.
		if ( ( isset( $_POST[ 'wc-' . $new_payment_method . '-payment-token' ] ) && 'new' !== $_POST[ 'wc-' . $new_payment_method . '-payment-token' ] ) ) {
			return $update_payment_method;
		}

		return false;
	}

	/**
	 * Prepares the payment information object.
	 *
	 * @param Payment_Information $payment_information The payment information from parent gateway.
	 * @param int                 $order_id The order ID whose payment will be processed.
	 * @return Payment_Information An object, which describes the payment.
	 */
	protected function maybe_prepare_subscription_payment_information( $payment_information, $order_id ) {
		if ( ! $this->is_payment_recurring( $order_id ) ) {
			return $payment_information;
		}

		// Subs-specific behavior starts here.
		$payment_information->set_payment_type( Payment_Type::RECURRING() );
		// The payment method is always saved for subscriptions.
		$payment_information->must_save_payment_method_to_store();
		$payment_information->set_is_changing_payment_method_for_subscription( $this->is_changing_payment_method_for_subscription() );

		return $payment_information;
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float    $amount The amount to charge.
	 * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount, $renewal_order ) {

		// Exit early if the order belongs to a WCPay Subscription. The payment will be processed by the subscription via webhooks.
		if ( $this->is_wcpay_subscription_renewal_order( $renewal_order ) ) {
			return;
		}

		$token = $this->get_payment_token( $renewal_order );
		if ( is_null( $token ) && ! WC_Payments::is_network_saved_cards_enabled() ) {
			$renewal_order->add_order_note( 'Subscription renewal failed: No saved payment method found.' );
			Logger::error( 'There is no saved payment token for order #' . $renewal_order->get_id() );
			// TODO: Update to use Order_Service->mark_payment_failed.
			$renewal_order->update_status( 'failed' );
			return;
		}

		$customer_id = $this->order_service->get_customer_id_for_order( $renewal_order );

		try {
			$payment_information = new Payment_Information( '', $renewal_order, Payment_Type::RECURRING(), $token, Payment_Initiated_By::MERCHANT(), null, null, '', $this->get_payment_method_to_use_for_intent(), $customer_id );
			$this->process_payment_for_order( null, $payment_information, true );
		} catch ( API_Exception $e ) {
			Logger::error( 'Error processing subscription renewal: ' . $e->getMessage() );
			// TODO: Update to use Order_Service->mark_payment_failed.
			$renewal_order->update_status( 'failed' );

			if ( ! empty( $payment_information ) ) {
				$error_details = esc_html( rtrim( $e->getMessage(), '.' ) );
				if ( $e instanceof API_Merchant_Exception ) {
					$error_details = $error_details . '. ' . esc_html( rtrim( $e->get_merchant_message(), '.' ) );
				}

				$note = sprintf(
					WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the failed payment amount, %2: error message  */
						__(
							'A payment of %1$s <strong>failed</strong> to complete with the following message: <code>%2$s</code>.',
							'woocommerce-payments'
						),
						[
							'strong' => '<strong>',
							'code'   => '<code>',
						]
					),
					WC_Payments_Explicit_Price_Formatter::get_explicit_price(
						wc_price( $amount, [ 'currency' => WC_Payments_Utils::get_order_intent_currency( $renewal_order ) ] ),
						$renewal_order
					),
					$error_details
				);
				$renewal_order->add_order_note( $note );
			}
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
			$renewal_order->add_order_note( 'Unable to update subscription payment method: No valid payment token or method found.' );
			Logger::error( 'Failing subscription could not be updated: there is no saved payment token for order #' . $renewal_order->get_id() );
			return;
		}
		$this->add_token_to_order( $subscription, $renewal_token );
	}

	/**
	 * Return the payment meta data for this payment gateway.
	 *
	 * @param WC_Subscription $subscription The subscription order.
	 * @return array
	 */
	private function get_payment_meta( $subscription ) {
		$active_token = $this->get_payment_token( $subscription );

		return [
			self::$payment_method_meta_table => [
				self::$payment_method_meta_key => [
					'label' => __( 'Saved payment method', 'woocommerce-payments' ),
					'value' => empty( $active_token ) ? '' : (string) $active_token->get_id(),
				],
			],
		];
	}

	/**
	 * Append payment meta if order and subscription are using WCPay as payment method and if passed payment meta is an array.
	 *
	 * @param array           $payment_meta Associative array of meta data required for automatic payments.
	 * @param WC_Order        $order        The subscription's related order.
	 * @param WC_Subscription $subscription The subscription order.
	 * @return array
	 */
	public function append_payment_meta( $payment_meta, $order, $subscription ) {
		// Check if both order and subscription should be handled by this gateway.
		if ( ! $this->should_handle_order( $order ) || ! $this->should_handle_order( $subscription ) ) {
			return $payment_meta;
		}

		if ( ! is_array( $payment_meta ) ) {
			return $payment_meta;
		}

		return array_merge( $payment_meta, $this->get_payment_meta( $subscription ) );
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
		// Add payment meta for all reusable WCPay gateways (card, Amazon Pay).
		foreach ( $this->get_reusable_wcpay_gateway_ids() as $gateway_id ) {
			$payment_meta[ $gateway_id ] = $this->get_payment_meta( $subscription );

			// Display select element on newer Subscriptions versions.
			add_action(
				sprintf(
					'woocommerce_subscription_payment_meta_input_%s_%s_%s',
					$gateway_id,
					self::$payment_method_meta_table,
					self::$payment_method_meta_key
				),
				[ $this, 'render_custom_payment_meta_input' ],
				10,
				3
			);
		}

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
	 * @throws Invalid_Payment_Method_Exception When $payment_meta is not valid.
	 */
	public function validate_subscription_payment_meta( $payment_gateway_id, $payment_meta, $subscription ) {
		// Validate for all reusable WCPay gateways (card, Amazon Pay).
		if ( ! $this->is_reusable_wcpay_gateway( $payment_gateway_id ) ) {
			return;
		}

		if ( empty( $payment_meta[ self::$payment_method_meta_table ][ self::$payment_method_meta_key ]['value'] ) ) {
			throw new Invalid_Payment_Method_Exception(
				__( 'A customer saved payment method was not selected for this order.', 'woocommerce-payments' ),
				'payment_method_not_selected'
			);
		}

		$token = WC_Payment_Tokens::get( $payment_meta[ self::$payment_method_meta_table ][ self::$payment_method_meta_key ]['value'] );

		if ( empty( $token ) ) {
			throw new Invalid_Payment_Method_Exception(
				__( 'The saved payment method selected is invalid or does not exist.', 'woocommerce-payments' ),
				'payment_method_token_not_found'
			);
		}

		if ( $subscription->get_user_id() !== $token->get_user_id() ) {
			throw new Invalid_Payment_Method_Exception(
				__( 'The saved payment method selected does not belong to this order\'s customer.', 'woocommerce-payments' ),
				'payment_method_token_not_owned'
			);
		}
	}

	/**
	 * Saves the payment token to the order.
	 *
	 * @param WC_Order         $order The order.
	 * @param WC_Payment_Token $token The token to save.
	 */
	public function maybe_add_token_to_subscription_order( $order, $token ) {
		if ( $this->is_subscriptions_enabled() ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
			if ( is_array( $subscriptions ) ) {
				foreach ( $subscriptions as $subscription ) {
					$payment_token = $this->get_payment_token( $subscription );
					if ( is_null( $payment_token ) || $token->get_id() !== $payment_token->get_id() ) {
						$subscription->add_payment_token( $token );
					}
				}
			}
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
		if ( self::$payment_method_meta_table !== $table || self::$payment_method_meta_key !== $meta_key ) {
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

		WC_Payments::register_script_with_dependencies( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'dist/subscription-edit-page' );

		wp_set_script_translations( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_SUBSCRIPTION_EDIT_PAGE' );
	}

	/**
	 * Override the payment method display for Link subscriptions in all contexts.
	 *
	 * Stripe Link uses the card gateway (woocommerce_payments), so the gateway title is "Card".
	 * This replaces it with the Link token's display name (e.g. "Stripe Link (r***@r***.com)").
	 *
	 * @param string          $payment_method_to_display Default payment method to display.
	 * @param WC_Subscription $subscription              Subscription object.
	 *
	 * @return string Payment method string to display.
	 */
	public function maybe_override_link_subscription_payment_method_display( $payment_method_to_display, $subscription ) {
		try {
			if ( ! $this->should_handle_order( $subscription ) ) {
				return $payment_method_to_display;
			}

			$token = $this->get_payment_token( $subscription );
			if ( $token instanceof \WC_Payment_Token_WCPay_Link ) {
				return $token->get_display_name();
			}

			return $payment_method_to_display;
		} catch ( \Exception $e ) {
			return $payment_method_to_display;
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
			// Use should_handle_order() to check if this gateway should render the payment method.
			// This allows the base gateway to render payment methods for Amazon Pay subscriptions too.
			if ( ! $this->should_handle_order( $subscription ) ) {
				return $payment_method_to_display;
			}

			$token = $this->get_payment_token( $subscription );

			if ( is_null( $token ) ) {
				Logger::info( 'There is no saved payment token for subscription #' . $subscription->get_id() );
			} else {
				$payment_method_to_display = $token->get_display_name();
			}

			return $payment_method_to_display;
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get payment method for subscription  #' . $subscription->get_id() . ' ' . $e );
			return $payment_method_to_display;
		}
	}

	/**
	 * Hide "Change payment" button for manual subscriptions with non-reusable payment methods.
	 * These subscriptions use the "Renew now" flow where customers choose a payment method at renewal time.
	 *
	 * @param array           $actions      The subscription actions.
	 * @param WC_Subscription $subscription The subscription object.
	 * @return array The modified actions array.
	 */
	public function maybe_hide_change_payment_for_manual_subscriptions( $actions, $subscription ) {
		// Only process manual subscriptions with non-reusable payment methods.
		$original_payment_method_id = $subscription->get_meta( '_wcpay_original_payment_method_id', true );

		if ( $subscription->is_manual() && ! empty( $original_payment_method_id ) ) {
			// Remove the "Change payment" action since they'll choose payment method during renewal.
			unset( $actions['change_payment_method'] );
		}

		return $actions;
	}

	/**
	 * Hide "Auto renew" toggle for manual subscriptions with non-reusable payment methods.
	 *
	 * @param array $allcaps List of user capabilities.
	 * @param array $caps    Which capabilities are being checked.
	 * @param array $args    Arguments, in our case user ID and subscription ID.
	 * @return array
	 */
	public function maybe_hide_auto_renew_toggle_for_manual_subscriptions( $allcaps, $caps, $args ) {
		if ( ! isset( $caps[0] ) || 'toggle_shop_subscription_auto_renewal' !== $caps[0] ) {
			// Do not interfere with other capabilities.
			return $allcaps;
		}

		if ( ! isset( $args[2] ) ) {
			return $allcaps;
		}
		$subscription = wcs_get_subscription( $args[2] );
		if ( ! $subscription ) {
			return $allcaps;
		}
		// Only process manual subscriptions with non-reusable payment methods.
		$original_payment_method_id = $subscription->get_meta( '_wcpay_original_payment_method_id', true );

		if ( $subscription->is_manual() && ! empty( $original_payment_method_id ) ) {
			// Remove the capability as this subscription won't work with automatic renewals.
			unset( $allcaps['toggle_shop_subscription_auto_renewal'] );
		}

		return $allcaps;
	}

	/**
	 * AJAX handler to fetch payment tokens for a user.
	 *
	 * @return void
	 */
	public function ajax_get_user_payment_tokens() {
		check_ajax_referer( 'wcpay-subscription-edit', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'woocommerce-payments' ) ], 403 );
			return;
		}

		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway_id'] ) ) : null;

		if ( $user_id <= 0 ) {
			wp_send_json_success( [ 'tokens' => [] ] );
			return;
		}

		// Verify user exists.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user ID.', 'woocommerce-payments' ) ], 400 );
			return;
		}

		// Validating the gateway_id - only allow reusable WCPay gateways.
		if ( null !== $gateway_id && ! $this->is_reusable_wcpay_gateway( $gateway_id ) ) {
			$gateway_id = null; // Fall back to the default card gateway.
		}

		$tokens = $this->get_user_formatted_tokens_array( $user_id, $gateway_id );
		wp_send_json_success( [ 'tokens' => $tokens ] );
	}

	/**
	 * Outputs a select element to be used for the Subscriptions payment meta token selection.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @param string          $field_id     The field_id to add to the select element.
	 * @param string          $field_value  The field_value to be selected by default.
	 */
	public function render_custom_payment_meta_input( $subscription, $field_id, $field_value ) {
		// Make sure that we are either working with integers or null.
		$field_value = ctype_digit( $field_value )
			? absint( $field_value )
			: (
				is_int( $field_value )
					? $field_value
					: null
			);

		$user_id = $subscription->get_user_id();

		// Extract the gateway ID from the field_id which follows the pattern:
		// _payment_method_meta[{gateway_id}][{table}][{key}]
		// This ensures we show tokens for the gateway being rendered, not the subscription's current gateway.
		$token_gateway_id = WC_Payment_Gateway_WCPay::GATEWAY_ID; // Default to card.
		if ( preg_match( '/\[([^\]]+)\]/', $field_id, $matches ) ) {
			$extracted_gateway_id = $matches[1];
			if ( $this->is_reusable_wcpay_gateway( $extracted_gateway_id ) ) {
				$token_gateway_id = $extracted_gateway_id;
			}
		}

		$disabled      = false;
		$selected      = null;
		$options       = [];
		$prepared_data = [
			'value'     => $field_value,
			'userId'    => $user_id,
			'tokens'    => [],
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wcpay-subscription-edit' ),
			'gatewayId' => $token_gateway_id,
		];

		if ( $user_id > 0 ) {
			$tokens = $this->get_user_formatted_tokens_array( $user_id, $token_gateway_id );
			foreach ( $tokens as $token ) {
				$options[ $token['tokenId'] ] = $token['displayName'];
				if ( $field_value === $token['tokenId'] || ( ! $field_value && $token['isDefault'] ) ) {
					$selected = $token['tokenId'];
				}
			}

			$prepared_data['tokens'] = $tokens;

			if ( empty( $options ) ) {
				$options[0] = __( 'No payment methods found for customer', 'woocommerce-payments' );
				$disabled   = true;
			}
		} else {
			$options[0] = __( 'Please select a customer first', 'woocommerce-payments' );
			$selected   = 0;
			$disabled   = true;
		}
		?>
		<span class="wcpay-subscription-payment-method" data-wcpay-pm-selector="<?php echo esc_attr( wp_json_encode( $prepared_data ) ); ?>">
			<select name="<?php echo esc_attr( $field_id ); ?>" id="<?php echo esc_attr( $field_id ); ?>">
				<?php if ( $field_value && $field_value !== $selected ) : ?>
					<option value="" selected disabled><?php echo esc_html__( 'Please select a payment method', 'woocommerce-payments' ); ?></option>
				<?php endif; ?>
				<?php foreach ( $options as $token_id => $display_name ) : ?>
					<option value="<?php echo esc_attr( $token_id ); ?>" <?php selected( $token_id, $selected ); ?> <?php echo disabled( $disabled ); ?>>
						<?php echo esc_html( $display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</span>
		<?php
	}

	/**
	 * Add specific data like last 4 digit of wcpay payment gateway
	 *
	 * @param string          $old_payment_method_title Payment method title, eg: Credit card.
	 * @param string          $old_payment_method Payment gateway id.
	 * @param WC_Subscription $subscription The subscription order.
	 * @return string
	 */
	public function get_specific_old_payment_method_title( $old_payment_method_title, $old_payment_method, $subscription ) {
		// make sure payment method is wcpay's.
		if ( 0 !== strpos( $old_payment_method, WC_Payment_Gateway_WCPay::GATEWAY_ID ) ) {
			return $old_payment_method_title;
		}

		if ( $this->is_changing_payment_method_for_subscription() ) {
			$token_ids = $subscription->get_payment_tokens();
			// since old payment must be the second to last saved payment...
			if ( count( $token_ids ) < 2 ) {
				return $old_payment_method_title;
			}

			$second_to_last_token_id = $token_ids[ count( $token_ids ) - 2 ];
			$token                   = WC_Payment_Tokens::get( $second_to_last_token_id );

			return $this->get_payment_method_title_from_token( $token, $old_payment_method_title );
		} else {
			$last_order_id = $subscription->get_last_order();
			if ( ! $last_order_id ) {
				return $old_payment_method_title;
			}

			$last_order = wc_get_order( $last_order_id );
			$token_ids  = $last_order->get_payment_tokens();
			// since old payment must be the second to last saved payment...
			if ( count( $token_ids ) < 2 ) {
				return $old_payment_method_title;
			}

			$second_to_last_token_id = $token_ids[ count( $token_ids ) - 2 ];
			$token                   = WC_Payment_Tokens::get( $second_to_last_token_id );

			return $this->get_payment_method_title_from_token( $token, $old_payment_method_title );
		}
	}

	/**
	 * Add specific data like last 4 digit of wcpay payment gateway
	 *
	 * @param string          $new_payment_method_title Payment method title, eg: Credit card.
	 * @param string          $new_payment_method Payment gateway id.
	 * @param WC_Subscription $subscription The subscription order.
	 * @return string
	 */
	public function get_specific_new_payment_method_title( $new_payment_method_title, $new_payment_method, $subscription ) {
		// make sure payment method is wcpay's (including split gateways like Amazon Pay).
		if ( 0 !== strpos( $new_payment_method, WC_Payment_Gateway_WCPay::GATEWAY_ID ) ) {
			return $new_payment_method_title;
		}

		if ( $this->is_changing_payment_method_for_subscription() ) {
			$order = $subscription;
		} else {
			$last_order_id = $subscription->get_last_order();
			if ( ! $last_order_id ) {
				return $new_payment_method_title;
			}
			$order = wc_get_order( $last_order_id );
		}

		try {
			$payment_information = $this->prepare_payment_information( $order );
		} catch ( Exception $e ) {
			return $new_payment_method_title;
		}

		if ( $payment_information->is_using_saved_payment_method() ) {
			$token = $payment_information->get_payment_token();

			return $this->get_payment_method_title_from_token( $token, $new_payment_method_title );
		} else {
			try {
				$payment_method_id = $payment_information->get_payment_method();
				$payment_method    = $this->payments_api_client->get_payment_method( $payment_method_id );
				if ( ! empty( $payment_method['card']['last4'] ) ) {
					// translators: 1: payment method likely credit card, 2: last 4 digit.
					return sprintf( __( '%1$s ending in %2$s', 'woocommerce-payments' ), $new_payment_method_title, $payment_method['card']['last4'] );
				}
				if ( ! empty( $payment_method['amazon_pay']['email'] ) ) {
					// translators: 1: payment method (Amazon Pay), 2: redacted customer email.
					return sprintf( __( '%1$s (%2$s)', 'woocommerce-payments' ), $new_payment_method_title, $payment_method['amazon_pay']['email'] );
				}
				if ( ! empty( $payment_method['link']['email'] ) ) {
					// Link uses the card gateway, so $new_payment_method_title is "Card". Use "Stripe Link" instead.
					// translators: 1: payment method (Stripe Link), 2: customer email.
					return sprintf( __( '%1$s (%2$s)', 'woocommerce-payments' ), __( 'Stripe Link', 'woocommerce-payments' ), $payment_method['link']['email'] );
				}
			} catch ( Exception $e ) {
				Logger::error( $e );
			}
		}

		return $new_payment_method_title;
	}

	/**
	 * When an order is created/updated, we want to add an ActionScheduler job to send this data to
	 * the payment server.
	 *
	 * @param int           $order_id  The ID of the order that has been created.
	 * @param WC_Order|null $order     The order that has been created.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function maybe_schedule_subscription_order_tracking( $order_id, $order = null ) {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$save_meta_data = false;

		if ( is_null( $order ) ) {
			$order = wc_get_order( $order_id );
		}

		$payment_token = $this->get_payment_token( $order );

		// If we can't get the payment token for this order, then we check if we already have a payment token
		// set in the order metadata. If we don't, then we try and get the parent order's token from the metadata.
		if ( is_null( $payment_token ) ) {
			if ( empty( $this->order_service->get_payment_method_id_for_order( $order ) ) ) {
				$parent_order = wc_get_order( $order->get_parent_id() );
				if ( $parent_order ) {
					$parent_payment_method_id = $this->order_service->get_payment_method_id_for_order( $parent_order );
				}
				// If there is no parent order, or the parent order doesn't have the metadata set, then we cannot track this order.
				if ( empty( $parent_order ) || empty( $parent_payment_method_id ) ) {
					return;
				}

				$this->order_service->set_payment_method_id_for_order( $order, $parent_payment_method_id );
				$save_meta_data = true;
			}
		} elseif ( $this->order_service->get_payment_method_id_for_order( $order ) !== $payment_token->get_token() ) {
			// If the payment token stored in the metadata already doesn't reflect the latest token, update it.
			$this->order_service->set_payment_method_id_for_order( $order, $payment_token->get_token() );
			$save_meta_data = true;
		}

		// If the stripe customer ID metadata isn't set for this order, try and get this data from the metadata of the parent order.
		if ( empty( $this->order_service->get_customer_id_for_order( $order ) ) ) {
			$parent_order = wc_get_order( $order->get_parent_id() );
			if ( $parent_order ) {
				$parent_customer_id = $this->order_service->get_customer_id_for_order( $parent_order );
			}
			if ( ! empty( $parent_order ) && ! empty( $parent_customer_id ) ) {
				$this->order_service->set_customer_id_for_order( $order, $parent_customer_id );
				$save_meta_data = true;
			}
		}

		// If we need to, save our changes to the metadata for this order.
		if ( $save_meta_data ) {
			$order->save_meta_data();
		}
	}

	/**
	 * Action called when a renewal order is created, allowing us to strip metadata that we do not
	 * want it to inherit from the parent order.
	 *
	 * @param string $order_meta_query The metadata query (a valid SQL query).
	 * @param int    $to_order         The renewal order.
	 * @param int    $from_order       The source (parent) order.
	 *
	 * @return string
	 */
	public function update_renewal_meta_data( $order_meta_query, $to_order, $from_order ) {
		$order_meta_query .= " AND `meta_key` NOT IN ('_new_order_tracking_complete')";

		return $order_meta_query;
	}

	/**
	 * Removes the data that we don't need to copy to renewal orders.
	 *
	 * @param array $order_data Renewal order data.
	 *
	 * @return array The renewal order data with the data we don't want copied removed
	 */
	public function remove_data_renewal_order( $order_data ) {
		unset( $order_data['_new_order_tracking_complete'] );
		return $order_data;
	}

	/**
	 * Adds the failed SCA auth email to WooCommerce.
	 *
	 * @param WC_Email[] $email_classes All existing emails.
	 * @return WC_Email[]
	 */
	public function add_emails( $email_classes ) {
		include_once __DIR__ . '/class-wc-payments-email-failed-renewal-authentication.php';
		include_once __DIR__ . '/class-wc-payments-email-failed-authentication-retry.php';
		$email_classes['WC_Payments_Email_Failed_Renewal_Authentication'] = new WC_Payments_Email_Failed_Renewal_Authentication( $email_classes );
		$email_classes['WC_Payments_Email_Failed_Authentication_Retry']   = new WC_Payments_Email_Failed_Authentication_Retry();
		return $email_classes;
	}

	/**
	 * Update the specified subscription's payment token with a new token.
	 *
	 * @param bool             $updated      Whether the token was updated.
	 * @param WC_Subscription  $subscription The subscription whose payment token need to be updated.
	 * @param WC_Payment_Token $new_token    The new payment token to be used for the specified subscription.
	 *
	 * @return bool Whether this function updates the token or not.
	 */
	public function update_subscription_token( $updated, $subscription, $new_token ) {
		$token_gateway_id = $new_token->get_gateway_id();

		// Check if the token belongs to a reusable WCPay gateway.
		// Only the base gateway processes this hook, so we handle all reusable gateway tokens.
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $this->id || ! $this->is_reusable_wcpay_gateway( $token_gateway_id ) ) {
			return $updated;
		}

		// Set the subscription payment method to match the token's gateway.
		$subscription->set_payment_method( $token_gateway_id );
		$subscription->update_meta_data( '_payment_method_id', $new_token->get_token() );
		$subscription->add_payment_token( $new_token );
		$subscription->save();

		return true;
	}

	/**
	 * Checks if a renewal order is linked to a WCPay subscription.
	 *
	 * @param WC_Order $renewal_order The renewal order to check.
	 *
	 * @return bool True if the renewal order is linked to a renewal order. Otherwise false.
	 */
	private function is_wcpay_subscription_renewal_order( WC_Order $renewal_order ) {
		// Renewal orders copy metadata from the parent subscription, so we can first check if it has the `_wcpay_subscription_id` meta.
		if ( ! class_exists( 'WC_Payments_Subscription_Service' ) || ! $renewal_order->meta_exists( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY ) ) {
			return false;
		}

		// Confirm the renewal order is linked to a subscription which is a WCPay Subscription.
		foreach ( wcs_get_subscriptions_for_renewal_order( $renewal_order ) as $subscription ) {
			if ( WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get card mandate parameters for the order payment intent if needed.
	 * Only required for subscriptions creation for cards issued in India.
	 * More details https://wp.me/pc4etw-ky
	 *
	 * @param WC_Order $order The subscription order.
	 * @return array Params to be included or empty array.
	 */
	public function get_mandate_params_for_order( WC_Order $order ): array {
		$result = [];

		if ( ! $this->is_subscriptions_enabled() ) {
			return $result;
		}
		$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
		$subscription  = reset( $subscriptions );

		if ( ! $subscription ) {
			return $result;
		}

		// TEMP Fix – Stripe validates mandate params for cards not
		// issued by Indian banks. Apply them only for INR as Indian banks
		// only support it for now.
		$currency = $order->get_currency();
		if ( 'INR' !== $currency ) {
			return $result;
		}

		// Get total by adding only subscriptions and get rid of any other product or fee.
		$subs_amount = 0.0;
		foreach ( $subscriptions as $sub ) {
			$subs_amount += $sub->get_total();
		}

		$amount = WC_Payments_Utils::prepare_amount( $subs_amount, $order->get_currency() );

		// TEMP Fix – Prevent stale free subscription data to throw
		// an error due amount < 1.
		if ( 0 === $amount ) {
			return $result;
		}

		$result['setup_future_usage']                                = 'off_session';
		$result['payment_method_options']['card']['mandate_options'] = [
			'reference'       => $order->get_id(),
			'amount'          => $amount,
			'amount_type'     => 'fixed',
			'start_date'      => $subscription->get_time( 'date_created' ),
			'interval'        => $subscription->get_billing_period(),
			'interval_count'  => $subscription->get_billing_interval(),
			'supported_types' => [ 'india' ],
		];

		// Multiple subscriptions per order needs:
		// - Set amount type to maximum, to allow renews of any amount under the order total.
		// - Set interval to sporadic, to not follow any specific interval.
		// - Unset interval count, because it doesn't apply anymore.
		if ( 1 < count( $subscriptions ) ) {
			$result['card']['mandate_options']['amount_type'] = 'maximum';
			$result['card']['mandate_options']['interval']    = 'sporadic';
			if ( isset( $result['card']['mandate_options']['interval_count'] ) ) {
				unset( $result['card']['mandate_options']['interval_count'] );
			}
		}

		return $result;
	}

	/**
	 * Add an order note if the renew intent customer notification requires the merchant to authenticate the payment.
	 * The note includes the charge attempt date and let the merchant know the need of an off-session step by the customer.
	 *
	 * @param WC_Order $order The renew order.
	 * @param array    $processing Processing state from Stripe's intent response.
	 * @return void
	 */
	public function maybe_add_customer_notification_note( WC_Order $order, array $processing = [] ) {
		$approval_requested = $processing['card']['customer_notification']['approval_requested'] ?? false;
		$completes_at       = $processing['card']['customer_notification']['completes_at'] ?? null;
		if ( $approval_requested && $completes_at ) {
			$attempt_date = wp_date( get_option( 'date_format', 'F j, Y' ), $completes_at, wp_timezone() );
			$attempt_time = wp_date( get_option( 'time_format', 'g:i a' ), $completes_at, wp_timezone() );

			$note = sprintf(
			/* translators: 1) date in date_format or 'F j, Y'; 2) time in time_format or 'g:i a' */
				__( 'The customer must authorize this payment via a notification sent to them by the bank which issued their card. The authorization must be completed before %1$s at %2$s, when the charge will be attempted.', 'woocommerce-payments' ),
				$attempt_date,
				$attempt_time
			);

			$order->add_order_note( $note );
		}
	}

	/**
	 * Get mandate ID parameter to renewal payment if exists.
	 * Only required for subscriptions renewals for cards issued in India.
	 * More details https://wp.me/pc4etw-ky
	 *
	 * @param WC_Order $renewal_order The subscription renewal order.
	 * @return string Param to be included or empty array.
	 */
	public function get_mandate_param_for_renewal_order( WC_Order $renewal_order ): string {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		$subscription  = reset( $subscriptions );

		if ( ! $subscription ) {
			return '';
		}

		$parent_order = wc_get_order( $subscription->get_parent_id() );

		if ( ! $parent_order ) {
			return '';
		}

		$mandate = $parent_order->get_meta( '_stripe_mandate_id', true );

		if ( empty( $mandate ) ) {
			return '';
		}

		return $mandate;
	}

	/**
	 * Switch subscription to Amazon Pay gateway when created via Express Checkout.
	 *
	 * ECE payments are initially processed by the base gateway, but Amazon Pay subscriptions
	 * need to use the split Amazon Pay gateway for proper renewal handling.
	 *
	 * This runs at priority 9, before maybe_force_subscription_to_manual (priority 10).
	 *
	 * @param WC_Subscription $subscription The subscription being created.
	 */
	public function maybe_switch_subscription_to_amazon_pay_gateway( $subscription ) {
		// Only process subscriptions using the base WCPay gateway.
		$payment_method_id = $subscription->get_payment_method();
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $payment_method_id ) {
			return;
		}

		// Check if this is an Amazon Pay Express Checkout payment.
		$parent_order = $subscription->get_parent();
		if ( ! $parent_order ) {
			return;
		}

		// technically, `$express_checkout_type` could also be `google_pay` or `apple_pay`.
		// But those are card methods, processed through `woocommerce_payments`, not through `woocommerce_payments_google_pay`.
		$express_checkout_type = $parent_order->get_meta( '_wcpay_express_checkout_payment_method' );
		if ( AmazonPayDefinition::get_id() !== $express_checkout_type ) {
			return;
		}

		// Switch to the Amazon Pay split gateway.
		$amazon_pay_gateway_id = WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . AmazonPayDefinition::get_id();
		$subscription->set_payment_method( $amazon_pay_gateway_id );
		$subscription->save();
	}

	/**
	 * Force subscription to manual renewal if non-reusable payment method was used.
	 *
	 * This runs at priority 10, after maybe_switch_subscription_to_amazon_pay_gateway (priority 9).
	 *
	 * @param WC_Subscription $subscription The subscription being created.
	 */
	public function maybe_force_subscription_to_manual( $subscription ) {
		// Only process WCPay subscriptions (including split UPE gateways like woocommerce_payments_ideal).
		$payment_method_id = $subscription->get_payment_method();
		if ( 0 !== strpos( $payment_method_id, WC_Payment_Gateway_WCPay::GATEWAY_ID ) ) {
			return;
		}

		// Check if this is a reusable payment method (card, Amazon Pay).
		// Reusable payment methods can be charged for subscription renewals, so no action needed.
		if ( $this->is_reusable_wcpay_gateway( $payment_method_id ) ) {
			return;
		}

		// This is a split UPE gateway (non-reusable payment method).
		// Extract the payment method type from the gateway ID (e.g., "ideal" from "woocommerce_payments_ideal").
		$payment_method_type = str_replace( WC_Payment_Gateway_WCPay::GATEWAY_ID . '_', '', $payment_method_id );

		// Store the original payment method ID for reference.
		$subscription->update_meta_data( '_wcpay_original_payment_method_id', $payment_method_id );

		// Set to manual renewal (keep the original split payment method ID).
		$subscription->set_requires_manual_renewal( true );

		$subscription->save();

		// Add order note confirming the subscription was set to manual.
		$subscription->add_order_note(
			sprintf(
				/* translators: %s: payment method type */
				__( 'Subscription set to manual renewal because %s is a non-reusable payment method.', 'woocommerce-payments' ),
				$payment_method_type
			)
		);
	}

	/**
	 * When a customer changes the payment method for a subscription order, updates the
	 * payment method via WC_Subscriptions_Change_Payment_Gateway and adds a notice.
	 *
	 * @param WC_Order $order The order whose payment method was changed.
	 */
	public function maybe_update_subscription_payment_method( WC_Order $order ) {
		if ( ! class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) ) {
			return;
		}

		$payment_token = $this->get_payment_token( $order );
		WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $order, $payment_token->get_gateway_id() );
		$notice = __( 'Payment method updated.', 'woocommerce-payments' );

		if ( WC_Subscriptions_Change_Payment_Gateway::will_subscription_update_all_payment_methods( $order ) && WC_Subscriptions_Change_Payment_Gateway::update_all_payment_methods_from_subscription( $order, $payment_token->get_gateway_id() ) ) {
			$notice = __( 'Payment method updated for all your current subscriptions.', 'woocommerce-payments' );
		}

		wc_add_notice( $notice );
	}
}
