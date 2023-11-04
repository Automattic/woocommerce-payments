<?php
/**
 * Class WC_Payments_Checkout
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Exception;
use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Customer_Service;
use WC_Payments_Fraud_Service;
use WC_Payments_Utils;
use WC_Payments_Features;
use WCPay\Constants\Payment_Method;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Payment_Methods\UPE_Payment_Method;


/**
 * WC_Payments_Checkout
 */
class WC_Payments_UPE_Checkout extends WC_Payments_Checkout {

	/**
	 * WC Payments Gateway.
	 *
	 * @var UPE_Payment_Gateway
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
	 * @param UPE_Payment_Gateway          $gateway          WC Payment Gateway.
	 * @param WooPay_Utilities             $woopay_util      WooPay Utilities.
	 * @param WC_Payments_Account          $account          WC Payments Account.
	 * @param WC_Payments_Customer_Service $customer_service WC Payments Customer Service.
	 * @param WC_Payments_Fraud_Service    $fraud_service    Fraud service instance.
	 */
	public function __construct(
		UPE_Payment_Gateway $gateway,
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
		add_action( 'woocommerce_after_account_payment_methods', [ $this->gateway, 'remove_upe_setup_intent_from_session' ], 10, 0 );
		add_action( 'woocommerce_subscription_payment_method_updated', [ $this->gateway, 'remove_upe_setup_intent_from_session' ], 10, 0 );
		add_action( 'woocommerce_order_payment_status_changed', [ get_class( $this->gateway ), 'remove_upe_payment_intent_from_session' ], 10, 0 );
		add_action( 'wp', [ $this->gateway, 'maybe_process_upe_redirect' ] );
		add_action( 'wc_ajax_wcpay_log_payment_error', [ $this->gateway, 'log_payment_error_ajax' ] );
		add_action( 'wp_ajax_save_upe_appearance', [ $this->gateway, 'save_upe_appearance_ajax' ] );
		add_action( 'wp_ajax_nopriv_save_upe_appearance', [ $this->gateway, 'save_upe_appearance_ajax' ] );
		add_action( 'switch_theme', [ $this->gateway, 'clear_upe_appearance_transient' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ $this->gateway, 'clear_upe_appearance_transient' ] );
		if ( ! WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
			add_action( 'wc_ajax_wcpay_create_payment_intent', [ $this->gateway, 'create_payment_intent_ajax' ] );
			add_action( 'wc_ajax_wcpay_update_payment_intent', [ $this->gateway, 'update_payment_intent_ajax' ] );
		}
		add_action( 'wc_ajax_wcpay_init_setup_intent', [ $this->gateway, 'init_setup_intent_ajax' ] );
		add_action( 'wc_ajax_wcpay_log_payment_error', [ $this->gateway, 'log_payment_error_ajax' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts_for_zero_order_total' ], 11 );
		add_action( 'woocommerce_email_before_order_table', [ $this->gateway, 'set_payment_method_title_for_email' ], 10, 3 );
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

		$script_dependencies = [ 'stripe', 'wc-checkout', 'wp-i18n' ];

		if ( $this->gateway->supports( 'tokenization' ) ) {
			$script_dependencies[] = 'woocommerce-tokenization-form';
		}

		if ( WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
			$script = 'dist/upe_with_deferred_intent_creation_checkout';
		} else {
			$script = 'dist/upe_checkout';
		}

		WC_Payments::register_script_with_dependencies( 'wcpay-upe-checkout', $script, $script_dependencies );
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

		$payment_fields                             = parent::get_payment_fields_js_config();
		$payment_fields['accountDescriptor']        = $this->gateway->get_account_statement_descriptor();
		$payment_fields['addPaymentReturnURL']      = wc_get_account_endpoint_url( 'payment-methods' );
		$payment_fields['gatewayId']                = UPE_Payment_Gateway::GATEWAY_ID;
		$payment_fields['isCheckout']               = is_checkout();
		$payment_fields['paymentMethodsConfig']     = $this->get_enabled_payment_method_config();
		$payment_fields['testMode']                 = WC_Payments::mode()->is_test();
		$payment_fields['upeAppearance']            = get_transient( UPE_Payment_Gateway::UPE_APPEARANCE_TRANSIENT );
		$payment_fields['wcBlocksUPEAppearance']    = get_transient( UPE_Payment_Gateway::WC_BLOCKS_UPE_APPEARANCE_TRANSIENT );
		$payment_fields['cartContainsSubscription'] = $this->gateway->is_subscription_item_in_cart();

		if ( WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
			$payment_fields['currency']  = get_woocommerce_currency();
			$cart_total                  = ( WC()->cart ? WC()->cart->get_total( '' ) : 0 );
			$payment_fields['cartTotal'] = WC_Payments_Utils::prepare_amount( $cart_total, get_woocommerce_currency() );
		} elseif ( WC_Payments_Features::is_upe_legacy_enabled() ) {
			$payment_fields['checkoutTitle']        = $this->gateway->get_checkout_title();
			$payment_fields['upePaymentIntentData'] = $this->gateway->get_payment_intent_data_from_session();
			$payment_fields['upeSetupIntentData']   = $this->gateway->get_setup_intent_data_from_session();
		}

		$enabled_billing_fields = [];
		foreach ( WC()->checkout()->get_checkout_fields( 'billing' ) as $billing_field => $billing_field_options ) {
			if ( ! isset( $billing_field_options['enabled'] ) || $billing_field_options['enabled'] ) {
				$enabled_billing_fields[] = $billing_field;
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
							'wc_payment_method' => UPE_Payment_Gateway::GATEWAY_ID,
							'_wpnonce'          => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
						],
						$this->gateway->get_return_url( $order )
					)
				);
			}
		}

