<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;

/**
 * Gateway class for WooCommerce Payments
 */
class WC_Payment_Gateway_WCPay extends WC_Payment_Gateway_CC {

	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client       $payments_api_client - WooCommerce Payments API client.
	 * @param WC_Payments_Account          $account             - Account class instance.
	 * @param WC_Payments_Customer_Service $customer_service    - Customer class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service
	) {
		$this->payments_api_client = $payments_api_client;
		$this->account             = $account;
		$this->customer_service    = $customer_service;

		$this->id                 = self::GATEWAY_ID;
		$this->icon               = ''; // TODO: icon.
		$this->has_fields         = true;
		$this->method_title       = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via credit card.', 'woocommerce-payments' );
		$this->title              = __( 'Credit card', 'woocommerce-payments' );
		$this->description        = __( 'Enter your card details', 'woocommerce-payments' );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Define setting fields.
		$this->form_fields = array(
			'enabled'         => array(
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'account_details' => array(
				'type' => 'account_actions',
			),
			'account_status'  => array(
				'type' => 'account_status',
			),
			'manual_capture'  => array(
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Manually capture funds within 7 days after the customer authorizes payment on checkout.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'test_mode'       => array(
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'enable_logging'  => array(
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
		);

		if ( $this->is_in_dev_mode() ) {
			$this->form_fields['test_mode']['custom_attributes']['disabled']      = 'disabled';
			$this->form_fields['test_mode']['label']                              = __( 'Dev mode is active so all transactions will be in test mode. This setting is only available to live accounts.', 'woocommerce-payments' );
			$this->form_fields['enable_logging']['custom_attributes']['disabled'] = 'disabled';
			$this->form_fields['enable_logging']['label']                         = __( 'Dev mode is active so logging is on by default.', 'woocommerce-payments' );
		}

		// Load the settings.
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
		add_action( 'woocommerce_order_action_capture_charge', array( $this, 'capture_charge' ) );
		add_action( 'woocommerce_order_action_cancel_authorization', array( $this, 'cancel_authorization' ) );
	}

	/**
	 * Returns the URL of the configuration screen for this gateway, for use in internal links.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . self::GATEWAY_ID );
	}

	/**
	 * Check the defined constant to determine the current plugin mode.
	 *
	 * @return bool
	 */
	public function is_in_dev_mode() {
		return apply_filters( 'wcpay_dev_mode', defined( 'WCPAY_DEV_MODE' ) && WCPAY_DEV_MODE );
	}

	/**
	 * Returns whether test_mode or dev_mode is active for the gateway
	 *
	 * @return boolean Test mode enabled if true, disabled if false
	 */
	public function is_in_test_mode() {
		if ( $this->is_in_dev_mode() ) {
			return true;
		}

		return 'yes' === $this->get_option( 'test_mode' );
	}

	/**
	 * Checks if the gateway is enabled, and also if it's configured enough to accept payments from customers.
	 *
	 * Use parent method value alongside other business rules to make the decision.
	 *
	 * @return bool Whether the gateway is enabled and ready to accept payments.
	 */
	public function is_available() {
		if ( 'USD' !== get_woocommerce_currency() ) {
			return false;
		}

		return parent::is_available() && $this->account->is_stripe_connected( false );
	}

	/**
	 * Add notice to WooCommerce Payments settings page explaining test mode when it's enabled.
	 */
	public function admin_options() {
		if ( $this->is_in_test_mode() ) {
			?>
			<div id="wcpay-test-mode-notice" class="notice notice-warning">
				<p>
					<b><?php esc_html_e( 'Test mode active: ', 'woocommerce-payments' ); ?></b>
					<?php esc_html_e( "All transactions are simulated. Customers can't make real purchases through WooCommerce Payments.", 'woocommerce-payments' ); ?>
				</p>
			</div>
			<?php
		}

		parent::admin_options();
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			// Add JavaScript for the payment form.
			$js_config = array(
				'publishableKey' => $this->account->get_publishable_key( $this->is_in_test_mode() ),
				'accountId'      => $this->account->get_stripe_account_id(),
			);

			// Register Stripe's JavaScript using the same ID as the Stripe Gateway plugin. This prevents this JS being
			// loaded twice in the event a site has both plugins enabled. We still run the risk of different plugins
			// loading different versions however.
			wp_register_script(
				'stripe',
				'https://js.stripe.com/v3/',
				array(),
				'3.0',
				true
			);

			wp_register_script(
				'wcpay-checkout',
				plugins_url( 'assets/js/wcpay-checkout.js', WCPAY_PLUGIN_FILE ),
				array( 'stripe', 'wc-checkout' ),
				WC_Payments::get_file_version( 'assets/js/wcpay-checkout.js' ),
				true
			);

			wp_localize_script( 'wcpay-checkout', 'wcpay_config', $js_config );
			wp_enqueue_script( 'wcpay-checkout' );

			wp_enqueue_style(
				'wcpay-checkout',
				plugins_url( 'assets/css/wcpay-checkout.css', WCPAY_PLUGIN_FILE ),
				array(),
				WC_Payments::get_file_version( 'assets/css/wcpay-checkout.css' )
			);

			// Output the form HTML.
			?>
			<fieldset>
				<?php if ( ! empty( $this->get_description() ) ) : ?>
					<legend><?php echo wp_kses_post( $this->get_description() ); ?></legend>
				<?php endif; ?>

				<?php if ( $this->is_in_test_mode() ) : ?>
					<p class="testmode-info">
					<?php
						echo WC_Payments_Utils::esc_interpolated_html(
							/* translators: link to Stripe testing page */
							__( '<strong>Test mode:</strong> use test card numbers listed <a>here</a>.', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'a'      => '<a href="https://docs.woocommerce.com/document/payments/testing/#test-cards" target="_blank">',
							]
						);
					?>
					</p>
				<?php endif; ?>

				<div id="wcpay-card-element"></div>
				<div id="wcpay-errors" role="alert"></div>
				<input id="wcpay-payment-method" type="hidden" name="wcpay-payment-method" />
			</fieldset>
			<?php
		} catch ( Exception $e ) {
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
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			$amount = $order->get_total();

			if ( $amount > 0 ) {
				// Get the payment method from the request (generated when the user entered their card details).
				$payment_method = $this->get_payment_method_from_request();
				$manual_capture = 'yes' === $this->get_option( 'manual_capture' );
				$name           = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );

				$metadata = [
					'customer_name'  => $name,
					'customer_email' => sanitize_email( $order->get_billing_email() ),
					'site_url'       => esc_url( get_site_url() ),
					'order_id'       => $order->get_id(),
				];

				// Create intention, try to confirm it & capture the charge (if 3DS is not required).
				$intent = $this->payments_api_client->create_and_confirm_intention(
					round( (float) $amount * 100 ),
					'usd',
					$payment_method,
					$manual_capture,
					$metadata
				);

				// TODO: We're not handling *all* sorts of things here. For example, redirecting to a 3DS auth flow.
				$transaction_id = $intent->get_id();
				$status         = $intent->get_status();

				if ( 'requires_capture' === $status ) {
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: the authorized amount, %2: transaction ID of the payment */
							__( 'A payment of %1$s was <strong>authorized</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'code'   => '<code>',
							]
						),
						wc_price( $amount ),
						$transaction_id
					);
					$order->update_status( 'on-hold', $note );
					$order->set_transaction_id( $transaction_id );
				} else {
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
							__( 'A payment of %1$s was <strong>successfully charged</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'code'   => '<code>',
							]
						),
						wc_price( $amount ),
						$transaction_id
					);
					$order->add_order_note( $note );
					$order->payment_complete( $transaction_id );
				}

				$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
				$order->update_meta_data( '_intention_status', $status );
				$order->save();
			} else {
				$order->payment_complete();
			}

			wc_reduce_stock_levels( $order_id );
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {
			// TODO: Create plugin specific exceptions so that we can be smarter about what we create notices for.
			wc_add_notice( $e->getMessage(), 'error' );

			$order->update_status( 'failed' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Extract the payment method from the request's POST variables
	 *
	 * @return string
	 * @throws Exception - If no payment method is found.
	 */
	private function get_payment_method_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		if ( ! isset( $_POST['wcpay-payment-method'] ) ) {
			// If no payment method is set then stop here with an error.
			throw new Exception( __( 'Payment method not found.', 'woocommerce-payments' ) );
		}

		$payment_method = wc_clean( $_POST['wcpay-payment-method'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification

		return $payment_method;
	}

	/**
	 * Can the order be refunded?
	 *
	 * @param  WC_Order $order Order object.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_meta( '_charge_id', true );
	}

	/**
	 * Refund a charge.
	 *
	 * @param  int    $order_id - the Order ID to process the refund for.
	 * @param  float  $amount   - the amount to refund.
	 * @param  string $reason   - the reason for refunding.
	 *
	 * @return bool - Whether the refund went through.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$charge_id = $order->get_meta( '_charge_id', true );

		try {
			if ( is_null( $amount ) ) {
				$refund = $this->payments_api_client->refund_charge( $charge_id );
			} else {
				$refund = $this->payments_api_client->refund_charge( $charge_id, round( (float) $amount * 100 ) );
			}
		} catch ( Exception $e ) {

			$note = sprintf(
				/* translators: %1: the successfully charged amount, %2: error message */
				__( 'A refund of %1$s failed to complete: %2$s', 'woocommerce-payments' ),
				wc_price( $amount ),
				$e->getMessage()
			);

			Logger::log( $note );
			$order->add_order_note( $note );

			return new WP_Error( $e->getMessage() );
		}

		if ( empty( $reason ) ) {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'A refund of %1$s was successfully processed using WooCommerce Payments.', 'woocommerce-payments' ),
				wc_price( $amount )
			);
		} else {
			$note = sprintf(
				/* translators: %1: the successfully charged amount, %2: reason */
				__( 'A refund of %1$s was successfully processed using WooCommerce Payments. Reason: %2$s', 'woocommerce-payments' ),
				wc_price( $amount ),
				$reason
			);
		}
		$order->add_order_note( $note );

		return true;
	}

	/**
	 * Overrides the original method in woo's WC_Settings_API in order to conditionally render the enabled checkbox.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 *
	 * @return string Checkbox markup or empty string.
	 */
	public function generate_checkbox_html( $key, $data ) {
		if ( 'enabled' === $key && ! $this->account->is_stripe_connected( false ) ) {
			return '';
		}
		return parent::generate_checkbox_html( $key, $data );
	}

	/**
	 * Outputs the container for account status information.
	 *
	 * @return string Container markup or empty if the account is not connected.
	 */
	public function generate_account_status_html() {
		if ( ! $this->account->is_stripe_connected( false ) ) {
			return '';
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<?php echo esc_html( __( 'Account status', 'woocommerce-payments' ) ); ?>
			</th>
			<td>
				<div id="wcpay-account-status-container"></div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate markup for account actions
	 */
	public function generate_account_actions_html() {
		try {
			$stripe_connected = $this->account->try_is_stripe_connected();
			if ( $stripe_connected ) {
				$description = WC_Payments_Utils::esc_interpolated_html(
					/* translators: 1) dashboard login URL */
					__( '<a>View and edit account details</a>', 'woocommerce-payments' ),
					[
						'a' => '<a href="' . WC_Payments_Account::get_login_url() . '">',
					]
				);
			} else {
				$description = WC_Payments_Account::get_connection_message_html();
			}
		} catch ( Exception $e ) {
			// do not render the actions if the server is unreachable.
			$description = esc_html__( 'Error determining the connection status.', 'woocommerce-payments' );
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<?php echo esc_html( __( 'Account', 'woocommerce-payments' ) ); ?>
			</th>
			<td>
				<?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add capture and cancel actions for orders with an authorized charge.
	 *
	 * @param array $actions - Actions to make available in order actions metabox.
	 */
	public function add_order_actions( $actions ) {
		global $theorder;

		if ( $this->id !== $theorder->get_payment_method() ) {
			return $actions;
		}

		if ( 'requires_capture' !== $theorder->get_meta( '_intention_status', true ) ) {
			return $actions;
		}

		$new_actions = array(
			'capture_charge'       => __( 'Capture charge', 'woocommerce-payments' ),
			'cancel_authorization' => __( 'Cancel authorization', 'woocommerce-payments' ),
		);

		return array_merge( $new_actions, $actions );
	}

	/**
	 * Capture previously authorized charge.
	 *
	 * @param WC_Order $order - Order to capture charge on.
	 */
	public function capture_charge( $order ) {
		$amount = $order->get_total();
		$intent = $this->payments_api_client->capture_intention(
			$order->get_transaction_id(),
			round( (float) $amount * 100 )
		);
		$status = $intent->get_status();

		$order->update_meta_data( '_intention_status', $status );
		$order->save();

		if ( 'succeeded' === $status ) {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the successfully charged amount */
					__( 'A payment of %1$s was <strong>successfully captured</strong> using WooCommerce Payments.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
			$order->payment_complete();
		} else {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the successfully charged amount */
					__( 'A capture of %1$s <strong>failed</strong> to complete.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
		}
	}

	/**
	 * Cancel previously authorized charge.
	 *
	 * @param WC_Order $order - Order to cancel authorization on.
	 */
	public function cancel_authorization( $order ) {
		$intent = $this->payments_api_client->cancel_intention( $order->get_transaction_id() );
		$status = $intent->get_status();

		$order->update_meta_data( '_intention_status', $status );
		$order->save();

		if ( 'canceled' === $status ) {
			$order->update_status(
				'cancelled',
				WC_Payments_Utils::esc_interpolated_html(
					__( 'Payment authorization was successfully <strong>cancelled</strong>.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				)
			);
		} else {
			$order->add_order_note(
				WC_Payments_Utils::esc_interpolated_html(
					__( 'Canceling authorization <strong>failed</strong> to complete.', 'woocommerce-payments' ),
					[ 'strong' => '<strong>' ]
				)
			);
		}
	}
}
