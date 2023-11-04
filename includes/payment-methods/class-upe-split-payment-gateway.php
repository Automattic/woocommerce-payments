<?php
/**
 * Class UPE_Split_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use Exception;
use WC_Order;
use WC_Payments_API_Setup_Intention;
use WC_Payments_Features;
use WC_Payments_Fraud_Service;
use WCPay\Core\Server\Request\Get_Setup_Intention;
use WCPay\Exceptions\Add_Payment_Method_Exception;
use WCPay\Exceptions\Invalid_Address_Exception;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Logger;
use WCPay\Session_Rate_Limiter;
use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Order_Service;
use WC_Payment_Token_CC;
use WC_Payments_Token_Service;
use WC_Payment_Token_WCPay_SEPA;
use WC_Payments_Utils;
use WCPay\Constants\Payment_Method;
use WCPay\Duplicate_Payment_Prevention_Service;
use WP_User;
use WC_Payments_Localization_Service;
use WCPay\Payment_Information;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;


/**
 * Split UPE Payment gateway extended from UPE payment gateway.
 */
class UPE_Split_Payment_Gateway extends UPE_Payment_Gateway {
	/**
	 * UPE Payment Method for gateway.
	 *
	 * @var UPE_Payment_Method
	 */
	protected $payment_method;

	/**
	 * Stripe payment method type ID.
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * UPE Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client                  - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                              - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service                     - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service                        - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service             - Action Scheduler service instance.
	 * @param UPE_Payment_Method                   $payment_method                       - Specific UPE_Payment_Method instance for gateway.
	 * @param array                                $payment_methods                      - Array of payment methods to supply to parent.
	 * @param Session_Rate_Limiter                 $failed_transaction_rate_limiter      - Session Rate Limiter instance.
	 * @param WC_Payments_Order_Service            $order_service                        - Order class instance.
	 * @param Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service - Service for preventing duplicate payments.
	 * @param WC_Payments_Localization_Service     $localization_service                 - Localization service instance.
	 * @param WC_Payments_Fraud_Service            $fraud_service                        - Fraud service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		UPE_Payment_Method $payment_method,
		array $payment_methods,
		Session_Rate_Limiter $failed_transaction_rate_limiter,
		WC_Payments_Order_Service $order_service,
		Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service,
		WC_Payments_Localization_Service $localization_service,
		WC_Payments_Fraud_Service $fraud_service
	) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service, $payment_methods, $failed_transaction_rate_limiter, $order_service, $duplicate_payment_prevention_service, $localization_service, $fraud_service );
		$this->method_description = __( 'Payments made simple, with no monthly fees - designed exclusively for WooCommerce stores. Accept credit cards, debit cards, and other popular payment methods.', 'woocommerce-payments' );
		$this->description        = '';
		$this->stripe_id          = $payment_method->get_id();
		$this->payment_method     = $payment_method;
		$this->title              = $payment_method->get_title();
		$this->icon               = $payment_method->get_icon();

		if ( 'card' !== $this->stripe_id ) {
			$this->id           = self::GATEWAY_ID . '_' . $this->stripe_id;
			$this->method_title = "WooPayments ($this->title)";
		}
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
			add_action( "wc_ajax_wcpay_create_payment_intent_$this->stripe_id", [ $this, 'create_payment_intent_ajax' ] );
			add_action( "wc_ajax_wcpay_update_payment_intent_$this->stripe_id", [ $this, 'update_payment_intent_ajax' ] );
		}
		add_action( "wc_ajax_wcpay_init_setup_intent_$this->stripe_id", [ $this, 'init_setup_intent_ajax' ] );

		parent::init_hooks();
	}

	/**
	 * Displays HTML tags for WC payment gateway radio button content.
	 */
	public function display_gateway_html() {
		?>
			<div class="wcpay-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
		<?php
	}

	/**
	 * Returns boolean for whether payment gateway supports saved payments.
	 *
	 * @return bool True, if gateway supports saved payments. False, otherwise.
	 */
	public function should_support_saved_payments() {
		return $this->is_enabled_for_saved_payments( $this->stripe_id );
	}

