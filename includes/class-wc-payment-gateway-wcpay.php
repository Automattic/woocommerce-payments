<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;
use WCPay\Exceptions\WC_Payments_Intent_Authentication_Exception;
use WCPay\Tracker;

/**
 * Gateway class for WooCommerce Payments
 */
class WC_Payment_Gateway_WCPay extends WC_Payment_Gateway_CC {

	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments';

	/**
	 * Set of parameters to build the URL to the gateway's settings page.
	 *
	 * @var string[]
	 */
	private static $settings_url_params = [
		'page'    => 'wc-settings',
		'tab'     => 'checkout',
		'section' => self::GATEWAY_ID,
	];

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payments_Token instance for working with customer tokens
	 *
	 * @var WC_Payments_Token_Service
	 */
	private $token_service;

	/**
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client       $payments_api_client - WooCommerce Payments API client.
	 * @param WC_Payments_Account          $account             - Account class instance.
	 * @param WC_Payments_Customer_Service $customer_service    - Customer class instance.
	 * @param WC_Payments_Token_Service    $token_service       - Token class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service
	) {
		$this->payments_api_client = $payments_api_client;
		$this->account             = $account;
		$this->customer_service    = $customer_service;
		$this->token_service       = $token_service;

		$this->id                 = self::GATEWAY_ID;
		$this->icon               = ''; // TODO: icon.
		$this->has_fields         = true;
		$this->method_title       = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via credit card.', 'woocommerce-payments' );
		$this->title              = __( 'Credit card', 'woocommerce-payments' );
		$this->description        = __( 'Enter your card details', 'woocommerce-payments' );
		$this->supports           = [
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
		];

		// Define setting fields.
		$this->form_fields = [
			'enabled'         => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_details' => [
				'type' => 'account_actions',
			],
			'account_status'  => [
				'type' => 'account_status',
			],
			'manual_capture'  => [
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Charge must be captured within 7 days of authorization, otherwise the authorization and order will be canceled.', 'woocommerce-payments' ),
				'default'     => 'no',
			],
			'test_mode'       => [
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'enable_logging'  => [
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
		];

		if ( $this->is_in_dev_mode() ) {
			$this->form_fields['test_mode']['custom_attributes']['disabled']      = 'disabled';
			$this->form_fields['test_mode']['label']                              = __( 'Dev mode is active so all transactions will be in test mode. This setting is only available to live accounts.', 'woocommerce-payments' );
			$this->form_fields['enable_logging']['custom_attributes']['disabled'] = 'disabled';
			$this->form_fields['enable_logging']['label']                         = __( 'Dev mode is active so logging is on by default.', 'woocommerce-payments' );
		}

		// Load the settings.
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_order_actions', [ $this, 'add_order_actions' ] );
		add_action( 'woocommerce_order_action_capture_charge', [ $this, 'capture_charge' ] );
		add_action( 'woocommerce_order_action_cancel_authorization', [ $this, 'cancel_authorization' ] );

		add_action( 'wp_ajax_update_order_status', [ $this, 'update_order_status' ] );
		add_action( 'wp_ajax_nopriv_update_order_status', [ $this, 'update_order_status' ] );

		add_action( 'wp_ajax_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );
	}

	/**
	 * Check if the payment gateway is connected. This method is also used by
	 * external plugins to check if a connection has been established.
	 */
	public function is_connected() {
		return $this->account->is_stripe_connected( false );
	}

	/**
	 * Returns true if the gateway needs additional configuration, false if it's ready to use.
	 *
	 * @see WC_Payment_Gateway::needs_setup
	 * @return bool
	 */
	public function needs_setup() {
		if ( ! $this->is_connected() ) {
			return true;
		}

		$account_status = $this->account->get_account_status_data();
		return parent::needs_setup() || ! empty( $account_status['error'] ) || ! $account_status['paymentsEnabled'];
	}

	/**
	 * Whether the current page is the WooCommerce Payments settings page.
	 *
	 * @return bool
	 */
	public static function is_current_page_settings() {
		return count( self::$settings_url_params ) === count( array_intersect_assoc( $_GET, self::$settings_url_params ) ); // phpcs:disable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Returns the URL of the configuration screen for this gateway, for use in internal links.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url() {
		return admin_url( add_query_arg( self::$settings_url_params, 'admin.php' ) );
	}

