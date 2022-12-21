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
use WC_Payments_Utils;
use WCPay\Constants\Payment_Method;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Platform_Checkout\Platform_Checkout_Utilities;


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
	 * @param UPE_Payment_Gateway          $gateway                WC Payment Gateway.
	 * @param Platform_Checkout_Utilities  $platform_checkout_util Platform Checkout Utilities.
	 * @param WC_Payments_Account          $account                WC Payments Account.
	 * @param WC_Payments_Customer_Service $customer_service       WC Payments Customer Service.
	 */
	public function __construct(
		UPE_Payment_Gateway $gateway,
		Platform_Checkout_Utilities $platform_checkout_util,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service
	) {
		$this->gateway                = $gateway;
		$this->platform_checkout_util = $platform_checkout_util;
		$this->account                = $account;
		$this->customer_service       = $customer_service;

		add_action( 'wc_payments_add_upe_payment_fields', [ $this, 'payment_fields' ] );
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
		$payment_fields['saveUPEAppearanceNonce']   = wp_create_nonce( 'wcpay_save_upe_appearance_nonce' );
		$payment_fields['testMode']                 = $this->gateway->is_in_test_mode();
		$payment_fields['upeAppearance']            = get_transient( UPE_Payment_Gateway::UPE_APPEARANCE_TRANSIENT );
		$payment_fields['wcBlocksUPEAppearance']    = get_transient( UPE_Payment_Gateway::WC_BLOCKS_UPE_APPEARANCE_TRANSIENT );
		$payment_fields['checkoutTitle']            = $this->gateway->get_checkout_title();
		$payment_fields['cartContainsSubscription'] = $this->gateway->is_subscription_item_in_cart();
		$payment_fields['logPaymentErrorNonce']     = wp_create_nonce( 'wcpay_log_payment_error_nonce' );
		$payment_fields['upePaymentIntentData']     = WC()->session->get( UPE_Payment_Gateway::KEY_UPE_PAYMENT_INTENT );
		$payment_fields['upeSetupIntentData']       = WC()->session->get( UPE_Payment_Gateway::KEY_UPE_SETUP_INTENT );

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
							'wc_payment_method' => UPE_Payment_Gateway::GATEWAY_ID,
							'_wpnonce'          => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
						],
						$this->gateway->get_return_url( $order )
					)
				);
			}
		}
		return $payment_fields;
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @return array
	 */
	public function get_enabled_payment_method_config() {
		$settings                = [];
		$enabled_payment_methods = $this->gateway->get_payment_method_ids_enabled_at_checkout();

		if ( $this->gateway->is_subscriptions_enabled() && $this->gateway->is_changing_payment_method_for_subscription() ) {
			$enabled_payment_methods = array_filter( $enabled_payment_methods, [ $this->gateway, 'is_enabled_for_saved_payments' ] );
		}

		$payment_methods = $this->gateway->get_payment_methods();

		foreach ( $enabled_payment_methods as $payment_method ) {
			// Link by Stripe should be validated with available fees.
			if ( Payment_Method::LINK === $payment_method ) {
				if ( ! in_array( Payment_Method::LINK, array_keys( $this->account->get_fees() ), true ) ) {
					continue;
				}
			}

			$settings[ $payment_method ] = [
				'isReusable' => $payment_methods[ $payment_method ]->is_reusable(),
				'title'      => $payment_methods[ $payment_method ]->get_title(),
			];
		}

		return $settings;
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
			$payment_fields = $this->get_payment_fields_js_config();
			wp_enqueue_script( 'wcpay-upe-checkout' );
			add_action(
				'wp_footer',
				function() use ( $payment_fields ) {
					wp_localize_script( 'wcpay-upe-checkout', 'wcpay_config', $payment_fields );
				}
			);

			$prepared_customer_data = $this->customer_service->get_prepared_customer_data();
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
			<?php if ( ! empty( $this->gateway->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->gateway->get_description() ); ?></p>
			<?php endif; ?>

			<?php if ( $this->gateway->is_in_test_mode() ) : ?>
				<p class="testmode-info">
					<?php
					echo WC_Payments_Utils::esc_interpolated_html(
					/* translators: link to Stripe testing page */
						__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a>here</a>.', 'woocommerce-payments' ),
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
				$this->gateway->saved_payment_methods();
			}
			?>

			<fieldset id="wc-<?php echo esc_attr( $this->gateway->id ); ?>-upe-form" class="wc-upe-form wc-payment-form">
				<div id="wcpay-upe-element"></div>
				<div id="wcpay-upe-errors" role="alert"></div>
				<input id="wcpay-payment-method-upe" type="hidden" name="wcpay-payment-method-upe" />
				<input id="wcpay_selected_upe_payment_type" type="hidden" name="wcpay_selected_upe_payment_type" />
				<input id="wcpay_payment_country" type="hidden" name="wcpay_payment_country" />

				<?php
				$methods_enabled_for_saved_payments = array_filter( $this->gateway->get_upe_enabled_payment_method_ids(), [ $this->gateway, 'is_enabled_for_saved_payments' ] );
				if ( $this->gateway->is_saved_cards_enabled() && ! empty( $methods_enabled_for_saved_payments ) ) {
					$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_payments_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
					if ( is_user_logged_in() || $force_save_payment ) {
						$this->gateway->save_payment_method_checkbox( $force_save_payment );
					}
				}
				?>

			</fieldset>

			<?php if ( Fraud_Prevention_Service::get_instance()->is_enabled() ) : ?>
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

}
