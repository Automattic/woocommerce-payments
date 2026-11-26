<?php
/**
 * Class WC_Payments_Express_Checkout_Button_Display_Handler
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Payments_Express_Checkout_Button_Display_Handler
 */
class WC_Payments_Express_Checkout_Button_Display_Handler {

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Instance of WC_Payments_WooPay_Button_Handler, created in init function
	 *
	 * @var WC_Payments_WooPay_Button_Handler
	 */
	private $platform_checkout_button_handler;

	/**
	 * Instance of WC_Payments_Express_Checkout_Button_Handler, created in init function
	 *
	 * @var WC_Payments_Express_Checkout_Button_Handler
	 */
	private $express_checkout_button_handler;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Ajax_Handler
	 */
	private $express_checkout_ajax_handler;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payment_Gateway_WCPay                    $gateway WCPay gateway.
	 * @param WC_Payments_WooPay_Button_Handler           $platform_checkout_button_handler Platform checkout button handler.
	 * @param WC_Payments_Express_Checkout_Button_Handler $express_checkout_button_handler Express Checkout Element button handler.
	 * @param WC_Payments_Express_Checkout_Ajax_Handler   $express_checkout_ajax_handler Express checkout ajax handlers.
	 * @param WC_Payments_Express_Checkout_Button_Helper  $express_checkout_helper Express checkout button helper.
	 */
	public function __construct(
		WC_Payment_Gateway_WCPay $gateway,
		WC_Payments_WooPay_Button_Handler $platform_checkout_button_handler,
		WC_Payments_Express_Checkout_Button_Handler $express_checkout_button_handler,
		WC_Payments_Express_Checkout_Ajax_Handler $express_checkout_ajax_handler,
		WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper
	) {
		$this->gateway                          = $gateway;
		$this->platform_checkout_button_handler = $platform_checkout_button_handler;
		$this->express_checkout_button_handler  = $express_checkout_button_handler;
		$this->express_checkout_ajax_handler    = $express_checkout_ajax_handler;
		$this->express_checkout_helper          = $express_checkout_helper;
	}

	/**
	 * Initializes this class, its dependencies, and its hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->platform_checkout_button_handler->init();
		$this->express_checkout_button_handler->init();

		$is_woopay_enabled          = WC_Payments_Features::is_woopay_enabled();
		$is_payment_request_enabled = $this->gateway->is_payment_request_enabled();
		$is_amazon_pay_enabled      = $this->express_checkout_helper->can_use_amazon_pay();

		if ( $is_woopay_enabled ) {
			add_action( 'wc_ajax_wcpay_add_to_cart', [ $this->express_checkout_ajax_handler, 'ajax_add_to_cart' ] );
		}

		if ( $is_woopay_enabled || $is_payment_request_enabled || $is_amazon_pay_enabled ) {
			add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_express_checkout_buttons' ], 1 );
			add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_express_checkout_buttons' ], 21 );
			add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_express_checkout_buttons' ], 1 );
			add_action( 'woocommerce_pay_order_before_payment', [ $this, 'display_express_checkout_buttons' ], 1 );
		}

		add_filter( 'wcpay_tracks_event_properties', [ $this, 'record_all_ece_tracks_events' ], 10, 2 );

		if ( $this->is_pay_for_order_flow_supported() ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'add_pay_for_order_params_to_js_config' ], 5 );
		}
	}

	/**
	 * Display express checkout separator only when express buttons are displayed.
	 *
	 * @param bool $separator_starts_hidden Whether the separator should start hidden.
	 * @return void
	 */
	public function display_express_checkout_separator_if_necessary( $separator_starts_hidden = false ) {
		if ( $this->express_checkout_helper->is_checkout() ) {
			?>
			<p id="wcpay-express-checkout-button-separator" style="margin-top:1.5em;text-align:center;<?php echo $separator_starts_hidden ? 'display:none;' : ''; ?>">&mdash; <?php esc_html_e( 'OR', 'woocommerce-payments' ); ?> &mdash;</p>
			<?php
		}
	}

