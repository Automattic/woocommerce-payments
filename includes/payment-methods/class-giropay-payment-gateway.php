<?php
/**
 * Class Giropay_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WCPay\Exceptions\Connection_Exception;
use WCPay\Logger;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments;
use WC_Payments_Utils;
use Exception;
use WC_Payments_Explicit_Price_Formatter;

/**
 * Giropay Payment method extended from card payment method.
 * Loads different JS files and fields and handles a redirect payment method.
 */
class Giropay_Payment_Gateway extends WC_Payment_Gateway_WCPay {
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

		add_action( 'wp', [ $this, 'maybe_process_redirect_order' ] );
	}

	/**
	 * Processes redirect payments.
	 * This method is currently specific to the Giropay redirect payment method.
	 *
	 * @param int $order_id The order ID being processed.
	 */
	public function process_redirect_payment( $order_id ) {
		try {
			$intent_id = isset( $_GET['payment_intent'] ) ? wc_clean( wp_unslash( $_GET['payment_intent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			if ( empty( $intent_id ) ) {
				return;
			}

			if ( empty( $order_id ) ) {
				return;
			}

			$order = wc_get_order( $order_id );

			if ( ! is_object( $order ) ) {
				return;
			}

			if ( $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
				return;
			}

			// Result from Stripe API request.
			$response = null;

			Logger::log( "Begin processing redirect payment for order $order_id for the amount of {$order->get_total()}" );

			/**
			 * Get payment intent to confirm status
			 */
			$intent = $this->payments_api_client->get_intent( $order->get_transaction_id() );
			$status = $intent->get_status();
			$amount = $order->get_total();

			if ( 'succeeded' === $status ) {
				$this->attach_intent_info_to_order(
					$order,
					$intent->get_id(),
					$intent->get_status(),
					$intent->get_payment_method_id(),
					$intent->get_customer_id(),
					$intent->get_charge_id(),
					$intent->get_currency()
				);

				return;
			} else {
				$error = $intent->get_last_payment_error();
				if ( ! empty( $error ) ) {
					$error_message = isset( $error['message'] ) ? $error['message'] : 'unknown';
					$error_code    = isset( $error['code'] ) ? $error['code'] : 'unknown';
					Logger::log( sprintf( 'Giropay failed: %s (%s)', $error_message, $error_code ) );
					/* translators: localized exception message */
					$order->update_status( 'failed', sprintf( __( 'Giropay payment failed: %s', 'woocommerce-payments' ), $error_message ) );
				}

				wc_add_notice( __( 'Giropay payment has failed. If you continue to see this notice, please contact the admin.', 'woocommerce-payments' ), 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
		} catch ( Exception $e ) {
			Logger::log( 'Error: ' . $e->getMessage() );

			/* translators: localized exception message */
			$order->update_status( 'failed', sprintf( __( 'Giropay payment failed: %s', 'woocommerce-payments' ), $e->getLocalizedMessage() ) );

			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Check for a redirect payment method on order received page.
	 */
	public function maybe_process_redirect_order() {
		if ( ! is_order_received_page() || empty( $_GET['payment_intent_client_secret'] ) || empty( $_GET['payment_intent'] || empty( $_GET['wc_payment_method'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$payment_method = isset( $_GET['wc_payment_method'] ) ? wc_clean( wp_unslash( $_GET['wc_payment_method'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::GATEWAY_ID !== $payment_method ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? wc_clean( wp_unslash( $_GET['order_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$this->process_redirect_payment( $order_id );
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
				'WCPAY_CHECKOUT',
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
				'return_url'           => wp_sanitize_redirect(
					esc_url_raw(
						add_query_arg(
							[
								'order_id'          => $order_id,
								'wc_payment_method' => self::GATEWAY_ID,
							],
							$this->get_return_url( $order )
						)
					)
				),
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
					WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order ),
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
