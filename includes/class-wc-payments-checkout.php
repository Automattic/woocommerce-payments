<?php
/**
 * Class WC_Payments_Checkout
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Jetpack_Options;
use WC_AJAX;
use WC_Checkout;
use WC_Payment_Gateway_WCPay;
use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Customer_Service;
use WC_Payments_Features;
use WC_Payments_Utils;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\WooPay\WooPay_Utilities;


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
	 * Construct.
	 *
	 * @param WC_Payment_Gateway_WCPay     $gateway                WC Payment Gateway.
	 * @param WooPay_Utilities             $woopay_util WooPay Utilities.
	 * @param WC_Payments_Account          $account                WC Payments Account.
	 * @param WC_Payments_Customer_Service $customer_service       WC Payments Customer Service.
	 */
	public function __construct(
		WC_Payment_Gateway_WCPay $gateway,
		WooPay_Utilities $woopay_util,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service
	) {
		$this->gateway          = $gateway;
		$this->woopay_util      = $woopay_util;
		$this->account          = $account;
		$this->customer_service = $customer_service;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'wc_payments_add_payment_fields', [ $this, 'payment_fields' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts_for_zero_order_total' ], 11 );
	}

	/**
	 * Enqueues and localizes WCPay's checkout scripts.
	 */
	public function enqueue_payment_scripts() {
		wp_localize_script( 'WCPAY_CHECKOUT', 'wcpayConfig', WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() );
		wp_enqueue_script( 'WCPAY_CHECKOUT' );
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

		$script_dependencies = [ 'stripe', 'wc-checkout' ];

		if ( $this->gateway->supports( 'tokenization' ) ) {
			$script_dependencies[] = 'woocommerce-tokenization-form';
		}
		WC_Payments::register_script_with_dependencies( 'WCPAY_CHECKOUT', 'dist/checkout', $script_dependencies );
		wp_set_script_translations( 'WCPAY_CHECKOUT', 'woocommerce-payments' );
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
			! has_block( 'woocommerce/checkout' )
		) {
			WC_Payments::get_gateway()->tokenization_script();
			$script_handle = 'WCPAY_CHECKOUT';
			$js_object     = 'wcpayConfig';
			if ( WC_Payments_Features::is_upe_split_enabled() || WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
				$script_handle = 'wcpay-upe-checkout';
				$js_object     = 'wcpay_upe_config';
			} elseif ( WC_Payments_Features::is_upe_legacy_enabled() ) {
				$script_handle = 'wcpay-upe-checkout';
			}
			wp_localize_script( $script_handle, $js_object, WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() );
			wp_enqueue_script( $script_handle );
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

		$js_config = [
			'publishableKey'                 => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
			'testMode'                       => WC_Payments::mode()->is_test(),
			'accountId'                      => $this->account->get_stripe_account_id(),
			'ajaxUrl'                        => admin_url( 'admin-ajax.php' ),
			'wcAjaxUrl'                      => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'createSetupIntentNonce'         => wp_create_nonce( 'wcpay_create_setup_intent_nonce' ),
			'createPaymentIntentNonce'       => wp_create_nonce( 'wcpay_create_payment_intent_nonce' ),
			'updatePaymentIntentNonce'       => wp_create_nonce( 'wcpay_update_payment_intent_nonce' ),
			'logPaymentErrorNonce'           => wp_create_nonce( 'wcpay_log_payment_error_nonce' ),
			'initWooPayNonce'                => wp_create_nonce( 'wcpay_init_woopay_nonce' ),
			'saveUPEAppearanceNonce'         => wp_create_nonce( 'wcpay_save_upe_appearance_nonce' ),
			'genericErrorMessage'            => __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-payments' ),
			'fraudServices'                  => $this->account->get_fraud_services_config(),
			'features'                       => $this->gateway->supports,
			'forceNetworkSavedCards'         => WC_Payments::is_network_saved_cards_enabled() || $this->gateway->should_use_stripe_platform_on_checkout_page(),
			'locale'                         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			'isPreview'                      => is_preview(),
			'isUPEEnabled'                   => WC_Payments_Features::is_upe_enabled(),
			'isUPESplitEnabled'              => WC_Payments_Features::is_upe_split_enabled(),
			'isUPEDeferredEnabled'           => WC_Payments_Features::is_upe_deferred_intent_enabled(),
			'isSavedCardsEnabled'            => $this->gateway->is_saved_cards_enabled(),
			'isWooPayEnabled'                => $this->woopay_util->should_enable_woopay( $this->gateway ) && $this->woopay_util->should_enable_woopay_on_cart_or_checkout(),
			'isWoopayExpressCheckoutEnabled' => $this->woopay_util->is_woopay_express_checkout_enabled(),
			'isClientEncryptionEnabled'      => WC_Payments_Features::is_client_secret_encryption_enabled(),
			'woopayHost'                     => WooPay_Utilities::get_woopay_url(),
			'platformTrackerNonce'           => wp_create_nonce( 'platform_tracks_nonce' ),
			'accountIdForIntentConfirmation' => apply_filters( 'wc_payments_account_id_for_intent_confirmation', '' ),
			'wcpayVersionNumber'             => WCPAY_VERSION_NUMBER,
			'woopaySignatureNonce'           => wp_create_nonce( 'woopay_signature_nonce' ),
			'woopayMerchantId'               => Jetpack_Options::get_option( 'id' ),
			'icon'                           => $this->gateway->get_icon_url(),
		];

		/**
		 * Allows filtering of the JS config for the payment fields.
		 *
		 * @param array $js_config The JS config for the payment fields.
		 */
		return apply_filters( 'wcpay_payment_fields_js_config', $js_config );
	}

	/**
	 * Renders the credit card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->gateway->supports( 'tokenization' ) && ( is_checkout() || is_add_payment_method_page() );

			add_action( 'wp_footer', [ $this, 'enqueue_payment_scripts' ] );

			$prepared_customer_data = $this->customer_service->get_prepared_customer_data();
			if ( ! empty( $prepared_customer_data ) ) {
				wp_localize_script( 'WCPAY_CHECKOUT', 'wcpayCustomerData', $prepared_customer_data );
			}

			wp_enqueue_style(
				'WCPAY_CHECKOUT',
				plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/checkout.css' )
			);

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->gateway->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->gateway->get_description() ); ?></p>
			<?php endif; ?>

			<?php if ( WC_Payments::mode()->is_test() ) : ?>
				<p class="testmode-info">
					<?php
					echo WC_Payments_Utils::esc_interpolated_html(
					/* translators: link to Stripe testing page */
						__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC, or any test card numbers listed <a>here</a>.', 'woocommerce-payments' ),
						[
							'strong' => '<strong>',
							'a'      => '<a href="https://woocommerce.com/document/woocommerce-payments/testing-and-troubleshooting/testing/#test-cards" target="_blank">',
						]
					);
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

			<fieldset id="wc-<?php echo esc_attr( $this->gateway->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
				<div id="wcpay-card-element"></div>
				<div id="wcpay-errors" role="alert"></div>
				<input id="wcpay-payment-method" type="hidden" name="wcpay-payment-method" />
				<input type="hidden" name="wcpay-is-platform-payment-method" value="<?php echo esc_attr( $this->gateway->should_use_stripe_platform_on_checkout_page() ); ?>" />
				<?php
				if ( $this->gateway->is_saved_cards_enabled() ) {
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

			do_action( 'wcpay_payment_fields_wcpay', $this->gateway->id );

		} catch ( \Exception $e ) {
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

}
