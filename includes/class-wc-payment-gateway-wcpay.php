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
		$this->method_description = __( 'Accept payments via a WooCommerce-branded payment gateway', 'woocommerce-payments' );

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
				'default'     => __( 'Credit Card (WooCommerce Payments)', 'woocommerce-payments' ),
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-payments' ),
				'default'     => __( 'Pay with your credit card via WooCommerce Payments.', 'woocommerce-payments' ),
				'desc_tip'    => true,
			),
			'stripe_account_id'    => array(
				'title'       => __( 'Stripe Account ID', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your account ID from your Stripe account.', 'woocommerce-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode'             => array(
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable Test Mode', 'woocommerce-payments' ),
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

		// Add account ID to the payments.
		$this->payments_api_client->set_account_id(
			$this->get_option( 'stripe_account_id' )
		);
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
			'wc-payment-checkout',
			plugins_url( 'assets/js/wc-payment-checkout.js', WCPAY_PLUGIN_FILE ),
			array( 'stripe', 'wc-checkout' ),
			filemtime( WCPAY_ABSPATH . 'assets/js/wc-payment-checkout.js' ),
			true
		);

		wp_localize_script( 'wc-payment-checkout', 'wc_payment_config', $js_config );
		wp_enqueue_script( 'wc-payment-checkout' );

		// Output the form HTML.
		// TODO: Style this up. Formatting, escaping double line breaks etc.
		?>
		<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
		<div id="wc-payment-card-element"></div>
		<div id="wc-payment-errors" role="alert"></div>
		<input id="wc-payment-source" type="hidden" name="wc-payment-source" />
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
				// Get the payment source from the request (generated when the user entered their card details).
				$source = $this->get_source_from_request();

				// Create intention.
				$intent = $this->payments_api_client->create_intention( round( (float) $amount * 100 ), 'usd' );

				// TODO: We could attempt to confirm the intention when creating it instead?
				// Try to confirm the intention & capture the charge (if 3DS is not required).
				$intent = $this->payments_api_client->confirm_intention( $intent, $source );

				// TODO: We're not handling *all* sorts of things here. For example, redirecting to a 3DS auth flow.
				$transaction_id = $intent->get_id();

				$note = sprintf(
					/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
					__( 'A payment of %1$s was successfully charged using WooCommerce Payments (Transaction #%2$s)', 'woocommerce-payments' ),
					wc_price( $amount ),
					$transaction_id
				);
				$order->add_order_note( $note );
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
	 * Extract the payment source from the request's POST variables
	 *
	 * @return string
	 * @throws Exception - If no source is found.
	 */
	private function get_source_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		if ( ! isset( $_POST['wc-payment-source'] ) ) {
			// If no payment source is set then stop here with an error.
			throw new Exception( __( 'Payment source not found.', 'woocommerce-payments' ) );
		}

		$source = wc_clean( $_POST['wc-payment-source'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification

		return $source;
	}
}
