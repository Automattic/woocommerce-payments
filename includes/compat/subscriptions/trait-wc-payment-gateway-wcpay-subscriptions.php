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
use WCPay\Exceptions\Invalid_Payment_Method_Exception;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Exceptions\Add_Payment_Method_Exception;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Initiated_By;

/**
 * Gateway class for WooCommerce Payments, with added compatibility with WooCommerce Subscriptions.
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
	 * @param array                     $additional_api_parameters Any additional fields required for payment method to pass to API.
	 *
	 * @return array|null                   An array with result of payment and redirect URL, or nothing.
	 * @throws API_Exception                Error processing the payment.
	 * @throws Add_Payment_Method_Exception When $0 order processing failed.
	 */
	abstract public function process_payment_for_order( $cart, $payment_information, $additional_api_parameters = [] );

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
	 * @param int $user_id The user ID.
	 */
	abstract protected function get_user_formatted_tokens_array( $user_id );

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
	 * Initialize subscription support and hooks.
	 */
	public function maybe_init_subscriptions() {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		/*
		 * Base set of subscription features to add.
		 * The WCPay payment gateway supports these feautres
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

		if ( $this->is_subscriptions_plugin_active() ) {
			/*
			 * Subscription amount & date changes are only supported
			 * when WooCommerce Subscriptions is active.
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

		add_filter( 'woocommerce_email_classes', [ $this, 'add_emails' ], 20 );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'prepare_order_pay_page' ] );

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

		// TODO: Remove admin payment method JS hack for Subscriptions <= 3.0.7 when we drop support for those versions.
		// Enqueue JS hack when Subscriptions does not provide the meta input filter.
		if ( $this->is_subscriptions_plugin_active() && version_compare( $this->get_subscriptions_plugin_version(), '3.0.7', '<=' ) ) {
			add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'add_payment_method_select_to_subscription_edit' ] );
		}

		/*
		 * WC subscriptions hooks into the "template_redirect" hook with priority 100.
		 * If the screen is "Pay for order" and the order is a subscription renewal, it redirects to the plain checkout.
		 * See: https://github.com/woocommerce/woocommerce-subscriptions/blob/99a75687e109b64cbc07af6e5518458a6305f366/includes/class-wcs-cart-renewal.php#L165
		 * If we are in the "You just need to authorize SCA" flow, we don't want that redirection to happen.
		 */
		add_action( 'template_redirect', [ $this, 'remove_order_pay_var' ], 99 );
		add_action( 'template_redirect', [ $this, 'restore_order_pay_var' ], 101 );
	}

	/**
	 * Adds the necessary hooks to modify the "Pay for order" page in order to clean
	 * it up and prepare it for the PaymentIntents modal to confirm a payment.
	 *
	 * @param WC_Payment_Gateway[] $gateways A list of all available gateways.
	 * @return WC_Payment_Gateway[]          Either the same list or an empty one in the right conditions.
	 */
	public function prepare_order_pay_page( $gateways ) {
		if ( ! is_wc_endpoint_url( 'order-pay' ) || ! isset( $_GET['wcpay-confirmation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $gateways;
		}

		try {
			if ( ! $this->prepare_intent_for_order_pay_page() ) {
				return $gateways;
			}
		} catch ( Exception $e ) {
			// Just show the full order pay page if there was a problem preparing the Payment Intent.
			return $gateways;
		}

		add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
		add_filter( 'woocommerce_pay_order_button_html', '__return_false' );
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );
		add_filter( 'woocommerce_no_available_payment_methods_message', [ $this, 'change_no_available_methods_message' ] );

		return [];
	}

	/**
	 * Prepares the Payment Intent for it to be completed in the "Pay for Order" page.
	 *
	 * @return bool True if the Intent was fetched and prepared successfully, false otherwise.
	 */
	public function prepare_intent_for_order_pay_page(): bool {
		$order  = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		$intent = $this->payments_api_client->get_intent( $order->get_transaction_id() );

		if ( ! $intent || 'requires_action' !== $intent->get_status() ) {
			return false;
		}

		$js_config                     = $this->get_payment_fields_js_config();
		$js_config['intentSecret']     = $intent->get_client_secret();
		$js_config['updateOrderNonce'] = wp_create_nonce( 'wcpay_update_order_status_nonce' );
		wp_localize_script( 'WCPAY_CHECKOUT', 'wcpay_config', $js_config );
		wp_enqueue_script( 'WCPAY_CHECKOUT' );
		return true;
	}

	/**
	 * Changes the text of the "No available methods" message to one that indicates
	 * the need for a PaymentIntent to be confirmed.
	 *
	 * @return string the new message.
	 */
	public function change_no_available_methods_message() {
		return wpautop( __( "Almost there!\n\nYour order has already been created, the only thing that still needs to be done is for you to authorize the payment with your bank.", 'woocommerce-payments' ) );
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
		$token = $this->get_payment_token( $renewal_order );
		if ( is_null( $token ) && ! WC_Payments::is_network_saved_cards_enabled() ) {
			Logger::error( 'There is no saved payment token for order #' . $renewal_order->get_id() );
			// TODO: Update to use Order_Service->mark_payment_failed.
			$renewal_order->update_status( 'failed' );
			return;
		}

		try {
			$payment_information = new Payment_Information( '', $renewal_order, Payment_Type::RECURRING(), $token, Payment_Initiated_By::MERCHANT() );
			$this->process_payment_for_order( null, $payment_information );
		} catch ( API_Exception $e ) {
			Logger::error( 'Error processing subscription renewal: ' . $e->getMessage() );
			// TODO: Update to use Order_Service->mark_payment_failed.
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
					WC_Payments_Explicit_Price_Formatter::get_explicit_price(
						wc_price( $amount, [ 'currency' => WC_Payments_Utils::get_order_intent_currency( $renewal_order ) ] ),
						$renewal_order
					),
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
			self::$payment_method_meta_table => [
				self::$payment_method_meta_key => [
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
				self::$payment_method_meta_table,
				self::$payment_method_meta_key
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
				'table'             => self::$payment_method_meta_table,
				'metaKey'           => self::$payment_method_meta_key,
				'tokens'            => $this->get_user_formatted_tokens_array( $order->get_user_id() ),
				'defaultOptionText' => __( 'Please select a payment method', 'woocommerce-payments' ),
			]
		);

		wp_set_script_translations( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_SUBSCRIPTION_EDIT_PAGE' );
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
	 * If this is the "Pass the SCA challenge" flow, remove a variable that is checked by WC Subscriptions
	 * so WC Subscriptions doesn't redirect to the checkout
	 */
	public function remove_order_pay_var() {
		global $wp;
		if ( isset( $_GET['wcpay-confirmation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->order_pay_var         = $wp->query_vars['order-pay'];
			$wp->query_vars['order-pay'] = null;
		}
	}

	/**
	 * Restore the variable that was removed in remove_order_pay_var()
	 */
	public function restore_order_pay_var() {
		global $wp;
		if ( isset( $this->order_pay_var ) ) {
			$wp->query_vars['order-pay'] = $this->order_pay_var;
		}
	}
}
