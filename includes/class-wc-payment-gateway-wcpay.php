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
		add_action( 'wc_ajax_create_payment_intention', array( $this, 'create_payment_intention' ) );
	}

	/**
	 * Renders the Credit Card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		// Add JavaScript for the payment form.
		$js_config = array(
			'publishableKey'                 => $this->publishable_key,
			'accountId'                      => $this->get_option( 'stripe_account_id' ),
			'ajaxurl'                        => WC_AJAX::get_endpoint( 'create_payment_intention' ),
			'create_payment_intention_nonce' => wp_create_nonce( 'woocommerce-payments-create-payment-intention' ),
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
		<input id="wc-payment-intention-id" type="hidden" name="wc-payment-intention-id" />
		<?php
	}

	/**
	 * Handle AJAX request to create payment intention.
	 * Once the payment intention is created, respond with
	 * whether or not it requires further user action (i.e. 3DS) and
	 * the payment intention ID.
	 */
	public function create_payment_intention() {
		try {
			$payment_method_id = $this->get_payment_method_id_from_request();
			$amount            = WC()->cart->total;

			if ( $amount > 0 ) {
				$intention = $this->payments_api_client->create_and_confirm_intention(
					round( (float) $amount * 100 ),
					'usd',
					$payment_method_id
				);

				if ( 'requires_action' === $intention->get_status() ) {
					wp_send_json_success(
						array(
							'requires_action' => true,
							'payment_intention_client_secret' => $intention->get_client_secret(),
						)
					);
				} else {
					wp_send_json_success(
						array(
							'requires_action'      => false,
							'payment_intention_id' => $intention->get_id(),
						)
					);
				}
			}
			die();
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'There was a problem with your payment.', 'woocommerce-payments' ),
				)
			);
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
		try {
			$order                = wc_get_order( $order_id );
			$payment_intention_id = $this->get_payment_intention_id_from_request();

			$amount = $order->get_total();

			$transaction_id = '';

			if ( $amount > 0 ) {
				// Retrieve intention.
				$intention = $this->payments_api_client->retrieve_intention( $payment_intention_id );

				if ( 'requires_confirmation' === $intention->get_status() ) {
					$intention = $this->payments_api_client->confirm_intention( $intention );
				}

				if ( 'succeeded' === $intention->get_status() ) {
					$transaction_id = $intention->get_id();

					$note = sprintf(
						/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
						__( 'A payment of %1$s was successfully charged using WooCommerce Payments (Transaction #%2$s)', 'woocommerce-payments' ),
						wc_price( $amount ),
						$transaction_id
					);
						$order->add_order_note( $note );

					$order->payment_complete( $transaction_id );

					wc_reduce_stock_levels( $order_id );
					WC()->cart->empty_cart();

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);
				}
				return;
			}
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
	 * Extract the payment method ID from the request's POST variables
	 *
	 * @return string
	 * @throws Exception - If no payment method ID is found.
	 */
	private function get_payment_method_id_from_request() {
		check_ajax_referer( 'woocommerce-payments-create-payment-intention' );

		if ( ! isset( $_POST['wc_payment_method_id'] ) ) {
			// If no payment method ID is set then stop here with an error.
			throw new Exception( __( 'Payment method ID not found.', 'woocommerce-payments' ) );
		}

		$payment_method_id = wc_clean( $_POST['wc_payment_method_id'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		return $payment_method_id;
	}

	/**
	 * Extract the payment intention ID from the request's POST variables
	 *
	 * @return string
	 * @throws Exception - If no payment intention ID is found.
	 */
	private function get_payment_intention_id_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		if ( ! isset( $_POST['wc-payment-intention-id'] ) ) {
			// If no payment intention ID is set then stop here with an error.
			throw new Exception( __( 'Payment intention ID not found.', 'woocommerce-payments' ) );
		}

		$payment_intention_id = wc_clean( $_POST['wc-payment-intention-id'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification

		return $payment_intention_id;
	}
}
