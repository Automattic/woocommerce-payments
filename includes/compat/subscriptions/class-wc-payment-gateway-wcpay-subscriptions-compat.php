<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Compat
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Invalid_Payment_Method_Exception;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Constants\Payment_Type;
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

		// Used to filter out unwanted metadata on new renewal orders.
		add_filter( 'wcs_renewal_order_meta_query', [ $this, 'update_renewal_meta_data' ], 10, 3 );

		// Allow store managers to manually set Stripe as the payment method on a subscription.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );
		add_filter( 'woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 3 );
		add_action( 'wcs_save_other_payment_meta', [ $this, 'save_meta_in_order_tokens' ], 10, 4 );

		add_filter( 'woocommerce_subscription_note_old_payment_method_title', [ $this, 'get_specific_old_payment_method_title' ], 10, 3 );
		add_filter( 'woocommerce_subscription_note_new_payment_method_title', [ $this, 'get_specific_new_payment_method_title' ], 10, 3 );

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
	 * Prepares the payment information object.
	 *
	 * @param WC_Order $order The order whose payment will be processed.
	 * @return Payment_Information An object, which describes the payment.
	 */
	protected function prepare_payment_information( $order ) {
		$is_changing_payment = $this->is_changing_payment_method_for_subscription();
		if ( ! $is_changing_payment && ! wcs_order_contains_subscription( $order->get_id() ) ) {
			return parent::prepare_payment_information( $order );
		}

		// Subs-specific behavior starts here.

		$payment_information = parent::prepare_payment_information( $order );
		$payment_information->set_payment_type( Payment_Type::RECURRING() );
		// The payment method is always saved for subscriptions.
		$payment_information->must_save_payment_method();
		$payment_information->set_is_changing_payment_method_for_subscription( $is_changing_payment );

		return $payment_information;
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
		if ( is_null( $token ) && ! WC_Payments::is_network_saved_cards_enabled() ) {
			Logger::error( 'There is no saved payment token for order #' . $renewal_order->get_id() );
			$renewal_order->update_status( 'failed' );
			return;
		}

		try {
			$payment_information = new Payment_Information( '', $renewal_order, Payment_Type::RECURRING(), $token, Payment_Initiated_By::MERCHANT() );
			$this->process_payment_for_order( null, $payment_information );
		} catch ( API_Exception $e ) {
			Logger::error( 'Error processing subscription renewal: ' . $e->getMessage() );

			$renewal_order->update_status( 'failed' );

			if ( ! empty( $payment_information ) ) {
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
					wc_price( $amount, [ 'currency' => WC_Payments_Utils::get_order_intent_currency( $renewal_order ) ] ),
					esc_html( rtrim( $e->getMessage(), '.' ) )
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
					'value' => empty( $active_token ) ? '' : (string) $active_token->get_id(),
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
	 * @throws Invalid_Payment_Method_Exception When $payment_meta is not valid.
	 */
	public function validate_subscription_payment_meta( $payment_gateway_id, $payment_meta, $subscription ) {
		if ( $this->id !== $payment_gateway_id ) {
			return;
		}

		if ( empty( $payment_meta[ self::PAYMENT_METHOD_META_TABLE ][ self::PAYMENT_METHOD_META_KEY ]['value'] ) ) {
			throw new Invalid_Payment_Method_Exception(
				__( 'A customer saved payment method was not selected for this order.', 'woocommerce-payments' ),
				'payment_method_not_selected'
			);
		}

		$token = WC_Payment_Tokens::get( $payment_meta[ self::PAYMENT_METHOD_META_TABLE ][ self::PAYMENT_METHOD_META_KEY ]['value'] );

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

		wp_set_script_translations( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'woocommerce-payments' );

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
			$is_valid_value = $is_valid_value || (int) $field_value === $token['tokenId'];
		}

		echo '<select name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '">';
		// If no token matches the selected ID, add a default option.
		if ( ! $is_valid_value ) {
			echo '<option value="" selected disabled>' . esc_html__( 'Please select a payment method', 'woocommerce-payments' ) . '</option>';
		}
		foreach ( $tokens as $token ) {
			$is_selected = (int) $field_value === $token['tokenId'] ? 'selected' : '';
			echo '<option value="' . esc_attr( $token['tokenId'] ) . '" ' . esc_attr( $is_selected ) . '>' . esc_html( $token['displayName'] ) . '</option>';
		}
		echo '</select>';
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
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $old_payment_method ) {
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
			if ( $token && $token instanceof WC_Payment_Token_CC ) {
				// translators: 1: payment method likely credit card, 2: last 4 digit.
				return sprintf( __( '%1$s ending in %2$s', 'woocommerce-payments' ), $old_payment_method_title, $token->get_last4() );
			}
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
			if ( $token && $token instanceof WC_Payment_Token_CC ) {
				// translators: 1: payment method likely credit card, 2: last 4 digit.
				return sprintf( __( '%1$s ending in %2$s', 'woocommerce-payments' ), $old_payment_method_title, $token->get_last4() );
			}
		}

		return $old_payment_method_title;
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
		// make sure payment method is wcpay's.
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $new_payment_method ) {
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
			if ( $token && $token instanceof WC_Payment_Token_CC ) {
				// translators: 1: payment method likely credit card, 2: last 4 digit.
				return sprintf( __( '%1$s ending in %2$s', 'woocommerce-payments' ), $new_payment_method_title, $token->get_last4() );
			}
		} else {
			try {
				$payment_method_id = $payment_information->get_payment_method();
				$payment_method    = $this->payments_api_client->get_payment_method( $payment_method_id );
				if ( ! empty( $payment_method['card']['last4'] ) ) {
					// translators: 1: payment method likely credit card, 2: last 4 digit.
					return sprintf( __( '%1$s ending in %2$s', 'woocommerce-payments' ), $new_payment_method_title, $payment_method['card']['last4'] );
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
	 */
	public function schedule_order_tracking( $order_id, $order = null ) {
		$save_meta_data = false;

		if ( is_null( $order ) ) {
			$order = wc_get_order( $order_id );
		}

		$payment_token = $this->get_payment_token( $order );

		// If we can't get the payment token for this order, then we check if we already have a payment token
		// set in the order metadata. If we don't, then we try and get the parent order's token from the metadata.
		if ( is_null( $payment_token ) ) {
			if ( empty( $order->get_meta( '_payment_method_id' ) ) ) {
				$parent_order = wc_get_order( $order->get_parent_id() );

				// If there is no parent order, or the parent order doesn't have the metadata set, then we cannot track this order.
				if ( empty( $parent_order ) || empty( $parent_order->get_meta( '_payment_method_id' ) ) ) {
					return;
				}

				$order->update_meta_data( '_payment_method_id', $parent_order->get_meta( '_payment_method_id' ) );
				$save_meta_data = true;
			}
		} elseif ( $order->get_meta( '_payment_method_id' ) !== $payment_token->get_token() ) {
			// If the payment token stored in the metadata already doesn't reflect the latest token, update it.
			$order->update_meta_data( '_payment_method_id', $payment_token->get_token() );
			$save_meta_data = true;
		}

		// If the stripe customer ID metadata isn't set for this order, try and get this data from the metadata of the parent order.
		if ( empty( $order->get_meta( '_stripe_customer_id' ) ) ) {
			$parent_order = wc_get_order( $order->get_parent_id() );
			if ( ! empty( $parent_order ) && ! empty( $parent_order->get_meta( '_stripe_customer_id' ) ) ) {
				$order->update_meta_data( '_stripe_customer_id', $parent_order->get_meta( '_stripe_customer_id' ) );
				$save_meta_data = true;
			}
		}

		// If we need to, save our changes to the metadata for this order.
		if ( $save_meta_data ) {
			$order->save_meta_data();
		}

		// Call the parent logic to schedule the order tracking.
		parent::schedule_order_tracking( $order_id, $order );
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
}
