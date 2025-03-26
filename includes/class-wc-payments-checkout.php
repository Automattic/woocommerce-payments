<?php
/**
 * Class WC_Payments_Checkout
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Exception;
use Jetpack_Options;
use WC_AJAX;
use WC_Checkout;
use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Customer_Service;
use WC_Payments_Fraud_Service;
use WC_Payments_Utils;
use WC_Payments_Features;
use WCPay\Constants\Payment_Method;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WC_Payment_Gateway_WCPay;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\WooPay\WooPay_Session;

/**
 * WC_Payments_Checkout
 */
class WC_Payments_Checkout {

	/**
	 * WC Payments Gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * WooPay Utilities.
	 *
	 * @var WooPay_Utilities
	 */
	protected $woopay_util;

	/**
	 * WC Payments Account.
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * WC Payments Customer Service
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * WC_Payments_Fraud_Service instance to get information about fraud services.
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	protected $fraud_service;

	/**
	 * Construct.
	 *
	 * @param WC_Payment_Gateway_WCPay     $gateway          WC Payment Gateway.
	 * @param WooPay_Utilities             $woopay_util      WooPay Utilities.
	 * @param WC_Payments_Account          $account          WC Payments Account.
	 * @param WC_Payments_Customer_Service $customer_service WC Payments Customer Service.
	 * @param WC_Payments_Fraud_Service    $fraud_service    Fraud service instance.
	 */
	public function __construct(
		WC_Payment_Gateway_WCPay $gateway,
		WooPay_Utilities $woopay_util,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Fraud_Service $fraud_service
	) {
		$this->gateway          = $gateway;
		$this->woopay_util      = $woopay_util;
		$this->account          = $account;
		$this->customer_service = $customer_service;
		$this->fraud_service    = $fraud_service;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'wc_payments_set_gateway', [ $this, 'set_gateway' ] );
		add_action( 'wc_payments_add_upe_payment_fields', [ $this, 'payment_fields' ] );
		add_action( 'wp', [ $this->gateway, 'maybe_process_upe_redirect' ] );
		add_action( 'wp_ajax_save_upe_appearance', [ $this->gateway, 'save_upe_appearance_ajax' ] );
		add_action( 'wp_ajax_nopriv_save_upe_appearance', [ $this->gateway, 'save_upe_appearance_ajax' ] );
		add_action( 'switch_theme', [ $this->gateway, 'clear_upe_appearance_transient' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ $this->gateway, 'clear_upe_appearance_transient' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts_for_zero_order_total' ], 11 );
		add_action( 'woocommerce_after_checkout_form', [ $this, 'maybe_load_checkout_scripts' ] );
	}

	/**
	 * Registers all scripts, necessary for the gateway.
	 */
	public function register_scripts() {
		if ( wp_script_is( 'wcpay-upe-checkout', 'enqueued' ) ) {
			return;
		}
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

		$script_dependencies = [ 'stripe', 'wc-checkout', 'wp-i18n' ];

		if ( $this->gateway->supports( 'tokenization' ) ) {
			$script_dependencies[] = 'woocommerce-tokenization-form';
		}

		Fraud_Prevention_Service::maybe_append_fraud_prevention_token();

		WC_Payments::register_script_with_dependencies( 'wcpay-upe-checkout', 'dist/checkout', $script_dependencies );
	}

	/**
	 * Registers scripts necessary for the gateway, even when cart order total is 0.
	 * This is done so that if the cart is modified via AJAX on checkout,
	 * the scripts are still loaded.
	 */
	public function register_scripts_for_zero_order_total() {
		if (
			isset( WC()->cart ) &&
			! WC()->cart->is_empty() &&
			! WC()->cart->needs_payment() &&
			is_checkout() &&
			! has_block( 'woocommerce/checkout' ) &&
			! wp_script_is( 'wcpay-upe-checkout', 'enqueued' )
		) {
			$this->load_checkout_scripts();
		}
	}

	/**
	 * Sometimes the filters can remove the payment gateway from the checkout page which results in the payment fields not being displayed.
	 * This could prevent loading of the payment fields (checkout) scripts.
	 * This function ensures that these scripts are loaded.
	 */
	public function maybe_load_checkout_scripts() {
		if ( is_checkout() && ! wp_script_is( 'wcpay-upe-checkout', 'enqueued' ) ) {
			$this->load_checkout_scripts();
		}
	}

	/**
	 * Generates the configuration values, needed for payment fields.
	 *
	 * Isolated as a separate method in order to be available both
	 * during the classic checkout, as well as the checkout block.
	 *
	 * @return array
	 */
	public function get_payment_fields_js_config() {
		// Needed to init the hooks.
		WC_Checkout::instance();

		// The registered card gateway is more reliable than $this->gateway, but if it isn't available for any reason, fall back to the gateway provided to this checkout class.
		$gateway = WC_Payments::get_gateway() ?? $this->gateway;

		$js_config = [
			'publishableKey'                    => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
			'testMode'                          => WC_Payments::mode()->is_test(),
			'accountId'                         => $this->account->get_stripe_account_id(),
			'ajaxUrl'                           => admin_url( 'admin-ajax.php' ),
			'wcAjaxUrl'                         => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'createSetupIntentNonce'            => wp_create_nonce( 'wcpay_create_setup_intent_nonce' ),
			'initWooPayNonce'                   => wp_create_nonce( 'wcpay_init_woopay_nonce' ),
			'saveUPEAppearanceNonce'            => wp_create_nonce( 'wcpay_save_upe_appearance_nonce' ),
			'genericErrorMessage'               => __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-payments' ),
			'fraudServices'                     => $this->fraud_service->get_fraud_services_config(),
			'features'                          => $this->gateway->supports,
			'forceNetworkSavedCards'            => WC_Payments::is_network_saved_cards_enabled() || $gateway->should_use_stripe_platform_on_checkout_page(),
			'locale'                            => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			'isPreview'                         => is_preview(),
			'isSavedCardsEnabled'               => $this->gateway->is_saved_cards_enabled(),
			'isPaymentRequestEnabled'           => $this->gateway->is_payment_request_enabled(),
			'isTokenizedCartEceEnabled'         => WC_Payments_Features::is_tokenized_cart_ece_enabled(),
			'isWooPayEnabled'                   => $this->woopay_util->should_enable_woopay( $this->gateway ) && $this->woopay_util->should_enable_woopay_on_cart_or_checkout(),
			'isWoopayExpressCheckoutEnabled'    => $this->woopay_util->is_woopay_express_checkout_enabled(),
			'isWoopayFirstPartyAuthEnabled'     => $this->woopay_util->is_woopay_first_party_auth_enabled(),
			'isWooPayEmailInputEnabled'         => $this->woopay_util->is_woopay_email_input_enabled(),
			'isWooPayDirectCheckoutEnabled'     => WC_Payments_Features::is_woopay_direct_checkout_enabled(),
			'isWooPayGlobalThemeSupportEnabled' => $this->gateway->is_woopay_global_theme_support_enabled(),
			'woopayHost'                        => WooPay_Utilities::get_woopay_url(),
			'platformTrackerNonce'              => wp_create_nonce( 'platform_tracks_nonce' ),
			'accountIdForIntentConfirmation'    => apply_filters( 'wc_payments_account_id_for_intent_confirmation', '' ),
			'wcpayVersionNumber'                => WCPAY_VERSION_NUMBER,
			'woopaySignatureNonce'              => wp_create_nonce( 'woopay_signature_nonce' ),
			'woopaySessionNonce'                => wp_create_nonce( 'woopay_session_nonce' ),
			'woopayMerchantId'                  => Jetpack_Options::get_option( 'id' ),
			'icon'                              => $this->gateway->get_icon_url(),
			'woopayMinimumSessionData'          => WooPay_Session::get_woopay_minimum_session_data(),
		];

		/**
		 * Allows filtering of the JS config for the payment fields.
		 *
		 * @param array $js_config The JS config for the payment fields.
		 */
		$payment_fields = apply_filters( 'wcpay_payment_fields_js_config', $js_config );

		$payment_fields['accountDescriptor']             = $this->gateway->get_account_statement_descriptor();
		$payment_fields['addPaymentReturnURL']           = wc_get_account_endpoint_url( 'payment-methods' );
		$payment_fields['gatewayId']                     = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$payment_fields['isCheckout']                    = is_checkout();
		$payment_fields['paymentMethodsConfig']          = $this->get_enabled_payment_method_config();
		$payment_fields['testMode']                      = WC_Payments::mode()->is_test();
		$payment_fields['upeAppearance']                 = get_transient( WC_Payment_Gateway_WCPay::UPE_APPEARANCE_TRANSIENT );
		$payment_fields['upeAddPaymentMethodAppearance'] = get_transient( WC_Payment_Gateway_WCPay::UPE_ADD_PAYMENT_METHOD_APPEARANCE_TRANSIENT );
		$payment_fields['upeBnplProductPageAppearance']  = get_transient( WC_Payment_Gateway_WCPay::UPE_BNPL_PRODUCT_PAGE_APPEARANCE_TRANSIENT );
		$payment_fields['upeBnplClassicCartAppearance']  = get_transient( WC_Payment_Gateway_WCPay::UPE_BNPL_CLASSIC_CART_APPEARANCE_TRANSIENT );
		$payment_fields['upeBnplCartBlockAppearance']    = get_transient( WC_Payment_Gateway_WCPay::UPE_BNPL_CART_BLOCK_APPEARANCE_TRANSIENT );
		$payment_fields['wcBlocksUPEAppearance']         = get_transient( WC_Payment_Gateway_WCPay::WC_BLOCKS_UPE_APPEARANCE_TRANSIENT );
		$payment_fields['wcBlocksUPEAppearanceTheme']    = get_transient( WC_Payment_Gateway_WCPay::WC_BLOCKS_UPE_APPEARANCE_THEME_TRANSIENT );
		$payment_fields['cartContainsSubscription']      = $this->gateway->is_subscription_item_in_cart();
		$payment_fields['currency']                      = get_woocommerce_currency();
		$cart_total                                      = ( WC()->cart ? WC()->cart->get_total( '' ) : 0 );
		$payment_fields['cartTotal']                     = WC_Payments_Utils::prepare_amount( $cart_total, get_woocommerce_currency() );

		$enabled_billing_fields = [];
		foreach ( WC()->checkout()->get_checkout_fields( 'billing' ) as $billing_field => $billing_field_options ) {
			if ( ! isset( $billing_field_options['enabled'] ) || $billing_field_options['enabled'] ) {
				$enabled_billing_fields[ $billing_field ] = [
					'required' => $billing_field_options['required'] ?? false,
				];
			}
		}
		$payment_fields['enabledBillingFields'] = $enabled_billing_fields;

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			if ( $this->gateway->is_subscriptions_enabled() && $this->gateway->is_changing_payment_method_for_subscription() ) {
				$payment_fields['isChangingPayment']   = true;
				$payment_fields['addPaymentReturnURL'] = esc_url_raw( home_url( add_query_arg( [] ) ) );

				if ( $this->gateway->is_setup_intent_success_creation_redirection() && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ) ) ) {
					$setup_intent_id = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
					$token           = $this->gateway->create_token_from_setup_intent( $setup_intent_id, wp_get_current_user() );
					if ( null !== $token ) {
						$payment_fields['newTokenFormId'] = '#wc-' . $token->get_gateway_id() . '-payment-token-' . $token->get_id();
					}
				}
				return $payment_fields; // nosemgrep: audit.php.wp.security.xss.query-arg -- server generated url is passed in.
			}

