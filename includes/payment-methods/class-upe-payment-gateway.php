<?php
/**
 * Class UPE_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WCPay\Logger;
use WCPay\Payment_Information;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
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
	 * UPE Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                  - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service         - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service            - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service - Action Scheduler service instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, WC_Payments_Customer_Service $customer_service, WC_Payments_Token_Service $token_service, WC_Payments_Action_Scheduler_Service $action_scheduler_service ) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service );
		$this->method_title       = __( 'WooCommerce Payments - UPE', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via Stripe.', 'woocommerce-payments' );
		$this->title              = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->description        = __( 'You will be redirected to Stripe.', 'woocommerce-payments' );

		add_action( 'wp_ajax_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );

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
	 * Handle AJAX request for creating a payment intent for Stripe UPE.
	 *
	 * @throws Process_Payment_Exception - If nonce or setup intent is invalid.
	 */
	public function create_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_create_payment_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Process_Payment_Exception(
					__( 'Something terrible has happened. Please refresh the page and try again.', 'woocommerce-payments' ),
					'wcpay_upe_intent_error'
				);
			}

			wp_send_json_success( $this->create_payment_intent(), 200 );
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
	 * Creates payment intent using current cart and store details.
	 *
	 * @return array
	 */
	public function create_payment_intent() {
		$amount         = WC()->cart->get_total( false );
		$currency       = get_woocommerce_currency();
		$payment_intent = $this->payments_api_client->create_intention(
			WC_Payments_Utils::prepare_amount( $amount, $currency ),
			strtolower( $currency ),
			$this->get_upe_enabled_payment_method_ids()
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
			$this->get_upe_enabled_payment_method_ids()
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
			if ( ! empty( $_GET['setup_intent_client_secret'] ) & ! empty( $_GET['setup_intent'] ) & ! empty( $_GET['redirect_status'] ) ) {
				if ( 'succeeded' === $_GET['redirect_status'] ) {
					wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-payments' ) );
					$user = wp_get_current_user();
					$this->customer_service->clear_cached_payment_methods_for_user( $user->ID );
				}
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
			$payment_method         = $intent->get_payment_method_id();
			$payment_method_details = $intent->get_payment_method_details();

			$error = $intent->get_last_payment_error();
			if ( ! empty( $error ) ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'upe_payment_intent_error'
				);
			} else {
				if ( $save_payment_method ) {
					try {
						$token = $this->token_service->add_payment_method_to_user( $payment_method, $user );
						$this->add_token_to_order( $order, $token );
					} catch ( Exception $e ) {
						// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
						Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
					}
				}

				$this->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method, $customer_id, $charge_id, $currency );
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
		$payment_fields                      = parent::get_payment_fields_js_config();
		$payment_fields['accountDescriptor'] = $this->get_account_statement_descriptor();
		$payment_fields['paymentMethodsURL'] = wc_get_account_endpoint_url( 'payment-methods' );

		return $payment_fields;
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

		switch ( $payment_method_details['type'] ) {
			case 'card':
				$details       = $payment_method_details['card'];
				$funding_types = [
					'credit'  => __( 'credit', 'woocommerce-payments' ),
					'debit'   => __( 'debit', 'woocommerce-payments' ),
					'prepaid' => __( 'prepaid', 'woocommerce-payments' ),
					'unknown' => __( 'unknown', 'woocommerce-payments' ),
				];

				$payment_method_title = sprintf(
					// Translators: %1$s card brand, %2$s card funding (prepaid, credit, etc.).
					__( '%1$s %2$s card', 'woocommerce-payments' ),
					ucfirst( $details['network'] ),
					$funding_types[ $details['funding'] ]
				);
				break;

			case 'giropay':
				$payment_method_title = 'Giropay';
				break;

			case 'sepa_debit':
				$payment_method_title = 'SEPA Direct Debit';
				break;

			case 'sofort':
				$payment_method_title = 'Sofort';
				break;

			default:
				return;
		}

		$order->set_payment_method_title( "$payment_method_title (WooCommerce Payments)" );
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
			if ( $this->is_saved_cards_enabled() ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_payments_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
				$this->save_payment_method_checkbox( $force_save_payment );
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
}
