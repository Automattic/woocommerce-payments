<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
	 * Returns whether test_mode is active for the gateway
	 *
	 * @return boolean Test mode enable if true, disabled if false
	 */
	public function get_test_mode() {
		return 'yes' === $this->get_option( 'test_mode' );
	}

	/**
	 * Returns the gateway publishable key based on test mode
	 *
	 * @return string Stripe's publishable key
	 */
	public function get_publishable_key() {
		return $this->get_test_mode()
			? $this->get_option( 'test_publishable_key' )
			: $this->get_option( 'publishable_key' );
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
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;

		$this->id                 = self::GATEWAY_ID;
		$this->icon               = ''; // TODO: icon.
		$this->has_fields         = true;
		$this->method_title       = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via credit card.', 'woocommerce-payments' );
		$this->title              = __( 'Credit Card', 'woocommerce-payments' );
		$this->description        = __( 'Enter your card details', 'woocommerce-payments' );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the settings.
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// TODO: move somewhere else?
		add_action( 'admin_notices', array( $this, 'display_errors' ) );
		add_action( 'woocommerce_init', array( $this, 'maybe_handle_oauth' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );

		add_action( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
		add_action( 'woocommerce_order_action_capture_charge', array( $this, 'capture_charge' ) );
		add_action( 'woocommerce_order_action_cancel_authorization', array( $this, 'cancel_authorization' ) );
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

		return parent::is_available() && $this->is_stripe_connected();
	}

	/**
	 * Get the form fields after they are initialized.
	 *
	 * @return array of options
	 */
	public function get_form_fields() {
		// Ensure that the `woocommerce_payments_allow_test_mode` option exists.
		if ( ! get_option( 'woocommerce_payments_allow_test_mode' ) ) {
			update_option( 'woocommerce_payments_allow_test_mode', 'no' );
		}

		// Define setting fields.
		$this->form_fields = array();

		$this->form_fields['account_details'] = array(
			'type' => 'account_actions',
		);

		$this->form_fields['manual_capture'] = array(
			'title'       => __( 'Manual Capture', 'woocommerce-payments' ),
			'label'       => __( 'Issue authorization and capture later', 'woocommerce-payments' ),
			'type'        => 'checkbox',
			'description' => __( 'Manually capture funds within 7 days after the customer authorizes payment on checkout.', 'woocommerce-payments' ),
			'default'     => 'no',
			'desc_tip'    => true,
		);

		if ( 'yes' === get_option( 'woocommerce_payments_allow_test_mode' ) ) {
			$this->form_fields['test_mode'] = array(
				'title'       => __( 'Test Mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			);
		}

		$this->form_fields['enabled'] = array(
			'title'       => __( 'Enable/Disable', 'woocommerce-payments' ),
			'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		);

		return parent::get_form_fields();
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		// Add JavaScript for the payment form.
		$js_config = array(
			'publishableKey' => $this->get_publishable_key(),
			'accountId'      => $this->get_option( 'stripe_account_id' ),
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

			<?php if ( $this->get_test_mode() ) : ?>
				<p class="testmode-info">
				<?php
					/* translators: link to Stripe testing page */
					echo wp_kses_post( sprintf( __( '<strong>Test mode:</strong> use test card numbers listed <a href="%s" target="_blank">here</a>.', 'woocommerce-payments' ), 'https://stripe.com/docs/testing' ) );
				?>
				</p>
			<?php endif; ?>

			<div id="wcpay-card-element"></div>
			<div id="wcpay-errors" role="alert"></div>
			<input id="wcpay-payment-method" type="hidden" name="wcpay-payment-method" />
		</fieldset>
		<?php
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

			$transaction_id = '';

			if ( $amount > 0 ) {
				// Get the payment method from the request (generated when the user entered their card details).
				$payment_method = $this->get_payment_method_from_request();

				$manual_capture = 'yes' === $this->get_option( 'manual_capture' );

				// Create intention, try to confirm it & capture the charge (if 3DS is not required).
				$intent = $this->payments_api_client->create_and_confirm_intention(
					round( (float) $amount * 100 ),
					'usd',
					$payment_method,
					$manual_capture
				);

				// TODO: We're not handling *all* sorts of things here. For example, redirecting to a 3DS auth flow.
				$transaction_id = $intent->get_id();
				$status         = $intent->get_status();

				if ( 'requires_capture' === $status ) {
					$note = sprintf(
						/* translators: %1: the authorized amount, %2: transaction ID of the payment */
						__( 'A payment of %1$s was <strong>authorized</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
						wc_price( $amount ),
						$transaction_id
					);
					$order->update_status( 'on-hold', $note );
					$order->set_transaction_id( $transaction_id );
				} else {
					$note = sprintf(
						/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
						__( 'A payment of %1$s was <strong>successfully charged</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
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
			// TODO: Create or wire-up a logger for writing messages to the server filesystem.
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
	 * @return bool|WP_Error - Whether the refund went through, or an error.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$charge_id = $order->get_meta( '_charge_id', true );

		if ( is_null( $amount ) ) {
			$refund = $this->payments_api_client->refund_charge( $charge_id );
		} else {
			$refund = $this->payments_api_client->refund_charge( $charge_id, round( (float) $amount * 100 ) );
		}

		if ( is_wp_error( $refund ) ) {
			// TODO log error.
			$note = sprintf(
				/* translators: %1: the successfully charged amount, %2: error message */
				__( 'A refund of %1$s failed to complete: %2$s', 'woocommerce-payments' ),
				wc_price( $amount ),
				$refund->get_error_message()
			);
			$order->add_order_note( $note );

			return $refund;
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
	 * Filter function to add Stripe to the list of allowed redirect hosts
	 *
	 * @param array $hosts - array of allowed hosts.
	 *
	 * @return array allowed hosts
	 */
	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'connect.stripe.com';
		return $hosts;
	}

	/**
	 * Checks whether the user has a Stripe account already connected
	 *
	 * @return boolean True if a Stripe account is registered, false otherwise.
	 */
	public function is_stripe_connected() {
		return $this->get_option( 'stripe_account_id' ) && $this->get_publishable_key();
	}

	/**
	 * Checks whether there are pending requirements for the Stripe account
	 *
	 * @param array $account Account to verify requirements.
	 * @return boolean True if there are pending requirements, false otherwise.
	 */
	public function account_has_pending_requirements( $account ) {
		$currently_due  = isset( $account['requirements']['currently_due'] ) ? $account['requirements']['currently_due'] : array();
		$past_due       = isset( $account['requirements']['past_due'] ) ? $account['requirements']['past_due'] : array();
		$eventually_due = isset( $account['requirements']['eventually_due'] ) ? $account['requirements']['eventually_due'] : array();

		if (
			! empty( $currently_due ) ||
			! empty( $past_due ) ||
			! empty( $eventually_due )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Get Stripe login url
	 *
	 * @return string Stripe account login url.
	 */
	public function get_login_url() {
		return wp_nonce_url( add_query_arg( [ 'wcpay-login' => '1' ] ), 'wcpay-login' );
	}

	/**
	 * Get Stripe connect url
	 *
	 * @return string Stripe account login url.
	 */
	public function get_connect_url() {
		return wp_nonce_url( add_query_arg( [ 'wcpay-connect' => '1' ] ), 'wcpay-connect' );
	}

	/**
	 * Get Stripe connect message with connect link
	 */
	public function get_connect_message() {
		return sprintf(
			/* translators: 1) oauth entry point URL */
			__( 'Accept credit cards online. Simply verify your business details to activate WooCommerce Payments. <a href="%1$s">Get started</a>', 'woocommerce-payments' ),
			$this->get_connect_url()
		);
	}

	/**
	 * Get Stripe pending requirements message with dashboard link, based on current deadline.
	 *
	 * If $current_deadline is null, it means that the requirements are already past due.
	 *
	 * TODO: Payouts is a Stripe dashboard terminology and it's being used here to avoid confusion.
	 * Once we have our custom dashboard running, Payouts should be renamed to Deposits.
	 *
	 * @param int $current_deadline Timestamp for when the requirements are due.
	 */
	public function get_verify_requirements_message( $current_deadline = null ) {
		if ( null !== $current_deadline ) {
			return sprintf(
				/* translators: 1) formatted requirements current deadline 2) dashboard login URL */
				__( 'We require additional details about your business. Please provide the required information by %1$s to avoid an interruption in your scheduled payouts. <a href="%2$s">Update now</a>', 'woocommerce-payments' ),
				/* translators: date time format to display deadline in "...provide the required information by %1$s to avoid an..."*/
				date_i18n( __( 'ga M j, Y', 'woocommerce-payments' ), $current_deadline ),
				$this->get_login_url()
			);
		}

		return sprintf(
			/* translators: 1) dashboard login URL */
			__( 'Your payouts have been suspended. We require additional details about your business. Please provide the requested information so you may continue to receive your payouts. <a href="%1$s">Update now</a>', 'woocommerce-payments' ),
			$this->get_login_url()
		);
	}

	/**
	 * Generate markup for account actions
	 */
	public function generate_account_actions_html() {
		if ( $this->is_stripe_connected() ) {
			$description = sprintf(
				/* translators: 1) dashboard login URL */
				__( '<a href="%1$s">View payouts and account details</a>', 'woocommerce-payments' ),
				$this->get_login_url()
			);
		} else {
			$description = $this->get_connect_message();
		}

		// Allow the description text to be altered by filters.
		$description = apply_filters(
			'wc_payments_account_actions',
			$description,
			$this->is_stripe_connected(),
			$this->get_login_url(),
			$this->get_connect_url()
		);

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<?php echo esc_html( __( 'Account', 'woocommerce-payments' ) ); ?>
			</th>
			<td>
				<?php echo wp_kses_post( $description ); ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle OAuth (login/init/redirect) routes
	 */
	public function maybe_handle_oauth() {
		if ( ! is_admin() ) {
			return;
		}

		if ( isset( $_GET['wcpay-connect'] ) && check_admin_referer( 'wcpay-connect' ) ) {
			// initialize the connection flow and redirect the user.
			$current_user = wp_get_current_user();

			$oauth_data = $this->payments_api_client->get_oauth_data(
				$this->get_settings_url(),
				array(
					'email'         => $current_user->user_email,
					'business_name' => get_bloginfo( 'name' ),
				)
			);

			if ( is_wp_error( $oauth_data ) && 'wcpay_test_mode_unavailable' === $oauth_data->get_error_code() ) {
				update_option( 'woocommerce_payments_allow_test_mode', 'no' );
				$this->update_option( 'test_mode', 'no' );

				// Translators: %1$s is replaced with an error code.
				$error_message = __( 'Test mode is not available for your account. %1$s', 'woocommerce-payments' );
				$error_message = sprintf( $error_message, 'paJDYF-qU-p2' );
				$this->add_error( $error_message );
				return;
			}

			if ( is_wp_error( $oauth_data ) || ! isset( $oauth_data['url'] ) ) {
				$this->add_error( __( 'There was a problem redirecting you to the account connection page. Please try again.', 'woocommerce-payments' ) );
				return;
			}

			if ( false === $oauth_data['url'] ) {
				$account_id           = sanitize_text_field( wp_unslash( $oauth_data['account_id'] ) );
				$live_publishable_key = sanitize_text_field( wp_unslash( $oauth_data['live_publishable_key'] ) );
				$test_publishable_key = sanitize_text_field( wp_unslash( $oauth_data['test_publishable_key'] ) );
				$this->update_option( 'stripe_account_id', $account_id );
				$this->update_option( 'publishable_key', $live_publishable_key );
				$this->update_option( 'test_publishable_key', $test_publishable_key );
				$this->update_option( 'enabled', 'yes' );
				wp_safe_redirect( $this->get_settings_url() );
				exit;
			}

			set_transient( 'wcpay_oauth_state', $oauth_data['state'], DAY_IN_SECONDS );

			wp_safe_redirect( $oauth_data['url'] );
			exit;
		}

		if ( isset( $_GET['wcpay-login'] ) && check_admin_referer( 'wcpay-login' ) ) {
			// retrieve the one-time login url and redirect to it.
			$login_data = $this->payments_api_client->get_login_data( $this->get_settings_url() );
			if ( is_wp_error( $login_data ) || ! isset( $login_data['url'] ) ) {
				$this->add_error( __( 'There was a problem redirecting you to the account dashboard. Please try again.', 'woocommerce-payments' ) );
				return;
			}
			wp_safe_redirect( $login_data['url'] );
			exit;
		}

		if (
			isset( $_GET['wcpay-state'] )
			&& isset( $_GET['wcpay-account-id'] )
			&& isset( $_GET['wcpay-publishable-key'] )
			&& isset( $_GET['wcpay-mode'] )
		) {
			// finish the connection flow and save the settings.
			$state = sanitize_text_field( wp_unslash( $_GET['wcpay-state'] ) );
			if ( get_transient( 'wcpay_oauth_state' ) !== $state ) {
				$this->add_error( __( 'There was a problem processing your account data. Please try again.', 'woocommerce-payments' ) );
				return;
			}
			delete_transient( 'wcpay_oauth_state' );

			$account_id      = sanitize_text_field( wp_unslash( $_GET['wcpay-account-id'] ) );
			$publishable_key = sanitize_text_field( wp_unslash( $_GET['wcpay-publishable-key'] ) );
			$mode            = sanitize_text_field( wp_unslash( $_GET['wcpay-mode'] ) );

			$this->update_option( 'stripe_account_id', $account_id );
			if ( 'live' === $mode ) {
				$this->update_option( 'publishable_key', $publishable_key );
			} else {
				$this->update_option( 'test_publishable_key', $publishable_key );
			}

			wp_safe_redirect( remove_query_arg( [ 'wcpay-state', 'wcpay-account-id', 'wcpay-publishable-key', 'wcpay-mode' ] ) );
			exit;
		}
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
				/* translators: %1: the successfully charged amount */
				__( 'A payment of %1$s was <strong>successfully captured</strong> using WooCommerce Payments.', 'woocommerce-payments' ),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
			$order->payment_complete();
		} else {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'A capture of %1$s <strong>failed</strong> to complete.', 'woocommerce-payments' ),
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
			$order->update_status( 'cancelled', __( 'Payment authorization was successfully <strong>cancelled</strong>.', 'woocommerce-payments' ) );
		} else {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'Canceling authorization <strong>failed</strong> to complete.', 'woocommerce-payments' ),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
		}
	}
}