			$payment_fields['isOrderPay'] = true;
			$order_id                     = absint( get_query_var( 'order-pay' ) );
			$payment_fields['orderId']    = $order_id;
			$order                        = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) ) {
				$order_currency                   = $order->get_currency();
				$payment_fields['currency']       = $order_currency;
				$payment_fields['cartTotal']      = WC_Payments_Utils::prepare_amount( $order->get_total(), $order_currency );
				$payment_fields['orderReturnURL'] = esc_url_raw(
					add_query_arg(
						[
							'wc_payment_method' => WC_Payment_Gateway_WCPay::GATEWAY_ID,
							'_wpnonce'          => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
						],
						$this->gateway->get_return_url( $order )
					)
				);
			}
		}

		// Get the store base country.
		$payment_fields['storeCountry'] = WC()->countries->get_base_country();

		// Get the WooCommerce Store API endpoint.
		$payment_fields['storeApiURL'] = get_rest_url( null, 'wc/store' );

		/**
		 * Allows filtering for the payment fields.
		 *
		 * @param array $payment_fields The payment fields.
		 */
		return apply_filters( 'wcpay_payment_fields_js_config', $payment_fields ); // nosemgrep: audit.php.wp.security.xss.query-arg -- server generated url is passed in.
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @return array
	 */
	public function get_enabled_payment_method_config() {
		$settings                = [];
		$enabled_payment_methods = $this->gateway->get_upe_enabled_payment_method_ids_based_on_manual_capture();

		foreach ( $enabled_payment_methods as $payment_method_id ) {
			// Link by Stripe should be validated with available fees.
			if ( Payment_Method::LINK === $payment_method_id ) {
				if ( ! in_array( Payment_Method::LINK, array_keys( $this->account->get_fees() ), true ) ) {
					continue;
				}
			}

			$settings[ $payment_method_id ] = $this->get_config_for_payment_method( $payment_method_id, $this->account->get_account_country() );
		}

		return $settings;
	}

	/**
	 * Gets the config for all payment methods.
	 *
	 * @return array
	 */
	public function get_all_payment_method_config() {
		$payment_method_configs = [];

		// Get all the registered payment methods.
		$registered_payment_methods = array_keys( $this->gateway->get_payment_methods() );

		foreach ( $registered_payment_methods as $payment_method_id ) {
			$payment_method_configs[ $payment_method_id ] = $this->get_config_for_payment_method( $payment_method_id, $this->account->get_account_country() );
		}

		return $payment_method_configs;
	}

	/**
	 * Gets the config for a payment method.
	 *
	 * @param string $payment_method_id The payment method ID.
	 * @param string $account_country The account country.
	 * @return array
	 */
	private function get_config_for_payment_method( $payment_method_id, $account_country ) {
		$payment_method = $this->gateway->wc_payments_get_payment_method_by_id( $payment_method_id );
		$config         = [
			'isReusable'     => $payment_method->is_reusable(),
			'isBnpl'         => $payment_method->is_bnpl(),
			'title'          => $payment_method->get_title( $account_country ),
			'icon'           => $payment_method->get_icon( $account_country ),
			'darkIcon'       => $payment_method->get_dark_icon( $account_country ),
			'showSaveOption' => $this->should_upe_payment_method_show_save_option( $payment_method ),
			'countries'      => $payment_method->get_countries(),
		];

		$gateway_for_payment_method    = $this->gateway->wc_payments_get_payment_gateway_by_id( $payment_method_id );
		$config['gatewayId']           = $gateway_for_payment_method->id;
		$config['testingInstructions'] = WC_Payments_Utils::esc_interpolated_html(
			/* translators: link to Stripe testing page */
			$payment_method->get_testing_instructions( $account_country ),
			[
				'a'      => '<a href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">',
				'strong' => '<strong>',
				'number' => '<button type="button" class="js-woopayments-copy-test-number" aria-label="' . esc_attr( __( 'Click to copy the test number to clipboard', 'woocommerce-payments' ) ) . '" title="' . esc_attr( __( 'Copy to clipboard', 'woocommerce-payments' ) ) . '"><i></i><span>',
			]
		);

		$should_enable_network_saved_cards = Payment_Method::CARD === $payment_method_id && WC_Payments::is_network_saved_cards_enabled();
		$config['forceNetworkSavedCards']  = $should_enable_network_saved_cards || $gateway_for_payment_method->should_use_stripe_platform_on_checkout_page();

		return $config;
	}

	/**
	 * Checks if the save option for a payment method should be displayed or not.
	 *
	 * @param UPE_Payment_Method $payment_method UPE Payment Method instance.
	 * @return bool - True if the payment method is reusable and the saved cards feature is enabled for the gateway and there is no subscription item in the cart, false otherwise.
	 */
	private function should_upe_payment_method_show_save_option( $payment_method ) {
		if ( $payment_method->get_id() === Payment_Method::CARD && is_user_logged_in() && WC_Payments_Features::is_woopay_enabled() ) {
			return false;
		}

		if ( $payment_method->is_reusable() ) {
			return $this->gateway->is_saved_cards_enabled() && ! $this->gateway->is_subscription_item_in_cart();
		}

		return false;
	}

	/**
	 * Renders the UPE input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->gateway->supports( 'tokenization' ) && ( is_checkout() || is_add_payment_method_page() );

			/**
			 * Localizing scripts within shortcodes does not work in WP 5.9,
			 * but we need `$this->get_payment_fields_js_config` to be called
			 * before `$this->saved_payment_methods()`.
			 */
			if ( ! wp_script_is( 'wcpay-upe-checkout', 'enqueued' ) ) {
				$payment_fields = $this->get_payment_fields_js_config();
				wp_enqueue_script( 'wcpay-upe-checkout' );
				/**
				 * We can't localize the script right away since at this point is not registered yet.
				 * We also need to make sure it that it only runs once (using a dummy action), even if
				 * there are multiple payment methods available; otherwise the data will be overwritten
				 * which is pointless.
				 *
				 * The same applies for `wcpayCustomerData` a few lines below.
				 */
				add_action(
					'wp_footer',
					function () use ( $payment_fields ) {
						if ( ! did_action( '__wcpay_upe_config_localized' ) ) {
							wp_localize_script( 'wcpay-upe-checkout', 'wcpay_upe_config', $payment_fields );
						}
						do_action( '__wcpay_upe_config_localized' );
					}
				);

				$prepared_customer_data = $this->customer_service->get_prepared_customer_data();
				if ( ! empty( $prepared_customer_data ) ) {
					add_action(
						'wp_footer',
						function () use ( $prepared_customer_data ) {
							if ( ! did_action( '__wcpay_customer_data_localized' ) ) {
								wp_localize_script( 'wcpay-upe-checkout', 'wcpayCustomerData', $prepared_customer_data );
							}
							do_action( '__wcpay_customer_data_localized' );
						}
					);
				}

				WC_Payments_Utils::enqueue_style(
					'wcpay-upe-checkout',
					plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ),
					[],
					WC_Payments::get_file_version( 'dist/checkout.css' ),
					'all'
				);
			}

			?>
			<div class="wcpay-upe-form"
				data-payment-method-type="<?php echo esc_attr( $this->gateway->get_stripe_id() ); ?>"
				>
				<?php

				// Output the form HTML.
				if ( ! empty( $this->gateway->get_description() ) ) :
					?>
				<p><?php echo wp_kses_post( $this->gateway->get_description() ); ?></p>
					<?php
			endif;

				if ( WC_Payments::mode()->is_test() && false !== $this->gateway->get_payment_method()->get_testing_instructions( $this->account->get_account_country() ) ) :
					?>
				<p class="testmode-info">
					<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo WC_Payments_Utils::esc_interpolated_html(
							/* translators: link to Stripe testing page */
								$this->gateway->get_payment_method()->get_testing_instructions( $this->account->get_account_country() ),
								[
									'a'      => '<a href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">',
									'strong' => '<strong>',
									'number' => '<button type="button" class="js-woopayments-copy-test-number" aria-label="' . esc_attr( __( 'Click to copy the test number to clipboard', 'woocommerce-payments' ) ) . '" title="' . esc_attr( __( 'Copy to clipboard', 'woocommerce-payments' ) ) . '"><i></i><span>',
								]
							);
					?>
				</p>
					<?php
			endif;

				if ( $display_tokenization ) {
					$this->gateway->tokenization_script();
					// avoid showing saved payment methods on my-accounts add payment method page.
					if ( ! is_add_payment_method_page() ) {
						$this->gateway->saved_payment_methods();
					}
				}
				?>

			<fieldset style="padding: 7px" class="wc-payment-form">
				<?php
					$this->gateway->display_gateway_html();
				if ( $this->gateway->is_saved_cards_enabled() && $this->gateway->should_support_saved_payments() ) {
					$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_payments_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
					if ( is_user_logged_in() || $force_save_payment ) {
						$this->gateway->save_payment_method_checkbox( $force_save_payment );
					}
				}
				?>

			</fieldset>
			</div>
			<?php

			do_action( 'wcpay_payment_fields_upe', $this->gateway->id );

		} catch ( \Exception $e ) {
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
	 * Set gateway
	 *
	 * @param string $payment_method_id Payment method ID.
	 * @return void
	 */
	public function set_gateway( $payment_method_id ) {
		if ( null !== $payment_method_id ) {
			$this->gateway = $this->gateway->wc_payments_get_payment_gateway_by_id( $payment_method_id );
		}
	}

	/**
	 * Load the checkout scripts.
	 */
	private function load_checkout_scripts() {
		WC_Payments::get_gateway()->tokenization_script();
		$script_handle = 'wcpay-upe-checkout';
		$js_object     = 'wcpay_upe_config';
		wp_localize_script( $script_handle, $js_object, WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() );
		wp_enqueue_script( $script_handle );
	}
}