	/**
	 * Whether we should use the platform account to initialize Stripe on the checkout page.
	 *
	 * @return bool Result of the WCPay gateway checks if the card payment method is used, false otherwise.
	 */
	public function should_use_stripe_platform_on_checkout_page() {
		if ( 'card' === $this->stripe_id ) {
			return parent::should_use_stripe_platform_on_checkout_page();
		}
		return false;
	}

	/**
	 * This method is used by WooCommerce Core's WC_Payment_Gateways::get_available_payment_gateways() to filter out gateways.
	 *
	 * The availability decision includes an additional business rule that checks if the payment method is enabled at checkout
	 * via the is_enabled_at_checkout method. This method provides crucial information, among others, to determine if the gateway
	 * is reusable in case there's a subcription in the cart.
	 *
	 * @return bool Whether the gateway is enabled and ready to accept payments.
	 */
	public function is_available() {
		$processing_payment_method = $this->payment_methods[ $this->payment_method->get_id() ];
		if ( ! $processing_payment_method->is_enabled_at_checkout() ) {
			return false;
		}
		return parent::is_available();
	}

	/**
	 * Gets UPE_Payment_Method instance from ID.
	 *
	 * @param string $payment_method_type Stripe payment method type ID.
	 * @return UPE_Payment_Method|false UPE payment method instance.
	 */
	public function get_selected_payment_method( $payment_method_type ) {
		return WC_Payments::get_payment_method_by_id( $payment_method_type );
	}

