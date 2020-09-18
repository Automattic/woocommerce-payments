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
use WCPay\Payment_Information;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Payment_Capture_Type;
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
			'enabled'                      => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_details'              => [
				'type' => 'account_actions',
			],
			'account_status'               => [
				'type' => 'account_status',
			],
			'account_statement_descriptor' => [
				'type'        => 'account_statement_descriptor',
				'title'       => __( 'Customer bank statement', 'woocommerce-payments' ),
				'description' => __( 'Edit the way your store name appears on your customers’ bank statements.', 'woocommerce-payments' ),
			],
			'manual_capture'               => [
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Charge must be captured within 7 days of authorization, otherwise the authorization and order will be canceled.', 'woocommerce-payments' ),
				'default'     => 'no',
			],
			'test_mode'                    => [
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'enable_logging'               => [
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
		];

		// Load the settings.
		$this->init_settings();

		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, [ $this, 'sanitize_plugin_settings' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'admin_notices', [ $this, 'display_errors' ], 9999 );
		add_action( 'woocommerce_order_actions', [ $this, 'add_order_actions' ] );
		add_action( 'woocommerce_order_action_capture_charge', [ $this, 'capture_charge' ] );
		add_action( 'woocommerce_order_action_cancel_authorization', [ $this, 'cancel_authorization' ] );

		add_action( 'wp_ajax_update_order_status', [ $this, 'update_order_status' ] );
		add_action( 'wp_ajax_nopriv_update_order_status', [ $this, 'update_order_status' ] );

		add_action( 'wp_ajax_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );

		// Update the current request logged_in cookie after a guest user is created to avoid nonce inconsistencies.
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
	}

	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 *
	 * @param string $cookie New cookie value.
	 */
	public function set_cookie_on_current_request( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
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

			$checkout_script_src_url      = plugins_url( 'dist/checkout.js', WCPAY_PLUGIN_FILE );
			$checkout_script_asset_path   = WCPAY_ABSPATH . 'dist/checkout.asset.php';
			$checkout_script_asset        = file_exists( $checkout_script_asset_path ) ? require_once $checkout_script_asset_path : [ 'dependencies' => [] ];
			$checkout_script_dependencies = array_merge(
				$checkout_script_asset['dependencies'],
				[ 'stripe', 'wc-checkout' ]
			);
			wp_register_script(
				'WCPAY_CHECKOUT',
				$checkout_script_src_url,
				$checkout_script_dependencies,
				WC_Payments::get_file_version( 'dist/checkout.js' ),
				true
			);

			wp_localize_script( 'WCPAY_CHECKOUT', 'wcpay_config', $js_config );
			wp_enqueue_script( 'WCPAY_CHECKOUT' );

			wp_enqueue_style(
				'WCPAY_CHECKOUT',
				plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/checkout.css' )
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
	 * @param int  $order_id Order ID to process the payment for.
	 * @param bool $force_save_payment_method Whether this is a one-off payment (false) or it's the first installment of a recurring payment (true).
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id, $force_save_payment_method = false ) {
		$order = wc_get_order( $order_id );

		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payment_information = Payment_Information::from_payment_request( $_POST, $order, Payment_Initiated_By::CUSTOMER(), $this->get_capture_type() );

			return $this->process_payment_for_order( WC()->cart, $payment_information, $force_save_payment_method );
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
	 * Process the payment for a given order.
	 *
	 * @param WC_Cart                   $cart Cart.
	 * @param WCPay\Payment_Information $payment_information Payment info.
	 * @param bool                      $force_save_payment_method Whether this is a one-off payment (false) or it's the first installment of a recurring payment (true).
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 * @throws WC_Payments_API_Exception Error processing the payment.
	 */
	public function process_payment_for_order( $cart, $payment_information, $force_save_payment_method = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$save_payment_method = ! $payment_information->is_using_saved_payment_method() && ( ! empty( $_POST[ 'wc-' . self::GATEWAY_ID . '-new-payment-method' ] ) || $force_save_payment_method );
		$order               = $payment_information->get_order();

		$order_id = $order->get_id();
		$amount   = $order->get_total();
		$user     = $order->get_user() ?? wp_get_current_user();
		$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email    = sanitize_email( $order->get_billing_email() );
		$metadata = [
			'customer_name'  => $name,
			'customer_email' => $email,
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order_id,
		];

		// We only force save the card during subscriptions.
		// TODO: This is a bit flawed; make these 2 functionalities distinct.
		if ( $force_save_payment_method ) {
			$metadata['payment_type'] = 'recurring';
		}

		// Determine the customer making the payment, create one if we don't have one already.
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );

		if ( null === $customer_id ) {
			// Create a new customer.
			$customer_id = $this->customer_service->create_customer_for_user( $user, $name, $email );
		} else {
			// Update the existing customer with the current details. In the event the old customer can't be
			// found a new one is created, so we update the customer ID here as well.
			$customer_id = $this->customer_service->update_customer_for_user( $customer_id, $user, $name, $email );
		}

		// Update saved payment method information with checkout values, as some saved methods might not have billing details.
		if ( $payment_information->is_using_saved_payment_method() ) {
			try {
				$this->customer_service->update_payment_method_with_billing_details_from_order( $payment_information->get_payment_method(), $order );
			} catch ( Exception $e ) {
				// If updating the payment method fails, log the error message but catch the error to avoid crashing the checkout flow.
				Logger::log( 'Error when updating saved payment method: ' . $e->getMessage() );
			}
		}

		$intent_failed = false;

		// In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
		if ( 0 === $amount && ! $save_payment_method ) {
			$order->payment_complete();
		}

		if ( $amount > 0 ) {
			// Create intention, try to confirm it & capture the charge (if 3DS is not required).
			$intent = $this->payments_api_client->create_and_confirm_intention(
				WC_Payments_Utils::prepare_amount( $amount, 'USD' ),
				'usd',
				$payment_information->get_payment_method(),
				$customer_id,
				$payment_information->is_using_manual_capture(),
				$save_payment_method,
				$metadata,
				$this->get_level3_data_from_order( $order ),
				$payment_information->is_merchant_initiated()
			);

			$intent_id = $intent->get_id();
			$status    = $intent->get_status();
			$charge_id = $intent->get_charge_id();
		} else {
			// For $0 orders, we need to save the payment method using a setup intent.
			$intent = $this->payments_api_client->create_setup_intent(
				$payment_information->get_payment_method(),
				$customer_id,
				'true'
			);

			$intent_id = $intent['id'];
			$status    = $intent['status'];
			$charge_id = '';

			// In SCA cases the setup intent status might be requires_action and we should display the authentication modal.
			// For now, since we're not supporting SCA cards, we can ignore that status.
			if ( 'succeeded' !== $status ) {
				throw new Exception( __( 'Failed to add the provided payment method. Please try again later', 'woocommerce-payments' ) );
			}
		}

		if ( ! empty( $intent ) ) {
			if ( 'succeeded' !== $status && 'requires_capture' !== $status ) {
				$intent_failed = true;
			}

			if ( $save_payment_method && ! $intent_failed ) {
				try {
					$token = $this->token_service->add_payment_method_to_user( $payment_information->get_payment_method(), $user );
					$payment_information->set_token( $token );
				} catch ( Exception $e ) {
					// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
					Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
				}
			}

			if ( $payment_information->is_using_saved_payment_method() ) {
				$token = $payment_information->get_payment_token();
				$this->add_token_to_order( $order, $token );
			}

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
					$order->update_meta_data( '_charge_id', $charge_id );
					$order->update_meta_data( '_intention_status', $status );
					$order->save();

					if ( $amount > 0 ) {
						$order->add_order_note( $note );
					}
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
					$order->update_meta_data( '_charge_id', $charge_id );
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
						// Include a new nonce for update_order_status to ensure the update order
						// status call works when a guest user creates an account during checkout.
						'redirect' => sprintf(
							'#wcpay-confirm-pi:%s:%s:%s',
							$order_id,
							$intent->get_client_secret(),
							wp_create_nonce( 'wcpay_update_order_status_nonce' )
						),
					];
			}
		}

		wc_reduce_stock_levels( $order_id );
		if ( isset( $cart ) ) {
			$cart->empty_cart();
		}

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Saves the payment token to the order.
	 *
	 * @param WC_Order         $order The order.
	 * @param WC_Payment_Token $token The token to save.
	 */
	public function add_token_to_order( $order, $token ) {
		$payment_token = $this->get_payment_token( $order );

		// This could lead to tokens being saved twice in an order's payment tokens, but it is needed so that shoppers
		// may re-use a previous card for the same subscription, as we consider the last token to be the active one.
		// We can't remove the previous entry for the token because WC_Order does not support removal of tokens [1] and
		// we can't delete the token as it might be used somewhere else.
		// [1] https://github.com/woocommerce/woocommerce/issues/11857.
		if ( is_null( $payment_token ) || $token->get_id() !== $payment_token->get_id() ) {
			$order->add_payment_token( $token );
		}
	}

	/**
	 * Retrieve payment token from a subscription or order.
	 *
	 * @param WC_Order $order Order or subscription object.
	 *
	 * @return null|WC_Payment_Token Last token associated with order or subscription.
	 */
	protected function get_payment_token( $order ) {
		$order_tokens = $order->get_payment_tokens();
		$token_id     = end( $order_tokens );
		return ! $token_id ? null : WC_Payment_Tokens::get( $token_id );
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

		$in_dev_mode = $this->is_in_dev_mode();

		if ( 'test_mode' === $key && $in_dev_mode ) {
			$data['custom_attributes']['disabled'] = 'disabled';
			$data['label']                         = __( 'Dev mode is active so all transactions will be in test mode. This setting is only available to live accounts.', 'woocommerce-payments' );
		}

		if ( 'enable_logging' === $key && $in_dev_mode ) {
			$data['custom_attributes']['disabled'] = 'disabled';
			$data['label']                         = __( 'Dev mode is active so logging is on by default.', 'woocommerce-payments' );
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
	 * Generates markup for account statement descriptor field.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 *
	 * @return string
	 */
	public function generate_account_statement_descriptor_html( $key, $data ) {
		if ( ! $this->is_connected() ) {
			return '';
		}

		return parent::generate_text_html( $key, $data );
	}

	/**
	 * Get option from DB or connected account.
	 *
	 * Overrides parent method to retrieve some options from connected account.
	 *
	 * @param  string $key Option key.
	 * @param  mixed  $empty_value Value when empty.
	 * @return string The value specified for the option or a default value for the option.
	 */
	public function get_option( $key, $empty_value = null ) {
		switch ( $key ) {
			case 'account_statement_descriptor':
				return $this->get_account_statement_descriptor();
			default:
				return parent::get_option( $key, $empty_value );
		}
	}

	/**
	 * Get payment capture type from WCPay settings.
	 *
	 * @return Payment_Capture_Type MANUAL or AUTOMATIC depending on the settings.
	 */
	private function get_capture_type() {
		return 'yes' === $this->get_option( 'manual_capture' ) ? Payment_Capture_Type::MANUAL() : Payment_Capture_Type::AUTOMATIC();
	}

	/**
	 * Sanitizes plugin settings before saving them in site's DB.
	 *
	 * Filters out some values stored in connected account.
	 *
	 * @param array $settings Plugin settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_plugin_settings( $settings ) {
		if ( isset( $settings['account_statement_descriptor'] ) ) {
			$this->update_statement_descriptor( $settings['account_statement_descriptor'] );
			unset( $settings['account_statement_descriptor'] );
		}

		return $settings;
	}

	/**
	 * Gets connected account statement descriptor.
	 *
	 * @param mixed $empty_value Empty value to return when not connected or fails to fetch account descriptor.
	 *
	 * @return string Statement descriptor of default value.
	 */
	private function get_account_statement_descriptor( $empty_value = null ) {
		try {
			if ( ! $this->is_connected() ) {
				return $empty_value;
			}

			return $this->account->get_statement_descriptor();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account statement descriptor.' . $e );
			return $empty_value;
		}
	}

	/**
	 * Handles statement descriptor update when plugin settings saved.
	 *
	 * Adds error message to display in admin notices in case of failure.
	 *
	 * @param string $statement_descriptor Statement descriptor value.
	 */
	private function update_statement_descriptor( $statement_descriptor ) {
		if ( empty( $statement_descriptor ) ) {
			return;
		}

		$account_settings = [
			'statement_descriptor' => $statement_descriptor,
		];
		$error_message    = $this->account->update_stripe_account( $account_settings );

		if ( is_string( $error_message ) ) {
			$msg = __( 'Failed to update Statement descriptor. ', 'woocommerce-payments' ) . $error_message;
			$this->add_error( $msg );
		}
	}

	/**
	 * Validates statement descriptor value
	 *
	 * @param  string $key Field key.
	 * @param  string $value Posted Value.
	 *
	 * @return string Sanitized statement descriptor.
	 * @throws Exception When statement descriptor is invalid.
	 */
	public function validate_account_statement_descriptor_field( $key, $value ) {
		// Since the value is escaped, and we are saving in a place that does not require escaping, apply stripslashes.
		$value = stripslashes( $value );

		// Validation can be done with a single regex but splitting into multiple for better readability.
		$valid_length   = '/^.{5,22}$/';
		$has_one_letter = '/^.*[a-zA-Z]+/';
		$no_specials    = '/^[^*"\'<>]*$/';

		if (
			! preg_match( $valid_length, $value ) ||
			! preg_match( $has_one_letter, $value ) ||
			! preg_match( $no_specials, $value )
		) {
			throw new Exception( __( 'Customer bank statement is invalid. Statement should be between 5 and 22 characters long, contain at least single Latin character and does not contain special characters: \' " * &lt; &gt;', 'woocommerce-payments' ) );
		}

		return $value;
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

			$payment_method_id = isset( $_POST['payment_method_id'] ) ? wc_clean( wp_unslash( $_POST['payment_method_id'] ) ) : '';

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

					// The order is successful, so update it to reflect that.
					$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
					$order->update_meta_data( '_intention_status', $status );
					$order->save();

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

					// The order is successful, so update it to reflect that.
					$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
					$order->update_meta_data( '_intention_status', $status );
					$order->save();

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
				wc_reduce_stock_levels( $order_id );
				WC()->cart->empty_cart();

				if ( ! empty( $payment_method_id ) ) {
					try {
						// TODO: Add token to subscriptions related to this order.
						$this->token_service->add_payment_method_to_user( $payment_method_id, wp_get_current_user() );
					} catch ( Exception $e ) {
						// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
						Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
					}
				}

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

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST['wcpay-setup-intent'] ) ) {
				throw new Exception( __( 'A WooCommerce Payments payment method was not provided', 'woocommerce-payments' ) );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$setup_intent_id = ! empty( $_POST['wcpay-setup-intent'] ) ? wc_clean( $_POST['wcpay-setup-intent'] ) : false;

			$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );

			if ( ! $setup_intent_id || null === $customer_id ) {
				throw new Exception( __( "We're not able to add this payment method. Please try again later", 'woocommerce-payments' ) );
			}

			$setup_intent = $this->payments_api_client->get_setup_intent( $setup_intent_id );

			if ( 'succeeded' !== $setup_intent['status'] ) {
				throw new Exception( __( 'Failed to add the provided payment method. Please try again later', 'woocommerce-payments' ) );
			}

			$payment_method = $setup_intent['payment_method'];
			$this->token_service->add_payment_method_to_user( $payment_method, wp_get_current_user() );

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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_information = Payment_Information::from_payment_request( $_POST );

		// Determine the customer adding the payment method, create one if we don't have one already.
		$user        = wp_get_current_user();
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			$customer_id = $this->customer_service->create_customer_for_user( $user, "{$user->first_name} {$user->last_name}", $user->user_email );
		}

		return $this->payments_api_client->create_setup_intent(
			$payment_information->get_payment_method(),
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

	/**
	 * Add a url to the admin order page that links directly to the transactions detail view.
	 *
	 * @since 1.4.0
	 *
	 * @param WC_Order $order The context passed into this function when the user view the order details page in WordPress admin.
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$charge_id = $order->get_meta( '_charge_id' );

		if ( empty( $charge_id ) ) {
			return '';
		}

		return add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => '/payments/transactions/details&',
				'id'   => $charge_id,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns a formatted token list for a user.
	 *
	 * @param int $user_id The user ID.
	 */
	protected function get_user_formatted_tokens_array( $user_id ) {
		$tokens = WC_Payment_Tokens::get_tokens(
			[
				'user_id'    => $user_id,
				'gateway_id' => self::GATEWAY_ID,
			]
		);
		return array_map(
			function ( $token ) {
				return [
					'tokenId'         => $token->get_id(),
					'paymentMethodId' => $token->get_token(),
					'brand'           => $token->get_card_type(),
					'last4'           => $token->get_last4(),
					'expiryMonth'     => $token->get_expiry_month(),
					'expiryYear'      => $token->get_expiry_year(),
					'isDefault'       => $token->get_is_default(),
					'displayName'     => $token->get_display_name(),
				];
			},
			array_values( $tokens )
		);
	}
}