		/**
		 * Allows filtering for the payment fields.
		 *
		 * @param array $payment_fields The payment fields.
		 */
		return apply_filters( 'wcpay_payment_fields_js_config', $payment_fields ); // nosemgrep: audit.php.wp.security.xss.query-arg -- server generated url is passed in.
	}

	/**
	 * Checks if WooPay is enabled.
	 *
	 * @return bool - True if WooPay enabled, false otherwise.
	 */
	private function is_woopay_enabled() {
		return WC_Payments_Features::is_woopay_eligible() && 'yes' === $this->gateway->get_option( 'platform_checkout', 'no' ) && WC_Payments_Features::is_woopay_express_checkout_enabled();
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @return array
	 */
	public function get_enabled_payment_method_config() {
		$settings                = [];
		$enabled_payment_methods = $this->gateway->get_payment_method_ids_enabled_at_checkout();

		foreach ( $enabled_payment_methods as $payment_method_id ) {
			// Link by Stripe should be validated with available fees.
			if ( Payment_Method::LINK === $payment_method_id ) {
				if ( ! in_array( Payment_Method::LINK, array_keys( $this->account->get_fees() ), true ) ) {
					continue;
				}
			}

			$payment_method                 = $this->gateway->wc_payments_get_payment_method_by_id( $payment_method_id );
			$settings[ $payment_method_id ] = [
				'isReusable'     => $payment_method->is_reusable(),
				'title'          => $payment_method->get_title(),
				'icon'           => $payment_method->get_icon(),
				'showSaveOption' => $this->should_upe_payment_method_show_save_option( $payment_method ),
				'countries'      => $payment_method->get_countries(),
			];

			if ( WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
				$gateway_for_payment_method                             = $this->gateway->wc_payments_get_payment_gateway_by_id( $payment_method_id );
				$settings[ $payment_method_id ]['upePaymentIntentData'] = $this->gateway->get_payment_intent_data_from_session( $payment_method_id );
				$settings[ $payment_method_id ]['upeSetupIntentData']   = $this->gateway->get_setup_intent_data_from_session( $payment_method_id );
				$settings[ $payment_method_id ]['testingInstructions']  = WC_Payments_Utils::esc_interpolated_html(
					/* translators: link to Stripe testing page */
					$payment_method->get_testing_instructions(),
					[
						'strong' => '<strong>',
						'a'      => '<a href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">',
					]
				);
				$settings[ $payment_method_id ]['forceNetworkSavedCards'] = $gateway_for_payment_method->should_use_stripe_platform_on_checkout_page();
			}
		}

		return $settings;
	}

	/**
	 * Checks if the save option for a payment method should be displayed or not.
	 *
	 * @param UPE_Payment_Method $payment_method UPE Payment Method instance.
	 * @return bool - True if the payment method is reusable and the saved cards feature is enabled for the gateway and there is no subscription item in the cart, false otherwise.
	 */
	private function should_upe_payment_method_show_save_option( $payment_method ) {
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
			$payment_fields  = $this->get_payment_fields_js_config();
			$upe_object_name = WC_Payments_Features::is_upe_deferred_intent_enabled() ? 'wcpay_upe_config' : 'wcpayConfig';
			wp_enqueue_script( 'wcpay-upe-checkout' );
			add_action(
				'wp_footer',
				function() use ( $payment_fields, $upe_object_name ) {
					wp_localize_script( 'wcpay-upe-checkout', $upe_object_name, $payment_fields );
				}
			);

			$prepared_customer_data = $this->customer_service->get_prepared_customer_data();
			if ( ! empty( $prepared_customer_data ) ) {
				wp_localize_script( 'wcpay-upe-checkout', 'wcpayCustomerData', $prepared_customer_data );
			}

			WC_Payments_Utils::enqueue_style(
				'wcpay-upe-checkout',
				plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/checkout.css' ),
				'all'
			);

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->gateway->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->gateway->get_description() ); ?></p>
			<?php endif; ?>

			<?php if ( WC_Payments::mode()->is_test() ) : ?>
				<p class="testmode-info">
					<?php
						$testing_instructions = $this->gateway->get_payment_method()->get_testing_instructions();
					if ( false !== $testing_instructions ) {
						echo WC_Payments_Utils::esc_interpolated_html(
							/* translators: link to Stripe testing page */
							$testing_instructions,
							[
								'strong' => '<strong>',
								'a'      => '<a href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">',
							]
						);
					}
					?>
				</p>
			<?php endif; ?>

			<?php

			if ( $display_tokenization ) {
				$this->gateway->tokenization_script();
				// avoid showing saved payment methods on my-accounts add payment method page.
				if ( ! is_add_payment_method_page() ) {
					$this->gateway->saved_payment_methods();
				}
			}
			?>

			<fieldset style="padding: 7px" id="wc-<?php echo esc_attr( $this->gateway->id ); ?>-upe-form" class="wc-upe-form wc-payment-form">
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

			<?php if ( WC()->session && Fraud_Prevention_Service::get_instance()->is_enabled() ) : ?>
				<input type="hidden" name="wcpay-fraud-prevention-token" value="<?php echo esc_attr( Fraud_Prevention_Service::get_instance()->get_token() ); ?>">
			<?php endif; ?>

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

}