	/**
	 * Set formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param \WC_Order  $order WC Order being processed.
	 * @param string     $payment_method_type Stripe payment method key.
	 * @param array|bool $payment_method_details Array of payment method details from charge or false.
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details ) {
		$payment_method = $this->get_selected_payment_method( $payment_method_type );
		if ( ! $payment_method ) {
			return;
		}

		$payment_method_title = $payment_method->get_title( $payment_method_details );

		$payment_gateway = in_array( $payment_method->get_id(), [ Payment_Method::CARD, Payment_Method::LINK ], true ) ? self::GATEWAY_ID : self::GATEWAY_ID . '_' . $payment_method_type;

		$order->set_payment_method( $payment_gateway );
		$order->set_payment_method_title( $payment_method_title );
		$order->save();
	}

	/**
	 * Handle AJAX request for updating a payment intent for Stripe UPE.
	 *
	 * @throws Process_Payment_Exception - If nonce or setup intent is invalid.
	 */
	public function update_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_update_payment_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
					'wcpay_upe_intent_error'
				);
			}

			$order_id                  = isset( $_POST['wcpay_order_id'] ) ? absint( $_POST['wcpay_order_id'] ) : null;
			$payment_intent_id         = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : '';
			$fingerprint               = isset( $_POST['wcpay-fingerprint'] ) ? wc_clean( wp_unslash( $_POST['wcpay-fingerprint'] ) ) : '';
			$save_payment_method       = isset( $_POST['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_POST['save_payment_method'] ) ) : false;
			$selected_upe_payment_type = $this->stripe_id;
			$payment_country           = ! empty( $_POST['wcpay_payment_country'] ) ? wc_clean( wp_unslash( $_POST['wcpay_payment_country'] ) ) : null;

			wp_send_json_success( $this->update_payment_intent( $payment_intent_id, $order_id, $save_payment_method, $selected_upe_payment_type, $payment_country, $fingerprint ), 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				],
				WC_Payments_Utils::get_filtered_error_status_code( $e ),
			);
		}
	}

	/**
	 * Handle AJAX request for creating a payment intent for Stripe UPE.
	 *
	 * @throws Process_Payment_Exception - If nonce or setup intent is invalid.
	 */
	public function create_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_create_payment_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
					'wcpay_upe_intent_error'
				);
			}

			// If paying from order, we need to get the total from the order instead of the cart.
			$order_id    = isset( $_POST['wcpay_order_id'] ) ? absint( $_POST['wcpay_order_id'] ) : null;
			$fingerprint = isset( $_POST['wcpay-fingerprint'] ) ? wc_clean( wp_unslash( $_POST['wcpay-fingerprint'] ) ) : '';

			$enabled_payment_methods = $this->get_payment_method_ids_enabled_at_checkout( $order_id, true );
			if ( ! in_array( $this->payment_method->get_id(), $enabled_payment_methods, true ) ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
					'wcpay_upe_intent_error'
				);
			}
			$displayed_payment_methods = [ $this->payment_method->get_id() ];
			if ( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID === $this->payment_method->get_id() ) {
				if ( in_array( Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $enabled_payment_methods, true ) ) {
					$displayed_payment_methods[] = Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
				}
			}

			$response = $this->create_payment_intent( $displayed_payment_methods, $order_id, $fingerprint );

			// Encrypt client secret before exposing it to the browser.
			if ( $response['client_secret'] ) {
				$response['client_secret'] = WC_Payments_Utils::encrypt_client_secret( $this->account->get_stripe_account_id(), $response['client_secret'] );
			}

			if ( strpos( $response['id'], 'pi_' ) === 0 ) { // response is a payment intent (could possibly be a setup intent).
				$this->add_upe_payment_intent_to_session( $response['id'], $response['client_secret'] );
			}

			wp_send_json_success( $response, 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				],
				WC_Payments_Utils::get_filtered_error_status_code( $e ),
			);
		}
	}

	/**
	 * Handle AJAX request for creating a setup intent without confirmation for Stripe UPE.
	 *
	 * @throws Add_Payment_Method_Exception - If nonce or setup intent is invalid.
	 */
	public function init_setup_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_create_setup_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Add_Payment_Method_Exception(
					__( "We're not able to add this payment method. Please refresh the page and try again.", 'woocommerce-payments' ),
					'invalid_referrer'
				);
			}

			$enabled_payment_methods = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] );
			if ( ! in_array( $this->payment_method->get_id(), $enabled_payment_methods, true ) ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
					'wcpay_upe_intent_error'
				);
			}
			$displayed_payment_methods = [ $this->payment_method->get_id() ];

			$response = $this->create_setup_intent( $displayed_payment_methods );

			// Encrypt client secret before exposing it to the browser.
			if ( $response['client_secret'] ) {
				$response['client_secret'] = WC_Payments_Utils::encrypt_client_secret( $this->account->get_stripe_account_id(), $response['client_secret'] );
			}

			$this->add_upe_setup_intent_to_session( $response['id'], $response['client_secret'] );

			wp_send_json_success( $response, 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				],
				WC_Payments_Utils::get_filtered_error_status_code( $e ),
			);
		}
	}


	/**
	 * Renders the credit card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		do_action( 'wc_payments_set_gateway', $this->get_selected_stripe_payment_type_id() );
		do_action( 'wc_payments_add_upe_payment_fields' );
	}

	/**
	 * Update payment intent for completed checkout and return redirect URL for Stripe to confirm payment.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 * @throws Exception Error processing the payment.
	 */
	public function process_payment( $order_id ) {
		$_POST['wcpay_selected_upe_payment_type'] = $this->stripe_id;
		return parent::process_payment( $order_id );
	}

	/**
	 * Adds a token to current user from a setup intent id.
	 *
	 * @param string  $setup_intent_id ID of the setup intent.
	 * @param WP_User $user            User to add token to.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA|null The added token.
	 */
	public function create_token_from_setup_intent( $setup_intent_id, $user ) {
		try {
			$setup_intent_request = Get_Setup_Intention::create( $setup_intent_id );
			/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$setup_intent = $setup_intent_request->send();

			$payment_method_id = $setup_intent->get_payment_method_id();
			// TODO: When adding SEPA and Sofort, we will need a new API call to get the payment method and from there get the type.
			// Leaving 'card' as a hardcoded value for now to avoid the extra API call.
			// $payment_method = $this->payment_methods['card'];// Maybe this should be enforced.
			$payment_method = $this->payment_method;

			return $payment_method->get_payment_token_for_user( $user, $payment_method_id );
		} catch ( Exception $e ) {
			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error', [ 'icon' => 'error' ] );
			Logger::log( 'Error when adding payment method: ' . $e->getMessage() );
		}
	}

	/**
	 * Mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed.
	 * This applies to SEPA and Link payment methods.
	 * https://stripe.com/docs/payments/finalize-payments-on-the-server
	 *
	 * @return boolean True if mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed, false otherwise.
	 */
	public function is_mandate_data_required() {
		$is_stripe_link_enabled = Payment_Method::CARD === $this->get_selected_stripe_payment_type_id() && in_array( Payment_Method::LINK, $this->get_upe_enabled_payment_method_ids(), true );
		$is_sepa_debit_payment  = Payment_Method::SEPA === $this->get_selected_stripe_payment_type_id();

		return $is_stripe_link_enabled || $is_sepa_debit_payment;
	}

	/**
	 * Returns the Stripe payment type of the selected payment method.
	 *
	 * @return string
	 */
	public function get_selected_stripe_payment_type_id() {
		return $this->stripe_id;
	}

	/**
	 * Adds the id and client secret of payment intent needed to mount the UPE element in frontend to WC session.
	 *
	 * @param string $intent_id     The payment intent id.
	 * @param string $client_secret The payment intent client secret.
	 */
	private function add_upe_payment_intent_to_session( string $intent_id = '', string $client_secret = '' ) {
		$cart_hash = 'undefined';

		if ( isset( $_COOKIE['woocommerce_cart_hash'] ) ) {
			$cart_hash = sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_cart_hash'] ) );
		}

		$value = $cart_hash . '-' . $intent_id . '-' . $client_secret;

		WC()->session->set( $this->get_payment_intent_session_key(), $value );
	}

	/**
	 * Removes all UPE payment intents from WC session.
	 */
	public static function remove_upe_payment_intent_from_session() {
		if ( isset( WC()->session ) ) {
			foreach ( WC_Payments::get_payment_method_map() as $id => $payment_method ) {
				WC()->session->__unset( self::KEY_UPE_PAYMENT_INTENT . '_' . $payment_method->get_id() );
			}
		}
	}

	/**
	 * Adds the id and client secret of setup intent needed to mount the UPE element in frontend to WC session.
	 *
	 * @param string $intent_id     The setup intent id.
	 * @param string $client_secret The setup intent client secret.
	 */
	private function add_upe_setup_intent_to_session( string $intent_id = '', string $client_secret = '' ) {
		$value = $intent_id . '-' . $client_secret;

		WC()->session->set( $this->get_setup_intent_session_key(), $value );
	}

	/**
	 * Removes all UPE setup intents from WC session.
	 */
	public function remove_upe_setup_intent_from_session() {
		if ( isset( WC()->session ) ) {
			foreach ( $this->wc_payments_get_payment_method_map() as $id => $payment_method ) {
				WC()->session->__unset( $this->get_setup_intent_session_key( $payment_method->get_id() ) );
			}
		}
	}

	/**
	 * Returns session key for UPE SEPA payment intents.
	 *
	 * @param false|string $payment_method Stripe payment method.
	 * @return string
	 */
	public function get_payment_intent_session_key( $payment_method = false ) {
		if ( false === $payment_method ) {
			$payment_method = $this->stripe_id;
		}
		return self::KEY_UPE_PAYMENT_INTENT . '_' . $payment_method;
	}

	/**
	 * Returns session key for UPE SEPA setup intents.
	 *
	 * @param false|string $payment_method Stripe payment method.
	 * @return string
	 */
	public function get_setup_intent_session_key( $payment_method = false ) {
		if ( false === $payment_method ) {
			$payment_method = $this->stripe_id;
		}
		return self::KEY_UPE_SETUP_INTENT . '_' . $payment_method;
	}

	/**
	 * Returns payment intent session data.
	 *
	 * @param false|string $payment_method Stripe payment method.
	 * @return array|string value of session variable
	 */
	public function get_payment_intent_data_from_session( $payment_method = false ) {
		return WC()->session->get( $this->get_payment_intent_session_key( $payment_method ) );
	}

	/**
	 * Returns setup intent session data.
	 *
	 * @param false|string $payment_method Stripe payment method.
	 * @return array|string value of session variable
	 */
	public function get_setup_intent_data_from_session( $payment_method = false ) {
		return WC()->session->get( $this->get_setup_intent_session_key( $payment_method ) );
	}

	/**
	 * This function wraps WC_Payments::get_payment_method_by_id, useful for unit testing.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|UPE_Payment_Method Matching UPE Payment Method instance.
	 */
	public function wc_payments_get_payment_method_by_id( $payment_method_id ) {
		return WC_Payments::get_payment_method_by_id( $payment_method_id );
	}

	/**
	 * This function wraps WC_Payments::get_payment_gateway_by_id, useful for unit testing.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|UPE_Payment_Gateway Matching UPE Payment Gateway instance.
	 */
	public function wc_payments_get_payment_gateway_by_id( $payment_method_id ) {
		return WC_Payments::get_payment_gateway_by_id( $payment_method_id );
	}

	/**
	 * This function wraps WC_Payments::get_payment_method_map, useful for unit testing.
	 *
	 * @return array Array of UPE_Payment_Method instances.
	 */
	public function wc_payments_get_payment_method_map() {
		return WC_Payments::get_payment_method_map();
	}

	/**
	 * Returns the UPE payment method for the gateway.
	 *
	 * @return UPE_Payment_Method
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns Stripe payment method type ID.
	 *
	 * @return string
	 */
	public function get_stripe_id() {
		return $this->stripe_id;
	}

	/**
	 * Handles the shipping requirement for Afterpay payments.
	 *
	 * This method extracts the shipping and billing data from the order and sets the appropriate
	 * shipping data for the Afterpay payment request. If neither shipping nor billing data is valid
	 * for shipping, an exception is thrown.
	 *
	 * @param WC_Order                     $order    The order object containing shipping and billing information.
	 * @param Create_And_Confirm_Intention $request The Afterpay payment request object to set shipping data on.
	 *
	 * @throws Invalid_Address_Exception If neither shipping nor billing address is valid for Afterpay payments.
	 * @return void
	 */
	private function handle_afterpay_shipping_requirement( WC_Order $order, Create_And_Confirm_Intention $request ): void {
		$check_if_usable = function( array $address ): bool {
			return $address['country'] && $address['state'] && $address['city'] && $address['postal_code'] && $address['line1'];
		};

		$shipping_data = $this->order_service->get_shipping_data_from_order( $order );
		if ( $check_if_usable( $shipping_data['address'] ) ) {
			$request->set_shipping( $shipping_data );
			return;
		}

		$billing_data = $this->order_service->get_billing_data_from_order( $order );
		if ( $check_if_usable( $billing_data['address'] ) ) {
			$request->set_shipping( $billing_data );
			return;
		}

		throw new Invalid_Address_Exception( __( 'A valid shipping address is required for Afterpay payments.', 'woocommerce-payments' ) );
	}


	/**
	 * Modifies the create intent parameters when processing a payment.
	 *
	 * If the selected Stripe payment type is AFTERPAY, it updates the shipping data in the request.
	 *
	 * @param Create_And_Confirm_Intention $request               The request object for creating and confirming intention.
	 * @param Payment_Information          $payment_information   The payment information object.
	 * @param WC_Order                     $order                 The order object.
	 *
	 * @return void
	 */
	protected function modify_create_intent_parameters_when_processing_payment( Create_And_Confirm_Intention $request, Payment_Information $payment_information, WC_Order $order ): void {
		if ( Payment_Method::AFTERPAY === $this->get_selected_stripe_payment_type_id() ) {
			$this->handle_afterpay_shipping_requirement( $order, $request );
		}
	}
}