	/**
	 * Check the defined constant to determine the current plugin mode.
	 *
	 * @return bool
	 */
	public function is_in_dev_mode() {
		return apply_filters( 'wcpay_dev_mode', defined( 'WCPAY_DEV_MODE' ) && WCPAY_DEV_MODE );
	}

	/**
	 * Returns whether test_mode or dev_mode is active for the gateway
	 *
	 * @return boolean Test mode enabled if true, disabled if false
	 */
	public function is_in_test_mode() {
		if ( $this->is_in_dev_mode() ) {
			return true;
		}

		return 'yes' === $this->get_option( 'test_mode' );
	}

	/**
	 * Checks if the gateway is enabled, and also if it's configured enough to accept payments from customers.
	 *
	 * Use parent method value alongside other business rules to make the decision.
	 *
	 * @return bool Whether the gateway is enabled and ready to accept payments.
	 */
	public function is_available() {
		if ( 'USD' !== get_woocommerce_currency() ) {
			return false;
		}

		return parent::is_available() && ! $this->needs_setup();
	}

	/**
	 * Add notice to WooCommerce Payments settings page explaining test mode when it's enabled.
	 */
	public function admin_options() {
		if ( $this->is_in_test_mode() ) {
			?>
			<div id="wcpay-test-mode-notice" class="notice notice-warning">
				<p>
					<b><?php esc_html_e( 'Test mode active: ', 'woocommerce-payments' ); ?></b>
					<?php esc_html_e( "All transactions are simulated. Customers can't make real purchases through WooCommerce Payments.", 'woocommerce-payments' ); ?>
				</p>
			</div>
			<?php
		}

		parent::admin_options();
	}

	/**
	 * Displays the save to account checkbox.
	 */
	public function save_payment_method_checkbox() {
		printf(
			'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
			esc_attr( $this->id ),
			esc_html( apply_filters( 'wc_payments_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-payments' ) ) )
		);
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->supports( 'tokenization' ) && is_checkout();

			// Add JavaScript for the payment form.
			$js_config = [
				'publishableKey'         => $this->account->get_publishable_key( $this->is_in_test_mode() ),
				'accountId'              => $this->account->get_stripe_account_id(),
				'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
				'updateOrderStatusNonce' => wp_create_nonce( 'wcpay_update_order_status_nonce' ),
				'createSetupIntentNonce' => wp_create_nonce( 'wcpay_create_setup_intent_nonce' ),
				'genericErrorMessage'    => __( 'There was a problem processing the payment. Please check your email and refresh the page to try again.', 'woocommerce-payments' ),
			];

			// Register Stripe's JavaScript using the same ID as the Stripe Gateway plugin. This prevents this JS being
			// loaded twice in the event a site has both plugins enabled. We still run the risk of different plugins
			// loading different versions however. If Stripe release a v4 of their JavaScript, we could consider
			// changing the ID to stripe_v4. This would allow older plugins to keep using v3 while we used any new
			// feature in v4. Stripe have allowed loading of 2 different versions of stripe.js in the past (
			// https://stripe.com/docs/stripe-js/elements/migrating).
			wp_register_script(
				'stripe',
				'https://js.stripe.com/v3/',
				[],
				'3.0',
				true
			);

			wp_register_script(
				'wcpay-checkout',
				plugins_url( 'assets/js/wcpay-checkout.js', WCPAY_PLUGIN_FILE ),
				[ 'stripe', 'wc-checkout' ],
				WC_Payments::get_file_version( 'assets/js/wcpay-checkout.js' ),
				true
			);

			wp_localize_script( 'wcpay-checkout', 'wcpay_config', $js_config );
			wp_enqueue_script( 'wcpay-checkout' );

