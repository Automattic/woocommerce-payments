<?php
/**
 * Class WC_Payments_Checkout
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Customer_Service;
use WC_Payments_Fraud_Service;
use WC_Payments_Utils;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\WooPay\WooPay_Utilities;


/**
 * WC_Payments_Checkout
 */
class WC_Payments_Checkout {
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
	 * @param WooPay_Utilities             $woopay_util            WooPay Utilities.
	 * @param WC_Payments_Account          $account                WC Payments Account.
	 * @param WC_Payments_Customer_Service $customer_service       WC Payments Customer Service.
	 * @param WC_Payments_Fraud_Service    $fraud_service          Fraud service instance.
	 */
	public function __construct(
		WooPay_Utilities $woopay_util,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Fraud_Service $fraud_service
	) {
		$this->woopay_util      = $woopay_util;
		$this->account          = $account;
		$this->customer_service = $customer_service;
		$this->fraud_service    = $fraud_service;
	}

	/**
	 * Renders the credit card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->gateway->supports( 'tokenization' ) && ( is_checkout() || is_add_payment_method_page() );

			$prepared_customer_data = $this->customer_service->get_prepared_customer_data();
			if ( ! empty( $prepared_customer_data ) ) {
				wp_localize_script( 'WCPAY_CHECKOUT', 'wcpayCustomerData', $prepared_customer_data );
			}

			WC_Payments_Utils::enqueue_style(
				'WCPAY_CHECKOUT',
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
					echo WC_Payments_Utils::esc_interpolated_html(
					/* translators: link to Stripe testing page */
						__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC, or any test card numbers listed <a>here</a>.', 'woocommerce-payments' ),
						[
							'strong' => '<strong>',
							'a'      => '<a href="https://woo.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">',
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
