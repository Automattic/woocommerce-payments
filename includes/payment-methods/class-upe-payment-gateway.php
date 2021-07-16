<?php
/**
 * Class UPE_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Order;
use WP_User;
use WCPay\Exceptions\Add_Payment_Method_Exception;
use WCPay\Logger;
use WCPay\Payment_Information;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payment_Token_CC;
use WC_Payments;
use WC_Payments_Utils;

use Exception;
use WCPay\Exceptions\Process_Payment_Exception;


/**
 * UPE Payment method extended from WCPay generic Gateway.
 */
class UPE_Payment_Gateway extends WC_Payment_Gateway_WCPay {
	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments';

	const METHOD_ENABLED_KEY = 'enabled';

	/**
	 * Array mapping payment method string IDs to classes
	 *
	 * @var array
	 */
	protected $payment_methods = [];

	/**
	 * UPE Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                  - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service         - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service            - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service - Action Scheduler service instance.
	 * @param array                                $payment_methods          - Array of UPE payment methods.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, WC_Payments_Customer_Service $customer_service, WC_Payments_Token_Service $token_service, WC_Payments_Action_Scheduler_Service $action_scheduler_service, array $payment_methods ) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service );
		$this->method_title       = __( 'WooCommerce Payments - UPE', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via Stripe.', 'woocommerce-payments' );
		$this->title              = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->description        = __( 'You will be redirected to Stripe.', 'woocommerce-payments' );
		$this->payment_methods    = $payment_methods;

		add_action( 'wp_ajax_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );

		add_action( 'wp_ajax_update_payment_intent', [ $this, 'update_payment_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_update_payment_intent', [ $this, 'update_payment_intent_ajax' ] );

		add_action( 'wp_ajax_init_setup_intent', [ $this, 'init_setup_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_init_setup_intent', [ $this, 'init_setup_intent_ajax' ] );

		add_action( 'wp', [ $this, 'maybe_process_upe_redirect' ] );
	}

	/**
	 * Registers all scripts, necessary for the gateway.
	 */
	public function register_scripts() {
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
			'wcpay-upe-checkout',
			plugins_url( 'dist/upe_checkout.js', WCPAY_PLUGIN_FILE ),
			[ 'stripe', 'wc-checkout' ],
			WC_Payments::get_file_version( 'dist/upe_checkout.js' ),
			true
		);
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

			$order_id = isset( $_POST['wcpay_order_id'] ) ? absint( $_POST['wcpay_order_id'] ) : null;

			$payment_intent_id = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : '';

