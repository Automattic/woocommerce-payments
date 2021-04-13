<?php
/**
 * Class Giropay
 *
 * @package WCPay\Payment_Gateway
 */

namespace WCPay\Payment_Method;

use WCPay\Logger;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments;
use WC_Payments_Utils;

/**
 * Giropay Payment method extended from card payment method.
 * Loads different JS files and fields and handles a redirect payment method.
 */
class Giropay extends WC_Payment_Gateway_WCPay {
	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments_giropay';

	const METHOD_ENABLED_KEY = 'giropay_enabled';

	/**
	 * Giropay Constrictor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                  - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service         - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service            - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service - Action Scheduler service instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, WC_Payments_Customer_Service $customer_service, WC_Payments_Token_Service $token_service, WC_Payments_Action_Scheduler_Service $action_scheduler_service ) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service );
		$this->method_title       = __( 'WooCommerce Payments - Giropay', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via Giropay.', 'woocommerce-payments' );
		$this->title              = __( 'Giropay', 'woocommerce-payments' );
		$this->description        = __( 'You will be redirected to Giropay.', 'woocommerce-payments' );
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
			'wcpay-giropay-checkout',
			plugins_url( 'dist/giropay_checkout.js', WCPAY_PLUGIN_FILE ),
			[ 'stripe', 'wc-checkout' ],
			WC_Payments::get_file_version( 'dist/giropay_checkout.js' ),
			true
		);
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			wp_localize_script( 'wcpay-giropay-checkout', 'wcpay_config', $this->get_payment_fields_js_config() );
			wp_enqueue_script( 'wcpay-giropay-checkout' );

			wp_enqueue_style(
				'wcpay-giropay-checkout',
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
						__( '<strong>Test mode:</strong> You will be redirected to a test Stripe page to approve or deny payment.', 'woocommerce-payments' ),
						[
							'strong' => '<strong>',
						]
					);
					?>
				</p>
			<?php endif; ?>
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

	/**
	 * Process the payment for a given order.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			$payment_information   = $this->prepare_payment_information( $order );
			$intent_api_parameters = [
				'payment_method_types' => [ 'giropay' ],
				'payment_method_data'  => [
					'type'            => 'giropay',
					'billing_details' => [
						'name' => sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() ),
					],
				],
				'return_url'           => wp_sanitize_redirect( esc_url_raw( add_query_arg( [ 'order_id' => $order_id ], $this->get_return_url( $order ) ) ) ),
			];

			return $this->process_payment_for_order( WC()->cart, $payment_information, $intent_api_parameters );
		} catch ( Exception $e ) {
			// TODO: Create more exceptions to handle merchant specific errors.
			$error_message = $e->getMessage();
			if ( is_a( $e, Connection_Exception::class ) ) {
				$error_message = __( 'There was an error while processing the payment. If you continue to see this notice, please contact the admin.', 'woocommerce-payments' );
			}

			wc_add_notice( $error_message, 'error' );

			$order->update_status( 'failed' );

			if ( ! empty( $payment_information ) ) {
				$note = sprintf(
					WC_Payments_Utils::esc_interpolated_html(
						/* translators: %1: the failed payment amount, %2: error message  */
						__(
							'A payment of %1$s <strong>failed</strong> to complete with the following message: <code>%2$s</code>.',
							'woocommerce-payments'
						),
						[
							'strong' => '<strong>',
							'code'   => '<code>',
						]
					),
					wc_price( $order->get_total() ),
					esc_html( rtrim( $e->getMessage(), '.' ) )
				);
				$order->add_order_note( $note );
			}

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}
}