	/**
	 * Display express checkout separator only when express buttons are displayed.
	 *
	 * @return void
	 */
	public function display_express_checkout_buttons() {
		$should_show_woopay                  = $this->platform_checkout_button_handler->should_show_woopay_button();
		$should_show_express_checkout_button = $this->express_checkout_helper->should_show_express_checkout_button();

		// When Express Checkout button is enabled, we need the separator markup on the page, but hidden in case the browser doesn't have any express payment methods to display.
		// More details: https://github.com/Automattic/woocommerce-payments/pull/5399#discussion_r1073633776.
		$separator_starts_hidden = ! $should_show_woopay;
		if ( $should_show_woopay || $should_show_express_checkout_button ) {
			?>
			<div class='wcpay-express-checkout-wrapper' >
			<?php
			if ( ! $this->express_checkout_helper->is_pay_for_order_page() || $this->is_pay_for_order_flow_supported() ) {
				$this->platform_checkout_button_handler->display_woopay_button_html();
			}

			$this->express_checkout_button_handler->display_express_checkout_button_html();

			if ( is_cart() ) {
				add_action( 'woocommerce_after_cart', [ $this, 'add_order_attribution_inputs' ], 1 );
			} else {
				$this->add_order_attribution_inputs();
			}
			$this->display_express_checkout_separator_if_necessary( $separator_starts_hidden );
			?>
			</div >
			<?php
		}
	}

	/**
	 * Add order attribution inputs to the page.
	 *
	 * @return void
	 */
	public function add_order_attribution_inputs() {
		echo '<wc-order-attribution-inputs id="wcpay-express-checkout__order-attribution-inputs"></wc-order-attribution-inputs>';
	}

	/**
	 * Check if the pay-for-order flow is supported.
	 *
	 * @return bool
	 */
	private function is_pay_for_order_flow_supported() {
		return ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), '11.1.0', '>=' ) );
	}

	/**
	 * Check if WooPay is enabled.
	 *
	 * @return bool
	 */
	public function is_woopay_enabled() {
		return $this->platform_checkout_button_handler->is_woopay_enabled();
	}

	/**
	 * Add the Pay for order params to the JS config.
	 */
	public function add_pay_for_order_params_to_js_config() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		// This runs on wp_enqueue_scripts, before WooCommerce checks the key on the_content, and
		// the config is printed in the page head. Without the key, the visitor gets no order data.
		$order = WC_Payments_Utils::get_authorized_order_from_payment_link();

		if ( null === $order ) {
			return;
		}

		if ( $this->may_see_order_billing_email( $order ) ) {
			$billing_email = $order->get_billing_email();
		} else {
			$session       = wc()->session;
			$session_email = '';

			if ( is_a( $session, WC_Session::class ) ) {
				$customer      = $session->get( 'customer' );
				$session_email = is_array( $customer ) && isset( $customer['email'] ) ? $customer['email'] : '';
			}

			$billing_email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( filter_input( INPUT_POST, 'email', FILTER_SANITIZE_EMAIL ) ) ) : $session_email;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$pay_for_order = sanitize_text_field( wp_unslash( $_GET['pay_for_order'] ) );
		// phpcs:enable WordPress.Security.NonceVerification

		add_filter(
			'wcpay_payment_fields_js_config',
			function ( $js_config ) use ( $order, $pay_for_order, $billing_email ) {
				$js_config['order_id']      = $order->get_id();
				$js_config['pay_for_order'] = $pay_for_order;
				// The link's key matched this exactly.
				$js_config['key']           = $order->get_order_key();
				$js_config['billing_email'] = $billing_email;

				return $js_config;
			}
		);
	}

	/**
	 * Whether the pay page may give the visitor the order's billing email.
	 *
	 * The express checkout and WooPay buttons load the order from the Store API, which wants a
	 * guest order's billing email on every request. See WC_Payments_Utils::may_see_order_billing_details().
	 * This prints in the page head, before WooCommerce checks the visitor, so a check that can't run says no.
	 *
	 * @param WC_Order $order The order, already authorized by its key.
	 *
	 * @return bool
	 */
	private function may_see_order_billing_email( WC_Order $order ): bool {
		return WC_Payments_Utils::may_see_order_billing_details( $order );
	}

	/**
	 * Record all ECE tracks events by adding the track_on_all_stores flag to the event.
	 *
	 * @param array  $properties Event properties.
	 * @param string $event_name Event name.
	 * @return array
	 */
	public function record_all_ece_tracks_events( $properties, $event_name ) {
		$tracked_events_prefixes = [
			'wcpay_applepay',
			'wcpay_gpay',
		];

		foreach ( $tracked_events_prefixes as $prefix ) {
			if ( strpos( $event_name, $prefix ) === 0 ) {
				$properties['record_event_data']['track_on_all_stores'] = true;
				break;
			}
		}

		return $properties;
	}
}
