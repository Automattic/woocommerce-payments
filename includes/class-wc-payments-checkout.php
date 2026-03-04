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

		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts_for_zero_order_total' ], 11 );
		add_action( 'woocommerce_after_checkout_form', [ $this, 'maybe_load_checkout_scripts' ] );
		add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'add_payment_methods_config_to_update_order_review_fragments' ] );
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
			'genericErrorMessage'               => __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-payments' ),
			'fraudServices'                     => $this->fraud_service->get_fraud_services_config(),
			'features'                          => $this->gateway->supports,
			'forceNetworkSavedCards'            => WC_Payments::is_network_saved_cards_enabled() || $gateway->should_use_stripe_platform_on_checkout_page(),
			'locale'                            => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			'isPreview'                         => is_preview(),
			'isSavedCardsEnabled'               => $this->gateway->is_saved_cards_enabled(),
			'isWooPayEnabled'                   => $this->woopay_util->should_enable_woopay( $this->gateway ) && $this->woopay_util->should_enable_woopay_on_guest_checkout(),
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

		$payment_fields = $js_config;

		$payment_fields['gatewayId']                = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$payment_fields['isCheckout']               = is_checkout();
		$payment_fields['paymentMethodsConfig']     = $this->get_enabled_payment_method_config();
		$payment_fields['testMode']                 = WC_Payments::mode()->is_test();
		$payment_fields['cartContainsSubscription'] = $this->gateway->is_subscription_item_in_cart();
		$payment_fields['currency']                 = get_woocommerce_currency();
		$payment_fields['stylesCacheVersion']       = WC_Payments_Utils::get_styles_cache_version();
		$cart_total                                 = ( WC()->cart ? WC()->cart->get_total( '' ) : 0 );
		$payment_fields['cartTotal']                = WC_Payments_Utils::prepare_amount( $cart_total, get_woocommerce_currency() );

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
				$payment_fields['isChangingPayment'] = true;
				return $payment_fields; // nosemgrep: audit.php.wp.security.xss.query-arg -- server generated url is passed in.
			}

			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) && current_user_can( 'pay_for_order', $order->get_id() ) ) {
				$payment_fields['isOrderPay'] = true;
				$payment_fields['orderId']    = $order_id;
				$order_currency               = $order->get_currency();
				$payment_fields['currency']   = $order_currency;
				$payment_fields['cartTotal']  = WC_Payments_Utils::prepare_amount( $order->get_total(), $order_currency );
			}
		}

		// Get the store base country.
		$payment_fields['storeCountry'] = WC()->countries->get_base_country();

		// Whether express checkout methods (Apple Pay, Google Pay, Amazon Pay) should be displayed
		// in the payment methods list instead of as separate express buttons.
		$payment_fields['isExpressCheckoutInPaymentMethodsEnabled'] = \WC_Payments::get_gateway()->is_express_checkout_in_payment_methods_enabled();

		/**
		 * Allows filtering of the JS config for the payment fields.
		 *
		 * @param array $js_config The JS config for the payment fields.
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
		$enabled_payment_methods = $this->gateway->get_payment_method_ids_enabled_at_checkout();

		// When "express checkout in payment methods" setting is enabled, add express checkout
		// methods to the list. They're not in upe_enabled_payment_method_ids by default since
		// they're normally registered separately via registerExpressPaymentMethod() in JS.
		// Use the card gateway (main gateway) for this check, because $this->gateway
		// can be mutated by set_gateway() during shortcode checkout rendering.
		$card_gateway                           = \WC_Payments::get_gateway();
		$is_express_checkout_in_payment_methods = $card_gateway->is_express_checkout_in_payment_methods_enabled();

		if ( $is_express_checkout_in_payment_methods ) {
			// Add Apple Pay and Google Pay if payment request is enabled.
			if ( $card_gateway->is_payment_request_enabled() ) {
				$enabled_payment_methods[] = 'apple_pay';
				$enabled_payment_methods[] = 'google_pay';
			}

			// Add Amazon Pay if the feature flag is enabled and the gateway is enabled.
			if ( WC_Payments_Features::is_amazon_pay_enabled() ) {
				$amazon_pay_gateway = \WC_Payments::get_payment_gateway_by_id( 'amazon_pay' );
				if ( $amazon_pay_gateway && $amazon_pay_gateway->is_enabled() ) {
					$enabled_payment_methods[] = 'amazon_pay';
				}
			}
		}

		foreach ( $enabled_payment_methods as $payment_method_id ) {
			// Link by Stripe should be validated with available fees.
			if ( Payment_Method::LINK === $payment_method_id ) {
				if ( ! in_array( Payment_Method::LINK, array_keys( $this->account->get_fees() ), true ) ) {
					continue;
				}
			}

			// Skip express checkout methods if they somehow got into the list, but the setting
			// is not enabled (it shouldn't happen with normal code flow - adding just in case).
			$payment_method = $this->gateway->wc_payments_get_payment_method_by_id( $payment_method_id );
			if ( $payment_method && $payment_method->is_express_checkout() ) {
				if ( ! $is_express_checkout_in_payment_methods ) {
					continue;
				}
			}

			$settings[ $payment_method_id ] = $this->get_config_for_payment_method( $payment_method_id, $this->account->get_account_country() );
		}

		return $settings;
	}

	/**
	 * Adds dynamic payment fields config to the update_order_review AJAX response fragments.
	 *
	 * This allows the frontend to refresh the available payment methods and currency
	 * when the billing country changes during checkout. This is particularly important
	 * for stores using plugins that change currency based on customer location, ensuring
	 * that payment methods restricted by country/currency are properly updated.
	 *
	 * @param array $fragments The fragments to be updated.
	 * @return array The updated fragments.
	 */
	public function add_payment_methods_config_to_update_order_review_fragments( $fragments ) {
		if ( ! isset( $fragments['.woocommerce-checkout-payment'] ) ) {
			return $fragments;
		}

		// I'm calling the base method (rather than reconstructing the pieces individually), so that we can also take advantage of the hooks/filters.
		// It's a little heavier in computation, but it gives a more accurate result.
		$js_config = $this->get_payment_fields_js_config();

		$fragments['.woocommerce-checkout-payment'] .= sprintf(
			'<script>window.wcpay_upe_config && Object.assign( window.wcpay_upe_config, %s );</script>',
			wp_json_encode(
				[
					'paymentMethodsConfig' => $js_config['paymentMethodsConfig'],
					'currency'             => $js_config['currency'],
					'cartTotal'            => $js_config['cartTotal'],
				]
			)
		);

		return $fragments;
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
	 * Gets the config for a payment method.
	 *
	 * @param string $payment_method_id The payment method ID.
	 * @param string $account_country The account country.
	 * @return array
	 */
	private function get_config_for_payment_method( $payment_method_id, $account_country ) {
		$payment_method = $this->gateway->wc_payments_get_payment_method_by_id( $payment_method_id );

		if ( ! $payment_method ) {
			return [];
		}

		$config = [
			'isReusable'        => $payment_method->is_reusable(),
			'isBnpl'            => $payment_method->is_bnpl(),
			'isExpressCheckout' => $payment_method->is_express_checkout(),
			'title'             => $payment_method->get_title( $account_country ),
			'icon'              => $payment_method->get_icon( $account_country ),
			'darkIcon'          => $payment_method->get_dark_icon( $account_country ),
			'showSaveOption'    => $this->should_upe_payment_method_show_save_option( $payment_method ),
			'countries'         => $payment_method->get_countries(),
		];

		$gateway_for_payment_method = $this->gateway->wc_payments_get_payment_gateway_by_id( $payment_method_id );
		if ( ! $gateway_for_payment_method ) {
			return [];
		}
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