			$save_payment_method = isset( $_POST['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_POST['save_payment_method'] ) ) : false;

			wp_send_json_success( $this->update_payment_intent( $payment_intent_id, $order_id, $save_payment_method ), 200 );
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
	 * Updates payment intent to be able to save payment method.
	 *
	 * @param {string}  $payment_intent_id The id of the payment intent to update.
	 * @param {int}     $order_id The id of the order if intent created from Order.
	 * @param {boolean} $save_payment_method True if saving the payment method.
	 *
	 * @return array|null An array with result of the update, or nothing
	 */
	public function update_payment_intent( $payment_intent_id = '', $order_id = null, $save_payment_method = false ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$amount   = $order->get_total();
		$currency = $order->get_currency();

		if ( $payment_intent_id ) {
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

			$this->payments_api_client->update_intention(
				$payment_intent_id,
				WC_Payments_Utils::prepare_amount( $amount, $currency ),
				strtolower( $currency ),
				$save_payment_method,
				$customer_id,
				$this->get_level3_data_from_order( $this->account->get_account_country(), $order )
			);
		}

		return [
			'success' => true,
		];
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
			$order_id = isset( $_POST['wcpay_order_id'] ) ? absint( $_POST['wcpay_order_id'] ) : null;

			wp_send_json_success( $this->create_payment_intent( $order_id ), 200 );
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
	 * Creates payment intent using current cart or order and store details.
	 *
	 * @param {int} $order_id The id of the order if intent created from Order.
	 *
	 * @return array
	 */
	public function create_payment_intent( $order_id = null ) {
		$amount = WC()->cart->get_total( false );
		$order  = wc_get_order( $order_id );
		if ( is_a( $order, 'WC_Order' ) ) {
			$amount = $order->get_total();
		}
		$currency       = get_woocommerce_currency();
		$payment_intent = $this->payments_api_client->create_intention(
			WC_Payments_Utils::prepare_amount( $amount, $currency ),
			strtolower( $currency ),
			array_values( array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_at_checkout' ] ) ),
			$order_id ?? 0
		);
		return [
			'id'            => $payment_intent->get_id(),
			'client_secret' => $payment_intent->get_client_secret(),
		];
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

			wp_send_json_success( $this->create_setup_intent(), 200 );
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
	 * Creates setup intent without confirmation.
	 *
	 * @return array
	 */
	public function create_setup_intent() {
		// Determine the customer managing the payment methods, create one if we don't have one already.
		$user        = wp_get_current_user();
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			$customer_data = WC_Payments_Customer_Service::map_customer_data( null, new \WC_Customer( $user->ID ) );
			$customer_id   = $this->customer_service->create_customer_for_user( $user, $customer_data );
		}

		$setup_intent = $this->payments_api_client->create_setup_intention(
			$customer_id,
			array_values( array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] ) )
		);
		return [
			'id'            => $setup_intent['id'],
			'client_secret' => $setup_intent['client_secret'],
		];
	}

	/**
	 * Create and confirm payment intent. Saved payment methods do not follow UPE workflow.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment_using_saved_method( $order_id ) {
		return parent::process_payment( $order_id );
	}

	/**
	 * Update payment intent for completed checkout and return redirect URL for Stripe to confirm payment.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id ) {
		$payment_intent_id   = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order               = wc_get_order( $order_id );
		$amount              = $order->get_total();
		$currency            = $order->get_currency();
		$save_payment_method = ! empty( $_POST[ 'wc-' . static::GATEWAY_ID . '-new-payment-method' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token               = Payment_Information::get_token_from_request( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $payment_intent_id ) {
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

			$this->payments_api_client->update_intention(
				$payment_intent_id,
				WC_Payments_Utils::prepare_amount( $amount, $currency ),
				strtolower( $currency ),
				$save_payment_method,
				$customer_id,
				$this->get_level3_data_from_order( $this->account->get_account_country(), $order )
			);
		} elseif ( $token ) {
			return $this->process_payment_using_saved_method( $order_id );
		}

		return [
			'result'       => 'success',
			'redirect_url' => wp_sanitize_redirect(
				esc_url_raw(
					add_query_arg(
						[
							'order_id'            => $order_id,
							'wc_payment_method'   => self::GATEWAY_ID,
							'_wpnonce'            => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
							'save_payment_method' => $save_payment_method ? 'yes' : 'no',
						],
						$this->get_return_url( $order )
					)
				)
			),
		];
	}

	/**
	 * Returns true when viewing payment methods page.
	 *
	 * @return bool
	 */
	private function is_payment_methods_page() {
		global $wp;

		$page_id = wc_get_page_id( 'myaccount' );

		return ( $page_id && is_page( $page_id ) && ( isset( $wp->query_vars['payment-methods'] ) ) );
	}

	/**
	 * Check for a redirect payment method on order received page or setup intent on payment methods page.
	 */
	public function maybe_process_upe_redirect() {
		if ( $this->is_payment_methods_page() ) {
			// If a payment method was added using UPE, we need to clear the cache and notify the user.
			if ( $this->is_setup_intent_success_creation_redirection() ) {
					wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-payments' ) );
					$user = wp_get_current_user();
					$this->customer_service->clear_cached_payment_methods_for_user( $user->ID );
			}
			return;
		}

		if ( ! is_order_received_page() ) {
			return;
		}

		$payment_method = isset( $_GET['wc_payment_method'] ) ? wc_clean( wp_unslash( $_GET['wc_payment_method'] ) ) : '';
		if ( self::GATEWAY_ID !== $payment_method ) {
			return;
		}

		$is_nonce_valid = check_admin_referer( 'wcpay_process_redirect_order_nonce' );
		if ( ! $is_nonce_valid || empty( $_GET['payment_intent_client_secret'] ) || empty( $_GET['payment_intent'] ) || empty( $_GET['wc_payment_method'] ) ) {
			return;
		}

		$order_id            = isset( $_GET['order_id'] ) ? wc_clean( wp_unslash( $_GET['order_id'] ) ) : '';
		$intent_id           = isset( $_GET['payment_intent'] ) ? wc_clean( wp_unslash( $_GET['payment_intent'] ) ) : '';
		$save_payment_method = isset( $_GET['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_GET['save_payment_method'] ) ) : false;

		if ( empty( $intent_id ) || empty( $order_id ) ) {
			return;
		}

		$this->process_redirect_payment( $order_id, $intent_id, $save_payment_method );
	}

	/**
	 * Processes redirect payments.
	 *
	 * @param int    $order_id The order ID being processed.
	 * @param string $intent_id The Stripe payment intent ID for the order payment.
	 * @param bool   $save_payment_method Boolean representing whether payment method for order should be saved.
	 *
	 * @throws Process_Payment_Exception When the payment intent has an error.
	 */
	public function process_redirect_payment( $order_id, $intent_id, $save_payment_method ) {
		try {
			$order = wc_get_order( $order_id );

			if ( ! is_object( $order ) ) {
				return;
			}

			if ( $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
				return;
			}

			Logger::log( "Begin processing UPE redirect payment for order $order_id for the amount of {$order->get_total()}" );

			// Get user/customer for order.
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

			// Get payment intent to confirm status.
			$intent                 = $this->payments_api_client->get_intent( $intent_id );
			$status                 = $intent->get_status();
			$charge_id              = $intent->get_charge_id();
			$currency               = $intent->get_currency();
			$payment_method_id      = $intent->get_payment_method_id();
			$payment_method_details = $intent->get_payment_method_details();
			$payment_method_type    = $payment_method_details['type'];

			if ( ! isset( $this->payment_methods[ $payment_method_type ] ) ) {
				return;
			}
			$payment_method = $this->payment_methods[ $payment_method_type ];

			$error = $intent->get_last_payment_error();
			if ( ! empty( $error ) ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'upe_payment_intent_error'
				);
			} else {
				if ( $save_payment_method && $payment_method->is_reusable() ) {
					try {
						$token = $payment_method->get_payment_token_for_user( $user, $payment_method_id );
						$this->add_token_to_order( $order, $token );
					} catch ( Exception $e ) {
						// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
						Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
					}
				}

				$this->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method_id, $customer_id, $charge_id, $currency );
				$this->set_payment_method_title_for_order( $order, $payment_method_details );

				if ( 'requires_action' === $status ) {
					// I don't think this case should be possible, but just in case...
					$next_action = $intent->get_next_action();
					if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
						wp_safe_redirect( $next_action['redirect_to_url']['url'] );
						exit;
					} else {
						$payment_needed = 0 < $order->get_total();
						$client_secret  = $intent->get_client_secret();
						$redirect_url   = sprintf(
							'#wcpay-confirm-%s:%s:%s:%s',
							$payment_needed ? 'pi' : 'si',
							$order_id,
							$client_secret,
							wp_create_nonce( 'wcpay_update_order_status_nonce' )
						);
						wp_safe_redirect( $redirect_url );
						exit;
					}
				}
			}
		} catch ( Exception $e ) {
			Logger::log( 'Error: ' . $e->getMessage() );

			/* translators: localized exception message */
			$order->update_status( 'failed', sprintf( __( 'UPE payment failed: %s', 'woocommerce-payments' ), $e->getMessage() ) );

			wc_add_notice( $e->getMessage(), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Generates the configuration values, needed for UPE payment fields.
	 *
	 * @return array
	 */
	public function get_payment_fields_js_config() {
		$payment_fields                         = parent::get_payment_fields_js_config();
		$payment_fields['accountDescriptor']    = $this->get_account_statement_descriptor();
		$payment_fields['addPaymentReturnURL']  = wc_get_account_endpoint_url( 'payment-methods' );
		$payment_fields['gatewayId']            = self::GATEWAY_ID;
		$payment_fields['paymentMethodsConfig'] = $this->get_enabled_payment_method_config();
		$payment_fields['isCheckout']           = is_checkout();

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			if ( $this->is_subscriptions_enabled() && $this->is_changing_payment_method_for_subscription() ) {
				$payment_fields['isChangingPayment']   = true;
				$payment_fields['addPaymentReturnURL'] = esc_url_raw( home_url( add_query_arg( [] ) ) );

				if ( $this->is_setup_intent_success_creation_redirection() && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ) ) ) {
					$setup_intent_id                  = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
					$token                            = $this->create_token_from_setup_intent( $setup_intent_id, wp_get_current_user() );
					$payment_fields['newTokenFormId'] = '#wc-' . $token->get_gateway_id() . '-payment-token-' . $token->get_id();
				}
				return $payment_fields;
			}

			$payment_fields['isOrderPay'] = true;
			$order_id                     = absint( get_query_var( 'order-pay' ) );
			$payment_fields['orderId']    = $order_id;
			$order                        = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) ) {
				$payment_fields['orderReturnURL'] = esc_url_raw(
					add_query_arg(
						[
							'order_id'          => $order_id,
							'wc_payment_method' => self::GATEWAY_ID,
							'_wpnonce'          => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
						],
						$this->get_return_url( $order )
					)
				);
			}
		}
		return $payment_fields;
	}

	/**
	 * True if the request contains the values that indicates a redirection after a successful setup intent creation.
	 *
	 * @return bool
	 */
	public function is_setup_intent_success_creation_redirection() {
		return (
			! empty( $_GET['setup_intent_client_secret'] ) & ! empty( $_GET['setup_intent'] ) & ! empty( $_GET['redirect_status'] ) && 'succeeded' === $_GET['redirect_status'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Adds a token to current user from a setup intent id.
	 *
	 * @param string  $setup_intent_id ID of the setup intent.
	 * @param WP_User $user            User to add token to.
	 *
	 * @return WC_Payment_Token_CC The added token.
	 */
	public function create_token_from_setup_intent( $setup_intent_id, $user ) {
		try {
			$setup_intent      = $this->payments_api_client->get_setup_intent( $setup_intent_id );
			$payment_method_id = $setup_intent['payment_method'];
			// TODO: When adding SEPA and Sofort, we will need a new API call to get the payment method and from there get the type.
			// Leaving 'card' as a hardcoded value for now to avoid the extra API call.
			$payment_method = $this->payment_methods['card'];

			return $payment_method->get_payment_token_for_user( $user, $payment_method_id );
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error', [ 'icon' => 'error' ] );
			Logger::log( 'Error when adding payment method: ' . $e->getMessage() );
			return [
				'result' => 'error',
			];
		}
	}

	/**
	 * Set formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param WC_Order $order WC Order being processed.
	 * @param array    $payment_method_details Array of payment method details from charge.
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_details ) {
		if ( empty( $payment_method_details ) ) {
			return;
		}

		$payment_method_id = $payment_method_details['type'];
		if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
			return;
		}

		$payment_method_title = $this->payment_methods[ $payment_method_id ]->get_title( $payment_method_details );

		$order->set_payment_method_title( $payment_method_title );
		$order->save();
	}

	/**
	 * Renders the UPE input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->supports( 'tokenization' ) && is_checkout();

			$payment_fields = $this->get_payment_fields_js_config();
			wp_localize_script( 'wcpay-upe-checkout', 'wcpay_config', $payment_fields );
			wp_enqueue_script( 'wcpay-upe-checkout' );

			$prepared_customer_data = $this->get_prepared_customer_data();
			if ( ! empty( $prepared_customer_data ) ) {
				wp_localize_script( 'wcpay-upe-checkout', 'wcpayCustomerData', $prepared_customer_data );
			}

			wp_enqueue_style(
				'wcpay-upe-checkout',
				plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/checkout.css' )
			);

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

			<?php if ( $this->is_in_test_mode() ) : ?>
				<p class="testmode-info">
				<?php
					echo WC_Payments_Utils::esc_interpolated_html(
						/* translators: link to Stripe testing page */
						__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a>here</a>.', 'woocommerce-payments' ),
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
				$this->saved_payment_methods();
			}
			?>

			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-upe-form" class="wc-upe-form wc-payment-form">
				<div id="wcpay-upe-element"></div>
				<div id="wcpay-upe-errors" role="alert"></div>
				<input id="wcpay-payment-method-upe" type="hidden" name="wcpay-payment-method-upe" />

			<?php
			$methods_enabled_for_saved_payments = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] );
			if ( $this->is_saved_cards_enabled() && ! empty( $methods_enabled_for_saved_payments ) ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_payments_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
				if ( is_user_logged_in() || $force_save_payment ) {
					$this->save_payment_method_checkbox( $force_save_payment );
				}
			}
			?>

			</fieldset>
			<?php
		} catch ( Exception $e ) {
			// Output the error message.
			Logger::log( 'Error: ' . $e->getMessage() );
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
	 * Returns the list of available payment method types for UPE.
	 * See https://stripe.com/docs/stripe-js/payment-element#web-create-payment-intent for a complete list.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		$methods = parent::get_upe_available_payment_methods();

		$methods[] = 'giropay';
		$methods[] = 'sepa_debit';

		return array_values(
			apply_filters(
				'wcpay_upe_available_payment_methods',
				$methods
			)
		);
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @return array
	 */
	private function get_enabled_payment_method_config() {
		$settings                = [];
		$enabled_payment_methods = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_at_checkout' ] );

		foreach ( $enabled_payment_methods as $payment_method ) {
			$settings[ $payment_method ] = [
				'isReusable' => $this->payment_methods[ $payment_method ]->is_reusable(),
			];
		}

		return $settings;
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods supported with current checkout
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	private function is_enabled_at_checkout( $payment_method_id ) {
		if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
			return false;
		}
		return $this->payment_methods[ $payment_method_id ]->is_enabled_at_checkout();
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods that support saved payments
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	private function is_enabled_for_saved_payments( $payment_method_id ) {
		if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
			return false;
		}
		return $this->payment_methods[ $payment_method_id ]->is_reusable();
	}
}
