<?php
/**
 * Class Sepa_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments;
use WC_Payments_Utils;

/**
 * SEPA Payment method extended from cart payment method.
 * Just loads different js and fields.
 */
class Sepa_Payment_Gateway extends WC_Payment_Gateway_WCPay {
	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments_sepa';

	const METHOD_ENABLED_KEY = 'sepa_enabled';

	/**
	 * Sepa Constrictor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                  - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service         - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service            - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service - Action Scheduler service instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, WC_Payments_Customer_Service $customer_service, WC_Payments_Token_Service $token_service, WC_Payments_Action_Scheduler_Service $action_scheduler_service ) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service );
		$this->method_title       = __( 'WooCommerce Payments - SEPA', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via SEPA Direct Debit.', 'woocommerce-payments' );
		$this->title              = __( 'SEPA Direct Debit', 'woocommerce-payments' );
		$this->description        = __( 'Mandate Information.', 'woocommerce-payments' );
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->supports( 'tokenization' ) && is_checkout();

			wp_localize_script( 'wcpay-sepa-checkout', 'wcpay_config', $this->get_payment_fields_js_config() );
			wp_enqueue_script( 'wcpay-sepa-checkout' );

			$prepared_customer_data = $this->get_prepared_customer_data();
			if ( ! empty( $prepared_customer_data ) ) {
				wp_localize_script( 'wcpay-sepa-checkout', 'wcpayCustomerData', $prepared_customer_data );
			}

			wp_enqueue_style(
				'wcpay-sepa-checkout',
				plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/checkout.css' )
			);

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>
			<p>
				<?php
				// translators: %s: statement descriptor.
				echo wp_kses_post( sprintf( __( 'By providing your IBAN and confirming this payment, you are authorizing %s and Stripe, our payment service provider, to send instructions to your bank to debit your account and your bank to debit your account in accordance with those instructions. You are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited.', 'woocommerce-payments' ), $this->get_account_statement_descriptor() ) );
				?>
			</p>
			<?php if ( $this->is_in_test_mode() ) : ?>
				<p class="testmode-info">
					<?php
					echo WC_Payments_Utils::esc_interpolated_html(
					/* translators: link to Stripe testing page */
						__( '<strong>Test mode:</strong> use the test SEPA card DE89370400440532013000 with account number 000123456, or any test card numbers listed <a>here</a>.', 'woocommerce-payments' ),
						[
							'strong' => '<strong>',
							'a'      => '<a href="https://stripe.com/docs/testing#sepa-direct-debit" target="_blank">',
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

			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
				<div id="wcpay-sepa-element"></div>
				<div id="wcpay-sepa-errors" role="alert"></div>
				<input id="wcpay-payment-method-sepa" type="hidden" name="wcpay-payment-method-sepa" />

				<?php
				if ( $this->is_saved_cards_enabled() ) {
					$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_payments_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
					$this->save_payment_method_checkbox( $force_save_payment );
				}
				?>

			</fieldset>
			<?php
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
