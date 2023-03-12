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
use WCPay\Platform_Checkout\Platform_Checkout_Utilities;


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
	 * Platform Checkout Utilities.
	 *
	 * @var Platform_Checkout_Utilities
	 */
	protected $platform_checkout_util;

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
	 * @param Platform_Checkout_Utilities  $platform_checkout_util Platform Checkout Utilities.
	 * @param WC_Payments_Account          $account                WC Payments Account.
	 * @param WC_Payments_Customer_Service $customer_service       WC Payments Customer Service.
	 */
	public function __construct(
		WC_Payment_Gateway_WCPay $gateway,
		Platform_Checkout_Utilities $platform_checkout_util,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service
	) {
		$this->gateway                = $gateway;
		$this->platform_checkout_util = $platform_checkout_util;
		$this->account                = $account;
		$this->customer_service       = $customer_service;

		add_action( 'wc_payments_add_payment_fields', [ $this, 'payment_fields' ] );
	}

	/**
	 * Enqueues and localizes WCPay's checkout scripts.
	 */
	public function enqueue_payment_scripts() {
		wp_localize_script( 'WCPAY_CHECKOUT', 'wcpayConfig', WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() );
		wp_enqueue_script( 'WCPAY_CHECKOUT' );
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

		$wc_checkout = WC_Checkout::instance();

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
			'initPlatformCheckoutNonce'      => wp_create_nonce( 'wcpay_init_platform_checkout_nonce' ),
			'saveUPEAppearanceNonce'         => wp_create_nonce( 'wcpay_save_upe_appearance_nonce' ),
			'genericErrorMessage'            => __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-payments' ),
			'fraudServices'                  => $this->account->get_fraud_services_config(),
			'features'                       => $this->gateway->supports,
			'forceNetworkSavedCards'         => WC_Payments::is_network_saved_cards_enabled() || $this->gateway->should_use_stripe_platform_on_checkout_page(),
			'locale'                         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			'isPreview'                      => is_preview(),
			'isUPEEnabled'                   => WC_Payments_Features::is_upe_enabled(),
			'isUPESplitEnabled'              => WC_Payments_Features::is_upe_split_enabled(),
			'isSavedCardsEnabled'            => $this->gateway->is_saved_cards_enabled(),
			'isPlatformCheckoutEnabled'      => $this->platform_checkout_util->should_enable_platform_checkout( $this->gateway ),
			'isWoopayExpressCheckoutEnabled' => $this->platform_checkout_util->is_woopay_express_checkout_enabled(),
			'isClientEncryptionEnabled'      => WC_Payments_Features::is_client_secret_encryption_enabled(),
			'platformCheckoutHost'           => defined( 'PLATFORM_CHECKOUT_FRONTEND_HOST' ) ? PLATFORM_CHECKOUT_FRONTEND_HOST : 'https://pay.woo.com',
			'platformTrackerNonce'           => wp_create_nonce( 'platform_tracks_nonce' ),
			'accountIdForIntentConfirmation' => apply_filters( 'wc_payments_account_id_for_intent_confirmation', '' ),
			'wcpayVersionNumber'             => WCPAY_VERSION_NUMBER,
			'platformCheckoutNeedLogin'      => ! is_user_logged_in() && $wc_checkout->is_registration_required(),
			'userExistsEndpoint'             => get_rest_url( null, '/wc/v3/users/exists' ),
			'platformCheckoutSignatureNonce' => wp_create_nonce( 'platform_checkout_signature_nonce' ),
			'platformCheckoutMerchantId'     => Jetpack_Options::get_option( 'id' ),
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
							'a'      => '<a href="https://woocommerce.com/document/payments/testing/#test-cards" target="_blank">',
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
