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
	 * Set of parameters to build the URL to the gateway's settings page.
	 *
	 * @var string[]
	 */
	private static $settings_url_params = [
		'page'    => 'wc-settings',
		'tab'     => 'checkout',
		'section' => self::GATEWAY_ID,
	];

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
		$this->supports           = [
			'products',
			'refunds',
		];

		// Define setting fields.
		$this->form_fields = [
			'enabled'         => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_details' => [
				'type' => 'account_actions',
			],
			'account_status'  => [
				'type' => 'account_status',
			],
			'manual_capture'  => [
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Manually capture funds within 7 days after the customer authorizes payment on checkout.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'test_mode'       => [
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'enable_logging'  => [
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
		];

		if ( $this->is_in_dev_mode() ) {
			$this->form_fields['test_mode']['custom_attributes']['disabled']      = 'disabled';
			$this->form_fields['test_mode']['label']                              = __( 'Dev mode is active so all transactions will be in test mode. This setting is only available to live accounts.', 'woocommerce-payments' );
			$this->form_fields['enable_logging']['custom_attributes']['disabled'] = 'disabled';
			$this->form_fields['enable_logging']['label']                         = __( 'Dev mode is active so logging is on by default.', 'woocommerce-payments' );
		}

		// Load the settings.
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_order_actions', [ $this, 'add_order_actions' ] );
		add_action( 'woocommerce_order_action_capture_charge', [ $this, 'capture_charge' ] );
		add_action( 'woocommerce_order_action_cancel_authorization', [ $this, 'cancel_authorization' ] );

		add_action( 'wp_ajax_update_order_status', [ $this, 'update_order_status' ] );
		add_action( 'wp_ajax_nopriv_update_order_status', [ $this, 'update_order_status' ] );
	}

	/**
	 * Check if the payment gateway is connected. This method is also used by
	 * external plugins to check if a connection has been established.
	 */
	public function is_connected() {
		return $this->account->is_stripe_connected( false );
	}

	/**
	 * Returns true if the gateway needs additional configuration, false if it's ready to use.
	 *
	 * @see WC_Payment_Gateway::needs_setup
	 * @return bool
	 */
	public function needs_setup() {
		if ( ! $this->is_connected() ) {
			return true;
		}

		$account_status = $this->account->get_account_status_data();
		return parent::needs_setup() || ! empty( $account_status['error'] ) || ! $account_status['paymentsEnabled'];
	}

	/**
	 * Whether the current page is the WooCommerce Payments settings page.
	 *
	 * @return bool
	 */
	public static function is_current_page_settings() {
		return count( self::$settings_url_params ) === count( array_intersect_assoc( $_GET, self::$settings_url_params ) ); // phpcs:disable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Returns the URL of the configuration screen for this gateway, for use in internal links.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url() {
		return admin_url( add_query_arg( self::$settings_url_params, 'admin.php' ) );
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

		return parent::is_available() && ! $this->needs_setup();
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
			$js_config = [
				'publishableKey'         => $this->account->get_publishable_key( $this->is_in_test_mode() ),
				'accountId'              => $this->account->get_stripe_account_id(),
				'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
				'updateOrderStatusNonce' => wp_create_nonce( 'wcpay_update_order_status_nonce' ),
				'genericErrorMessage'    => __( 'There was a problem processing the payment. Please check your email and refresh the page to try again.', 'woocommerce-payments' ),
			];

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
				'wcpay-checkout',
				plugins_url( 'assets/js/wcpay-checkout.js', WCPAY_PLUGIN_FILE ),
				[ 'stripe', 'wc-checkout' ],
				WC_Payments::get_file_version( 'assets/js/wcpay-checkout.js' ),
				true
			);

			wp_localize_script( 'wcpay-checkout', 'wcpay_config', $js_config );
			wp_enqueue_script( 'wcpay-checkout' );

			wp_enqueue_style(
				'wcpay-checkout',
				plugins_url( 'assets/css/wcpay-checkout.css', WCPAY_PLUGIN_FILE ),
				[],
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
							__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC, or any test card numbers listed <a>here</a>.', 'woocommerce-payments' ),
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
				$email          = sanitize_email( $order->get_billing_email() );

				// Determine the customer making the payment, create one if we don't have one already.
				$user        = wp_get_current_user();
				$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );

				if ( null === $customer_id ) {
					// Create a new customer.
					$customer_id = $this->customer_service->create_customer_for_user( $user, $name, $email );
				} else {
					// Update the existing customer with the current details. In the event the old customer can't be
					// found a new one is created, so we update the customer ID here as well.
					$customer_id = $this->customer_service->update_customer_for_user( $customer_id, $user, $name, $email );
				}

				$metadata = [
					'customer_name'  => $name,
					'customer_email' => $email,
					'site_url'       => esc_url( get_site_url() ),
					'order_id'       => $order->get_id(),
				];

				// Create intention, try to confirm it & capture the charge (if 3DS is not required).
				$intent = $this->payments_api_client->create_and_confirm_intention(
					WC_Payments_Utils::prepare_amount( $amount, 'USD' ),
					'usd',
					$payment_method,
					$customer_id,
					$manual_capture,
					$metadata,
					$this->get_level3_data_from_order( $order )
				);

				// TODO: We're not handling *all* sorts of things here. For example, redirecting to a 3DS auth flow.
				$intent_id = $intent->get_id();
				$status    = $intent->get_status();

				switch ( $status ) {
					case 'succeeded':
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
							$intent_id
						);

						$order->update_meta_data( '_intent_id', $intent_id );
						$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
						$order->update_meta_data( '_intention_status', $status );
						$order->save();

						$order->add_order_note( $note );
						$order->payment_complete( $intent_id );
						break;
					case 'requires_capture':
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
							$intent_id
						);

						$order->update_status( 'on-hold', $note );
						$order->set_transaction_id( $intent_id );

						$order->update_meta_data( '_intent_id', $intent_id );
						$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
						$order->update_meta_data( '_intention_status', $status );
						$order->save();

						break;
					case 'requires_action':
						// Add a note in case the customer does not complete the payment (exits the page),
						// so the store owner has some information about what happened to create an order.
						$note = sprintf(
							WC_Payments_Utils::esc_interpolated_html(
								/* translators: %1: the authorized amount, %2: transaction ID of the payment */
								__( 'A payment of %1$s was <strong>started</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
								[
									'strong' => '<strong>',
									'code'   => '<code>',
								]
							),
							wc_price( $amount ),
							$intent_id
						);
						$order->add_order_note( $note );

						$order->update_meta_data( '_intent_id', $intent_id );
						$order->update_meta_data( '_intention_status', $status );
						$order->save();

						return [
							'result'   => 'success',
							'redirect' => sprintf( '#wcpay-confirm-pi:%s:%s', $order_id, $intent->get_client_secret() ),
						];
				}
			} else {
				$order->payment_complete();
			}

			wc_reduce_stock_levels( $order_id );
			WC()->cart->empty_cart();

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];
		} catch ( Exception $e ) {
			// TODO: Create plugin specific exceptions so that we can be smarter about what we create notices for.
			wc_add_notice( $e->getMessage(), 'error' );

			$order->update_status( 'failed' );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * Extract the payment method from the request's POST variables
	 *
	 * @return string
	 * @throws Exception - If no payment method is found.
	 */
	private function get_payment_method_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['wcpay-payment-method'] ) ) {
			// If no payment method is set then stop here with an error.
			throw new Exception( __( 'Payment method not found.', 'woocommerce-payments' ) );
		}

		$payment_method = wc_clean( $_POST['wcpay-payment-method'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:enable WordPress.Security.NonceVerification.Missing

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
				$refund = $this->payments_api_client->refund_charge( $charge_id, WC_Payments_Utils::prepare_amount( $amount, 'USD' ) );
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
		if ( 'enabled' === $key && ! $this->is_connected() ) {
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
		if ( ! $this->is_connected() ) {
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
				// This should never happen, if the account is not connected the merchant should have been redirected to the onboarding screen.
				// @see WC_Payments_Account::check_stripe_account_status.
				$description = esc_html__( 'Error determining the connection status.', 'woocommerce-payments' );
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

		$new_actions = [
			'capture_charge'       => __( 'Capture charge', 'woocommerce-payments' ),
			'cancel_authorization' => __( 'Cancel authorization', 'woocommerce-payments' ),
		];

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
			WC_Payments_Utils::prepare_amount( $amount, 'USD' ),
			$this->get_level3_data_from_order( $order )
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

	/**
	 * Create the level 3 data array to send to Stripe when making a purchase.
	 *
	 * @param WC_Order $order The order that is being paid for.
	 * @return array          The level 3 data to send to Stripe.
	 */
	public function get_level3_data_from_order( $order ) {
		// Get the order items. Don't need their keys, only their values.
		// Order item IDs are used as keys in the original order items array.
		$order_items = array_values( $order->get_items() );
		$currency    = $order->get_currency();

		$process_item  = function( $item ) use ( $currency ) {
			$description     = substr( $item->get_name(), 0, 26 );
			$quantity        = $item->get_quantity();
			$unit_cost       = WC_Payments_Utils::prepare_amount( $item->get_subtotal() / $quantity, $currency );
			$tax_amount      = WC_Payments_Utils::prepare_amount( $item->get_total_tax(), $currency );
			$discount_amount = WC_Payments_Utils::prepare_amount( $item->get_subtotal() - $item->get_total(), $currency );
			$product_id      = $item->get_variation_id()
				? $item->get_variation_id()
				: $item->get_product_id();

			return (object) [
				'product_code'        => (string) $product_id, // Up to 12 characters that uniquely identify the product.
				'product_description' => $description, // Up to 26 characters long describing the product.
				'unit_cost'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
				'quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
				'tax_amount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
				'discount_amount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
			];
		};
		$items_to_send = array_map( $process_item, $order_items );

		$level3_data = [
			'merchant_reference' => (string) $order->get_id(), // An alphanumeric string of up to  characters in length. This unique value is assigned by the merchant to identify the order. Also known as an “Order ID”.
			'shipping_amount'    => WC_Payments_Utils::prepare_amount( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), $currency ), // The shipping cost, in cents, as a non-negative integer.
			'line_items'         => $items_to_send,
		];

		// The customer’s U.S. shipping ZIP code.
		$shipping_address_zip = $order->get_shipping_postcode();
		if ( WC_Payments_Utils::is_valid_us_zip_code( $shipping_address_zip ) ) {
			$level3_data['shipping_address_zip'] = $shipping_address_zip;
		}

		// The merchant’s U.S. shipping ZIP code.
		$store_postcode = get_option( 'woocommerce_store_postcode' );
		if ( WC_Payments_Utils::is_valid_us_zip_code( $store_postcode ) ) {
			$level3_data['shipping_from_zip'] = $store_postcode;
		}

		return $level3_data;
	}

	/**
	 * Handle AJAX request after authenticating payment at checkout.
	 *
	 * This function is used to update the order status after the user has
	 * been asked to authenticate their payment.
	 *
	 * @throws Exception - If nonce is invalid.
	 */
	public function update_order_status() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_update_order_status_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ) );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : false;
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Exception( __( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ) );
			}

			$intent_id = $order->get_meta( '_intent_id', true );
			// An exception is thrown if an intent can't be found for the given intent ID.
			$intent    = $this->payments_api_client->get_intent( $intent_id );
			$status    = $intent->get_status();
			$intent_id = $intent->get_id();
			$amount    = $order->get_total();

			switch ( $status ) {
				case 'succeeded':
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
						$intent_id
					);
					$order->add_order_note( $note );
					$order->payment_complete( $intent_id );
					break;
				case 'requires_capture':
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
						$intent_id
					);
					// Save the note separately because if there is no change in status
					// then the note is not saved using WC_Order::update_status.
					$order->add_order_note( $note );
					$order->update_status( 'on-hold' );
					$order->set_transaction_id( $intent_id );
					break;
				case 'requires_payment_method':
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: the authorized amount, %2: transaction ID of the payment */
							__( 'A payment of %1$s <strong>failed</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
							[
								'strong' => '<strong>',
								'code'   => '<code>',
							]
						),
						wc_price( $amount ),
						$intent_id
					);
					// Save the note separately because if there is no change in status
					// then the note is not saved using WC_Order::update_status.
					$order->add_order_note( $note );
					$order->update_status( 'failed' );
					break;
			}

			if ( 'succeeded' === $status || 'requires_capture' === $status ) {
				// The order is successful, so update it to reflect that.
				$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
				$order->update_meta_data( '_intention_status', $status );
				$order->save();

				wc_reduce_stock_levels( $order_id );
				WC()->cart->empty_cart();

				// Send back redirect URL in the successful case.
				echo wp_json_encode(
					[
						'return_url' => $this->get_return_url( $order ),
					]
				);
				wp_die();
			}
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			echo wp_json_encode(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
			wp_die();
		}
	}
}
