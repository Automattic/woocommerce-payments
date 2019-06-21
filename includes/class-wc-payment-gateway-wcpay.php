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
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * API access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

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
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Define setting fields.
		$this->form_fields = array(
			'enabled'              => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                => array(
				'title'       => __( 'Title', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-payments' ),
				'default'     => __( 'Credit Card', 'woocommerce-payments' ),
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'payment_details'      => array(
				'type' => 'account_actions',
			),
			'stripe_account_id'    => array(
				'title'       => __( 'Stripe Account ID', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your account ID from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode'             => array(
				'title'       => __( 'Test Mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_publishable_key' => array(
				'title'       => __( 'Test Publishable Key', 'woocommerce-payments' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'publishable_key'      => array(
				'title'       => __( 'Live Publishable Key', 'woocommerce-payments' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'manual_capture'       => array(
				'title'       => __( 'Manual Capture', 'woocommerce-payments' ),
				'label'       => __( 'Separate authorization and capture', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Manually capture funds within 7 days after the customer authorizes payment on checkout.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);

		// Load the settings.
		$this->init_settings();

		// Extract values we want to use in this class from the settings.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->testmode        = ( ! empty( $this->settings['testmode'] ) && 'yes' === $this->settings['testmode'] ) ? true : false;
		$this->publishable_key = ! empty( $this->settings['publishable_key'] ) ? $this->settings['publishable_key'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $this->settings['test_publishable_key'] ) ? $this->settings['test_publishable_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// TODO: move somewhere else?
		add_action( 'admin_notices', array( $this, 'display_errors' ) );
		add_action( 'woocommerce_init', array( $this, 'maybe_handle_oauth' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		// Add JavaScript for the payment form.
		$js_config = array(
			'publishableKey' => $this->publishable_key,
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
			filemtime( WCPAY_ABSPATH . 'assets/js/wcpay-checkout.js' ),
			true
		);

		wp_localize_script( 'wcpay-checkout', 'wcpay_config', $js_config );
		wp_enqueue_script( 'wcpay-checkout' );

		wp_enqueue_style(
			'wcpay-checkout',
			plugins_url( 'assets/css/wcpay-checkout.css', WCPAY_PLUGIN_FILE ),
			array(),
			filemtime( WCPAY_ABSPATH . 'assets/css/wcpay-checkout.css' )
		);

		// Output the form HTML.
		?>
		<fieldset>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<legend><?php echo wp_kses_post( $this->get_description() ); ?></legend>
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

				$capture_method = 'yes' === $this->get_option( 'manual_capture' ) ? 'manual' : 'automatic';

				// Create intention, try to confirm it & capture the charge (if 3DS is not required).
				$intent = $this->payments_api_client->create_and_confirm_intention(
					round( (float) $amount * 100 ),
					'usd',
					$payment_method,
					$capture_method
				);

				// TODO: We're not handling *all* sorts of things here. For example, redirecting to a 3DS auth flow.
				$transaction_id = $intent->get_id();

				$note = sprintf(
					/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
					__( 'A payment of %1$s was successfully charged using WooCommerce Payments (Transaction #%2$s)', 'woocommerce-payments' ),
					wc_price( $amount ),
					$transaction_id
				);
				$order->add_order_note( $note );

				$order->update_meta_data( '_charge_id', $intent->get_charge_id() );
				$order->save();
			}

			$order->payment_complete( $transaction_id );

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
	 * Generate markup for account actions
	 */
	public function generate_account_actions_html() {
		$login_url   = wp_nonce_url( add_query_arg( [ 'wcpay-login' => '1' ] ), 'wcpay-login' );
		$connect_url = wp_nonce_url( add_query_arg( [ 'wcpay-connect' => '1' ] ), 'wcpay-connect' );
		$description = sprintf(
			/* translators: 1) dashboard login URL 2) oauth entry point URL */
			__( 'View and update your bank deposit, company, or personal details <a href="%1$s">over at Stripe</a>. (Or connect to a new Stripe account <a href="%2$s">here</a>.)', 'woocommerce-payments' ),
			$login_url,
			$connect_url
		);

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<?php echo esc_html( __( 'Payment Details', 'woocommerce-payments' ) ); ?>
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
			if ( is_wp_error( $oauth_data ) || ! isset( $oauth_data['url'] ) ) {
				$this->add_error( __( 'There was a problem redirecting you to the account connection page. Please try again.', 'woocommerce-payments' ) );
				return;
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
}