			wp_enqueue_style(
				'wcpay-checkout',
				plugins_url( 'assets/css/wcpay-checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'assets/css/wcpay-checkout.css' )
			);

			// Output the form HTML.
			?>
			<fieldset>
				<?php if ( ! empty( $this->get_description() ) ) : ?>
					<legend><?php echo wp_kses_post( $this->get_description() ); ?></legend>
				<?php endif; ?>

				<?php if ( $this->is_in_test_mode() ) : ?>
					<p class="testmode-info">
					<?php
						echo WC_Payments_Utils::esc_interpolated_html(
							/* translators: link to Stripe testing page */
							__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC, or any test card numbers listed <a>here</a>.', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'a'      => '<a href="https://docs.woocommerce.com/document/payments/testing/#test-cards" target="_blank">',
							]
						);
					?>
					</p>
				<?php endif; ?>

				<?php
				if ( $display_tokenization ) {
					$this->tokenization_script();
					echo $this->saved_payment_methods(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>

				<div id="wcpay-card-element"></div>
				<div id="wcpay-errors" role="alert"></div>
				<input id="wcpay-payment-method" type="hidden" name="wcpay-payment-method" />

				<?php
				if ( apply_filters( 'wc_payments_display_save_payment_method_checkbox', $display_tokenization ) && ! is_add_payment_method_page() ) {
					echo $this->save_payment_method_checkbox(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>


			</fieldset>
			<?php
		} catch ( Exception $e ) {
			// Output the error message.
			?>
			<div>
				<?php
				echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-payments' );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		return $this->process_payment_for_order( $order, WC()->cart );
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param WC_Order $order Order to process the payment for.
	 * @param WC_Cart  $cart The WC_Cart object.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment_for_order( $order, $cart ) {
		try {
			$order_id = $order->get_id();
			$amount   = $order->get_total();

			if ( $amount > 0 ) {
				// Get the payment method from the request (generated when the user entered their card details).
				$payment_method      = $this->get_payment_method_from_request();
				$manual_capture      = 'yes' === $this->get_option( 'manual_capture' );
				$name                = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
				$email               = sanitize_email( $order->get_billing_email() );
				$country             = sanitize_text_field( $order->get_billing_country() );
				$save_payment_method = ! empty( $_POST[ 'wc-' . self::GATEWAY_ID . '-new-payment-method' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

				// Determine the customer making the payment, create one if we don't have one already.
				$user        = wp_get_current_user();
				$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );

				if ( null === $customer_id ) {
					// Create a new customer.
					$customer_id = $this->customer_service->create_customer_for_user( $user, $name, $email );
				} else {
					// Update the existing customer with the current details. In the event the old customer can't be
					// found a new one is created, so we update the customer ID here as well.
					$customer_id = $this->customer_service->update_customer_for_user( $customer_id, $user, $name, $email );
				}

				// Update payment method information with checkout values, as some saved methods might not have billing details.
				$billing_details = $this->get_billing_details_from_request();
				if ( ! empty( $billing_details ) ) {
					$this->payments_api_client->update_payment_method(
						$payment_method,
						[
							'billing_details' => $billing_details,
						]
					);
				}

				$metadata = [
					'customer_name'    => $name,
					'customer_email'   => $email,
					'customer_country' => $country,
					'site_url'         => esc_url( get_site_url() ),
					'order_id'         => $order->get_id(),
				];

				// Create intention, try to confirm it & capture the charge (if 3DS is not required).
				$intent = $this->payments_api_client->create_and_confirm_intention(
					WC_Payments_Utils::prepare_amount( $amount, 'USD' ),
					'usd',
					$payment_method,
					$customer_id,
					$manual_capture,
					$save_payment_method,
					$metadata,
					$this->get_level3_data_from_order( $order )
				);

				if ( $save_payment_method ) {
					$payment_method_object = $this->payments_api_client->get_payment_method( $payment_method );
					$this->token_service->add_token_to_user( $payment_method_object, wp_get_current_user() );
				}

				// TODO: We're not handling *all* sorts of things here. For example, redirecting to a 3DS auth flow.
				$intent_id = $intent->get_id();
				$status    = $intent->get_status();

				switch ( $status ) {
					case 'succeeded':
						$note = sprintf(
							WC_Payments_Utils::esc_interpolated_html(
								/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
								__( 'A payment of %1$s was <strong>successfully charged</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
								[
									'strong' => '<strong>',
									'code'   => '<code>',
								]
							),
							wc_price( $amount ),
							$intent_id
						);

						$order->update_meta_data( '_intent_id', $intent_id );
						$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
						$order->update_meta_data( '_intention_status', $status );
						$order->save();

						$order->add_order_note( $note );
						$order->payment_complete( $intent_id );
						break;
					case 'requires_capture':
						$note = sprintf(
							WC_Payments_Utils::esc_interpolated_html(
								/* translators: %1: the authorized amount, %2: transaction ID of the payment */
								__( 'A payment of %1$s was <strong>authorized</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
								[
									'strong' => '<strong>',
									'code'   => '<code>',
								]
							),
							wc_price( $amount ),
							$intent_id
						);

						$order->update_status( 'on-hold', $note );
						$order->set_transaction_id( $intent_id );

						$order->update_meta_data( '_intent_id', $intent_id );
						$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
						$order->update_meta_data( '_intention_status', $status );
						$order->save();

						break;
					case 'requires_action':
						// Add a note in case the customer does not complete the payment (exits the page),
						// so the store owner has some information about what happened to create an order.
						$note = sprintf(
							WC_Payments_Utils::esc_interpolated_html(
								/* translators: %1: the authorized amount, %2: transaction ID of the payment */
								__( 'A payment of %1$s was <strong>started</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
								[
									'strong' => '<strong>',
									'code'   => '<code>',
								]
							),
							wc_price( $amount ),
							$intent_id
						);
						$order->add_order_note( $note );

						$order->update_meta_data( '_intent_id', $intent_id );
						$order->update_meta_data( '_intention_status', $status );
						$order->save();

						return [
							'result'   => 'success',
							'redirect' => sprintf( '#wcpay-confirm-pi:%s:%s', $order_id, $intent->get_client_secret() ),
						];
				}
			} else {
				$order->payment_complete();
			}

			wc_reduce_stock_levels( $order_id );
			$cart->empty_cart();

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];
		} catch ( Exception $e ) {
			// TODO: Create plugin specific exceptions so that we can be smarter about what we create notices for.
			wc_add_notice( $e->getMessage(), 'error' );

			$order->update_status( 'failed' );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * Extract the payment method from the request's POST variables
	 *
	 * @return string
	 * @throws Exception - If no payment method is found.
	 */
	private function get_payment_method_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['wcpay-payment-method'] ) && empty( $_POST[ 'wc-' . self::GATEWAY_ID . '-payment-token' ] ) ) {
			// If no payment method is set then stop here with an error.
			throw new Exception( __( 'Payment method not found.', 'woocommerce-payments' ) );
		}

		$payment_method = wc_clean( $_POST['wcpay-payment-method'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( empty( $payment_method ) ) {
			$token_id = wc_clean( $_POST[ 'wc-' . self::GATEWAY_ID . '-payment-token' ] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$token    = WC_Payment_Tokens::get( $token_id );

			if ( ! $token || self::GATEWAY_ID !== $token->get_gateway_id() || $token->get_user_id() !== get_current_user_id() ) {
				throw new Exception( __( 'Invalid payment method. Please input a new card number.', 'woocommerce-payments' ) );
			}

			$payment_method = $token->get_token();
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $payment_method;
	}

	/**
	 * Extract the billing details from the request's POST variables
	 *
	 * @return array
	 */
	private function get_billing_details_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$first_name      = ! empty( $_POST['billing_first_name'] ) ? wc_clean( $_POST['billing_first_name'] ) : '';
		$last_name       = ! empty( $_POST['billing_last_name'] ) ? wc_clean( $_POST['billing_last_name'] ) : '';
		$name            = trim( $first_name . ' ' . $last_name );
		$billing_details = [
			'address' => [
				'city'        => ! empty( $_POST['billing_city'] ) ? wc_clean( $_POST['billing_city'] ) : null,
				'country'     => ! empty( $_POST['billing_country'] ) ? wc_clean( $_POST['billing_country'] ) : null,
				'line1'       => ! empty( $_POST['billing_address_1'] ) ? wc_clean( $_POST['billing_address_1'] ) : null,
				'line2'       => ! empty( $_POST['billing_address_2'] ) ? wc_clean( $_POST['billing_address_2'] ) : null,
				'postal_code' => ! empty( $_POST['billing_postcode'] ) ? wc_clean( $_POST['billing_postcode'] ) : null,
				'state'       => ! empty( $_POST['billing_state'] ) ? wc_clean( $_POST['billing_state'] ) : null,
			],
			'email'   => ! empty( $_POST['billing_email'] ) ? wc_clean( $_POST['billing_email'] ) : null,
			'name'    => ! empty( $name ) ? $name : null,
			'phone'   => ! empty( $_POST['billing_phone'] ) ? wc_clean( $_POST['billing_phone'] ) : null,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$remove_empty_entries = function ( $value ) {
			return ! empty( $value );
		};

		$billing_details['address'] = array_filter( $billing_details['address'], $remove_empty_entries );
		return array_filter( $billing_details, $remove_empty_entries );
	}

	/**
	 * Can the order be refunded?
	 *
	 * @param  WC_Order $order Order object.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_meta( '_charge_id', true );
	}

	/**
	 * Refund a charge.
	 *
	 * @param  int    $order_id - the Order ID to process the refund for.
	 * @param  float  $amount   - the amount to refund.
	 * @param  string $reason   - the reason for refunding.
	 *
	 * @return bool - Whether the refund went through.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$charge_id = $order->get_meta( '_charge_id', true );

		try {
			if ( is_null( $amount ) ) {
				$refund = $this->payments_api_client->refund_charge( $charge_id );
			} else {
				$refund = $this->payments_api_client->refund_charge( $charge_id, WC_Payments_Utils::prepare_amount( $amount, 'USD' ) );
			}
			Tracker::track_admin( 'wcpay_edit_order_refund_success' );
		} catch ( Exception $e ) {

			$note = sprintf(
				/* translators: %1: the successfully charged amount, %2: error message */
				__( 'A refund of %1$s failed to complete: %2$s', 'woocommerce-payments' ),
				wc_price( $amount ),
				$e->getMessage()
			);

			Logger::log( $note );
			$order->add_order_note( $note );

			Tracker::track_admin( 'wcpay_edit_order_refund_failure', [ 'reason' => $note ] );
			return new WP_Error( $e->getMessage() );
		}

		if ( empty( $reason ) ) {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'A refund of %1$s was successfully processed using WooCommerce Payments.', 'woocommerce-payments' ),
				wc_price( $amount )
			);
		} else {
			$note = sprintf(
				/* translators: %1: the successfully charged amount, %2: reason */
				__( 'A refund of %1$s was successfully processed using WooCommerce Payments. Reason: %2$s', 'woocommerce-payments' ),
				wc_price( $amount ),
				$reason
			);
		}

		$order->add_order_note( $note );

		return true;
	}

	/**
	 * Overrides the original method in woo's WC_Settings_API in order to conditionally render the enabled checkbox.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 *
	 * @return string Checkbox markup or empty string.
	 */
	public function generate_checkbox_html( $key, $data ) {
		if ( 'enabled' === $key && ! $this->is_connected() ) {
			return '';
		}
		return parent::generate_checkbox_html( $key, $data );
	}

	/**
	 * Outputs the container for account status information.
	 *
	 * @return string Container markup or empty if the account is not connected.
	 */
	public function generate_account_status_html() {
		if ( ! $this->is_connected() ) {
			return '';
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<?php echo esc_html( __( 'Account status', 'woocommerce-payments' ) ); ?>
			</th>
			<td>
				<div id="wcpay-account-status-container"></div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate markup for account actions
	 */
	public function generate_account_actions_html() {
		try {
			$stripe_connected = $this->account->try_is_stripe_connected();
			if ( $stripe_connected ) {
				$description = WC_Payments_Utils::esc_interpolated_html(
					/* translators: 1) dashboard login URL */
					__( '<a>View and edit account details</a>', 'woocommerce-payments' ),
					[
						'a' => '<a href="' . WC_Payments_Account::get_login_url() . '">',
					]
				);
			} else {
				// This should never happen, if the account is not connected the merchant should have been redirected to the onboarding screen.
				// @see WC_Payments_Account::check_stripe_account_status.
				$description = esc_html__( 'Error determining the connection status.', 'woocommerce-payments' );
			}
		} catch ( Exception $e ) {
			// do not render the actions if the server is unreachable.
			$description = esc_html__( 'Error determining the connection status.', 'woocommerce-payments' );
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<?php echo esc_html( __( 'Account', 'woocommerce-payments' ) ); ?>
			</th>
			<td>
				<?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add capture and cancel actions for orders with an authorized charge.
	 *
	 * @param array $actions - Actions to make available in order actions metabox.
	 */
	public function add_order_actions( $actions ) {
		global $theorder;

		if ( $this->id !== $theorder->get_payment_method() ) {
			return $actions;
		}

		if ( 'requires_capture' !== $theorder->get_meta( '_intention_status', true ) ) {
			return $actions;
		}

		$new_actions = [
			'capture_charge'       => __( 'Capture charge', 'woocommerce-payments' ),
			'cancel_authorization' => __( 'Cancel authorization', 'woocommerce-payments' ),
		];

		return array_merge( $new_actions, $actions );
	}

	/**
	 * Capture previously authorized charge.
	 *
	 * @param WC_Order $order - Order to capture charge on.
	 */
	public function capture_charge( $order ) {
		$amount                   = $order->get_total();
		$is_authorization_expired = false;
		$status                   = null;

		try {
			$intent = $this->payments_api_client->capture_intention(
				$order->get_transaction_id(),
				WC_Payments_Utils::prepare_amount( $amount, 'USD' ),
				$this->get_level3_data_from_order( $order )
			);

			$status = $intent->get_status();

			$order->update_meta_data( '_intention_status', $status );
			$order->save();
		} catch ( WC_Payments_API_Exception $e ) {
			// Fetch the Intent to check if it's already expired and the site missed the "charge.expired" webhook.
			$intent = $this->payments_api_client->get_intent( $order->get_transaction_id() );
			if ( 'canceled' === $intent->get_status() ) {
				$is_authorization_expired = true;
			}
		}

		Tracker::track_admin( 'wcpay_merchant_captured_auth' );

		if ( 'succeeded' === $status ) {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the successfully charged amount */
					__( 'A payment of %1$s was <strong>successfully captured</strong> using WooCommerce Payments.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
			$order->payment_complete();
		} else {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the successfully charged amount */
					__( 'A capture of %1$s <strong>failed</strong> to complete.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
		}

		if ( $is_authorization_expired ) {
			WC_Payments_Utils::mark_payment_expired( $order );
		}
	}

	/**
	 * Cancel previously authorized charge.
	 *
	 * @param WC_Order $order - Order to cancel authorization on.
	 */
	public function cancel_authorization( $order ) {
		$intent = $this->payments_api_client->cancel_intention( $order->get_transaction_id() );
		$status = $intent->get_status();

		$order->update_meta_data( '_intention_status', $status );
		$order->save();

		if ( 'canceled' === $status ) {
			$order->update_status(
				'cancelled',
				WC_Payments_Utils::esc_interpolated_html(
					__( 'Payment authorization was successfully <strong>cancelled</strong>.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				)
			);
		} else {
			$order->add_order_note(
				WC_Payments_Utils::esc_interpolated_html(
					__( 'Canceling authorization <strong>failed</strong> to complete.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				)
			);
		}
	}

	/**
	 * Create the level 3 data array to send to Stripe when making a purchase.
	 *
	 * @param WC_Order $order The order that is being paid for.
	 * @return array          The level 3 data to send to Stripe.
	 */
	public function get_level3_data_from_order( $order ) {
		// Get the order items. Don't need their keys, only their values.
		// Order item IDs are used as keys in the original order items array.
		$order_items = array_values( $order->get_items() );
		$currency    = $order->get_currency();

		$process_item  = function( $item ) use ( $currency ) {
			$description     = substr( $item->get_name(), 0, 26 );
			$quantity        = $item->get_quantity();
			$unit_cost       = WC_Payments_Utils::prepare_amount( $item->get_subtotal() / $quantity, $currency );
			$tax_amount      = WC_Payments_Utils::prepare_amount( $item->get_total_tax(), $currency );
			$discount_amount = WC_Payments_Utils::prepare_amount( $item->get_subtotal() - $item->get_total(), $currency );
			$product_id      = $item->get_variation_id()
				? $item->get_variation_id()
				: $item->get_product_id();

			return (object) [
				'product_code'        => (string) $product_id, // Up to 12 characters that uniquely identify the product.
				'product_description' => $description, // Up to 26 characters long describing the product.
				'unit_cost'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
				'quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
				'tax_amount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
				'discount_amount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
			];
		};
		$items_to_send = array_map( $process_item, $order_items );

		$level3_data = [
			'merchant_reference' => (string) $order->get_id(), // An alphanumeric string of up to  characters in length. This unique value is assigned by the merchant to identify the order. Also known as an “Order ID”.
			'shipping_amount'    => WC_Payments_Utils::prepare_amount( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), $currency ), // The shipping cost, in cents, as a non-negative integer.
			'line_items'         => $items_to_send,
		];

		// The customer’s U.S. shipping ZIP code.
		$shipping_address_zip = $order->get_shipping_postcode();
		if ( WC_Payments_Utils::is_valid_us_zip_code( $shipping_address_zip ) ) {
			$level3_data['shipping_address_zip'] = $shipping_address_zip;
		}

		// The merchant’s U.S. shipping ZIP code.
		$store_postcode = get_option( 'woocommerce_store_postcode' );
		if ( WC_Payments_Utils::is_valid_us_zip_code( $store_postcode ) ) {
			$level3_data['shipping_from_zip'] = $store_postcode;
		}

		return $level3_data;
	}

	/**
	 * Handle AJAX request after authenticating payment at checkout.
	 *
	 * This function is used to update the order status after the user has
	 * been asked to authenticate their payment.
	 *
	 * This function is used for both:
	 * - regular checkout
	 * - Pay for Order page
	 *
	 * @throws Exception - If nonce is invalid.
	 */
	public function update_order_status() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_update_order_status_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ) );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : false;
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Exception( __( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ) );
			}

			$intent_id          = $order->get_meta( '_intent_id', true );
			$intent_id_received = isset( $_POST['intent_id'] )
			? sanitize_text_field( wp_unslash( $_POST['intent_id'] ) )
			/* translators: This will be used to indicate an unknown value for an ID. */
			: __( 'unknown', 'woocommerce-payments' );

			if ( empty( $intent_id ) ) {
				throw new WC_Payments_Intent_Authentication_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'empty_intent_id'
				);
			}

			// Check that the intent saved in the order matches the intent used as part of the
			// authentication process. The ID of the intent used is sent with
			// the AJAX request. We are about to use the status of the intent saved in
			// the order, so we need to make sure the intent that was used for authentication
			// is the same as the one we're using to update the status.
			if ( $intent_id !== $intent_id_received ) {
				throw new WC_Payments_Intent_Authentication_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'intent_id_mismatch'
				);
			}

			// An exception is thrown if an intent can't be found for the given intent ID.
			$intent = $this->payments_api_client->get_intent( $intent_id );

			$status = $intent->get_status();
			$amount = $order->get_total();

			switch ( $status ) {
				case 'succeeded':
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
							__( 'A payment of %1$s was <strong>successfully charged</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'code'   => '<code>',
							]
						),
						wc_price( $amount ),
						$intent_id
					);
					$order->add_order_note( $note );
					$order->payment_complete( $intent_id );
					break;
				case 'requires_capture':
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: the authorized amount, %2: transaction ID of the payment */
							__( 'A payment of %1$s was <strong>authorized</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'code'   => '<code>',
							]
						),
						wc_price( $amount ),
						$intent_id
					);
					// Save the note separately because if there is no change in status
					// then the note is not saved using WC_Order::update_status.
					$order->add_order_note( $note );
					$order->update_status( 'on-hold' );
					$order->set_transaction_id( $intent_id );
					break;
				case 'requires_payment_method':
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: the authorized amount, %2: transaction ID of the payment */
							__( 'A payment of %1$s <strong>failed</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'code'   => '<code>',
							]
						),
						wc_price( $amount ),
						$intent_id
					);
					// Save the note separately because if there is no change in status
					// then the note is not saved using WC_Order::update_status.
					$order->add_order_note( $note );
					$order->update_status( 'failed' );
					break;
			}

			if ( 'succeeded' === $status || 'requires_capture' === $status ) {
				// The order is successful, so update it to reflect that.
				$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
				$order->update_meta_data( '_intention_status', $status );
				$order->save();

				wc_reduce_stock_levels( $order_id );
				WC()->cart->empty_cart();

				// Send back redirect URL in the successful case.
				echo wp_json_encode(
					[
						'return_url' => $this->get_return_url( $order ),
					]
				);
				wp_die();
			}
		} catch ( WC_Payments_Intent_Authentication_Exception $e ) {
			$error_code = $e->get_error_code();

			switch ( $error_code ) {
				case 'intent_id_mismatch':
				case 'empty_intent_id': // The empty_intent_id case needs the same handling.
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: transaction ID of the payment or a translated string indicating an unknown ID. */
							__( 'A payment with ID <code>%1$s</code> was used in an attempt to pay for this order. This payment intent ID does not match any payments for this order, so it was ignored and the order was not updated.', 'woocommerce-payments' ),
							[
								'code' => '<code>',
							]
						),
						$intent_id_received
					);
					$order->add_order_note( $note );
					break;
			}

			// Send back error so it can be displayed to the customer.
			echo wp_json_encode(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
			wp_die();
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			echo wp_json_encode(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
			wp_die();
		}
	}

	/**
	 * Add payment method via account screen.
	 *
	 * @throws Exception If payment method is missing.
	 */
	public function add_payment_method() {
		try {

			// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			if ( ! isset( $_POST['wcpay-setup-intent'] ) ) {
				throw new Exception( __( 'A WooCommerce Payments payment method was not provided', 'woocommerce-payments' ) );
			}

			$setup_intent_id = ! empty( $_POST['wcpay-setup-intent'] ) ? wc_clean( $_POST['wcpay-setup-intent'] ) : false;
			// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash

			$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );

			if ( ! $setup_intent_id || null === $customer_id ) {
				throw new Exception( __( "We're not able to add this payment method. Please try again later", 'woocommerce-payments' ) );
			}

			$setup_intent = $this->payments_api_client->get_setup_intent( $setup_intent_id );

			if ( 'succeeded' !== $setup_intent['status'] ) {
				throw new Exception( __( 'Failed to add the provided payment method. Please try again later', 'woocommerce-payments' ) );
			}

			$payment_method        = $setup_intent['payment_method'];
			$payment_method_object = $this->payments_api_client->get_payment_method( $payment_method );
			$this->token_service->add_token_to_user( $payment_method_object, wp_get_current_user() );

			return [
				'result'   => 'success',
				'redirect' => wc_get_endpoint_url( 'payment-methods' ),
			];
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error', [ 'icon' => 'error' ] );
			Logger::log( 'Error when adding payment method: ' . $e->getMessage() );
			return [
				'result' => 'error',
			];
		}
	}

	/**
	 * Create a setup intent when adding cards using the my account page.
	 *
	 * @throws Exception - When an error occurs in setup intent creation.
	 */
	public function create_setup_intent() {
		$payment_method = $this->get_payment_method_from_request();

		// Determine the customer adding the payment method, create one if we don't have one already.
		$user        = wp_get_current_user();
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			$customer_id = $this->customer_service->create_customer_for_user( $user, "{$user->first_name} {$user->last_name}", $user->user_email );
		}

		return $this->payments_api_client->create_setup_intent(
			$payment_method,
			$customer_id
		);
	}

	/**
	 * Handle AJAX request for creating a setup intent when adding cards using the my account page.
	 *
	 * @throws Exception - If nonce or setup intent is invalid.
	 */
	public function create_setup_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_create_setup_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to add this payment method. Please refresh the page and try again.", 'woocommerce-payments' ) );
			}

			$setup_intent = $this->create_setup_intent();

			wp_send_json_success( $setup_intent, 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
		}
	}
}
