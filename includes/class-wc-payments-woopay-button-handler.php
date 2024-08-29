<?php
/**
 * Class WC_Payments_WooPay_Button_Handler
 * Adds support for the WooPay express checkout button.
 *
 * Borrowed heavily from the WC_Payments_Payment_Request_Button_Handler class.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// TODO: Not sure which of these are needed yet.
use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\WooPay\WooPay_Session;
use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_Payments_WooPay_Button_Handler class.
 */
class WC_Payments_WooPay_Button_Handler {
	const BUTTON_LOCATIONS = 'platform_checkout_button_locations';

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities
	 */
	private $woopay_utilities;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account                        $account Account information.
	 * @param WC_Payment_Gateway_WCPay                   $gateway WCPay gateway.
	 * @param WooPay_Utilities                           $woopay_utilities WCPay gateway.
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper Express checkout helper.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway, WooPay_Utilities $woopay_utilities, WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper ) {
		$this->account                 = $account;
		$this->gateway                 = $gateway;
		$this->woopay_utilities        = $woopay_utilities;
		$this->express_checkout_helper = $express_checkout_helper;
	}

	/**
	 * Indicates eligibility for WooPay via feature flag.
	 *
	 * @var bool
	 */
	private $is_woopay_eligible;

	/**
	 * Indicates whether WooPay is enabled.
	 *
	 * @var bool
	 */
	private $is_woopay_enabled;

	/**
	 * Indicates whether WooPay express checkout is enabled.
	 *
	 * @var bool
	 */
	private $is_woopay_express_button_enabled;

	/**
	 * Indicates whether WooPay and WooPay express checkout are enabled.
	 *
	 * @return bool
	 */
	public function is_woopay_enabled() {
		return $this->is_woopay_eligible && $this->is_woopay_enabled && $this->is_woopay_express_button_enabled;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		// Checks if WCPay is enabled.
		if ( ! $this->gateway->is_enabled() ) {
			return;
		}

		// Checks if WooPay is enabled.
		$this->is_woopay_eligible               = WC_Payments_Features::is_woopay_eligible(); // Feature flag.
		$this->is_woopay_enabled                = 'yes' === $this->gateway->get_option( 'platform_checkout', 'no' );
		$this->is_woopay_express_button_enabled = WC_Payments_Features::is_woopay_express_checkout_enabled();

		if ( ! $this->is_woopay_enabled() ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		// Create WooPay button location option if it doesn't exist and enable all locations by default.
		if ( ! array_key_exists( self::BUTTON_LOCATIONS, get_option( 'woocommerce_woocommerce_payments_settings' ) ) ) {

			$all_locations = $this->gateway->form_fields[ self::BUTTON_LOCATIONS ]['options'];

			$this->gateway->update_option( self::BUTTON_LOCATIONS, array_keys( $all_locations ) );

			WC_Payments::woopay_tracker()->woopay_locations_updated( $all_locations, array_keys( $all_locations ) );
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_filter( 'wcpay_payment_fields_js_config', [ $this, 'add_woopay_config' ] );

		add_action( 'wp_ajax_woopay_express_checkout_button_show_error_notice', [ $this, 'show_error_notice' ] );
		add_action( 'wp_ajax_nopriv_woopay_express_checkout_button_show_error_notice', [ $this, 'show_error_notice' ] );
	}

	/**
	 * Add the woopay button config to wcpay_config.
	 *
	 * @param array $config The existing config array.
	 *
	 * @return array The modified config array.
	 */
	public function add_woopay_config( $config ) {
		$user = wp_get_current_user();

		$config['woopayButton']           = $this->get_button_settings();
		$config['woopayButtonNonce']      = wp_create_nonce( 'woopay_button_nonce' );
		$config['addToCartNonce']         = wp_create_nonce( 'wcpay-add-to-cart' );
		$config['shouldShowWooPayButton'] = $this->should_show_woopay_button();
		$config['woopaySessionEmail']     = WooPay_Session::get_user_email( $user );

		return $config;
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if we should not show the button.
		if ( ! $this->should_show_woopay_button() ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( 'WCPAY_WOOPAY_EXPRESS_BUTTON', 'dist/woopay-express-button' );
		WC_Payments_Utils::enqueue_style(
			'WCPAY_WOOPAY_EXPRESS_BUTTON',
			plugins_url( 'dist/woopay-express-button.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/woopay-express-button.css' )
		);

		$wcpay_config = rawurlencode( wp_json_encode( WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() ) );

		wp_add_inline_script(
			'WCPAY_WOOPAY_EXPRESS_BUTTON',
			"
			var wcpayConfig = wcpayConfig || JSON.parse( decodeURIComponent( '" . esc_js( $wcpay_config ) . "' ) );
			",
			'before'
		);

		wp_set_script_translations( 'WCPAY_WOOPAY_EXPRESS_BUTTON', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_WOOPAY_EXPRESS_BUTTON' );

		WC_Payments_Utils::enqueue_style(
			'WCPAY_WOOPAY',
			plugins_url( 'dist/woopay.css', WCPAY_PLUGIN_FILE ),
			[],
			WCPAY_VERSION_NUMBER,
			'all'
		);
	}

	/**
	 * Returns the error notice HTML.
	 */
	public function show_error_notice() {
		$is_nonce_valid = check_ajax_referer( 'woopay_button_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

		// $message has already been translated.
		wc_add_notice( $message, 'error' );
		$notice = wc_print_notices( true );

		wp_send_json_success(
			[
				'notice' => $notice,
			]
		);

		wp_die();
	}

	/**
	 * The settings for the `button` attribute - they depend on the "grouped settings" flag value.
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$common_settings        = $this->express_checkout_helper->get_common_button_settings();
		$woopay_button_settings = [
			'size'    => $this->gateway->get_option( 'payment_request_button_size' ),
			'context' => $this->express_checkout_helper->get_button_context(),
		];

		return array_merge( $common_settings, $woopay_button_settings );
	}

	/**
	 * Checks whether Payment Request Button should be available on this page.
	 *
	 * @return bool
	 */
	public function should_show_woopay_button() {
		// WCPay is not available.
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $gateways['woocommerce_payments'] ) ) {
			return false;
		}

		// WooPay is not enabled.
		if ( ! $this->is_woopay_enabled() ) {
			return false;
		}

		// Page not supported.
		if ( ! $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_cart() && ! $this->express_checkout_helper->is_checkout() ) {
			return false;
		}

		// Check if WooPay is available in the user country.
		if ( ! $this->woopay_utilities->is_country_available( $this->gateway ) ) {
			return false;
		}

		// Product page, but not available in settings.
		if ( $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_available_at( 'product', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Checkout page, but not available in settings.
		if ( $this->express_checkout_helper->is_checkout() && ! $this->express_checkout_helper->is_available_at( 'checkout', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Cart page, but not available in settings.
		if ( $this->express_checkout_helper->is_cart() && ! $this->express_checkout_helper->is_available_at( 'cart', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Product page, but has unsupported product type.
		if ( $this->express_checkout_helper->is_product() && ! $this->is_product_supported() ) {
			Logger::log( 'Product page has unsupported product type ( WooPay Express button disabled )' );
			return false;
		}

		// Cart has unsupported product type.
		if ( ( $this->express_checkout_helper->is_checkout() || $this->express_checkout_helper->is_cart() ) && ! $this->has_allowed_items_in_cart() ) {
			Logger::log( 'Items in the cart have unsupported product type ( WooPay Express button disabled )' );
			return false;
		}

		if ( ! is_user_logged_in() ) {
			// On product page for a subscription product, but not logged in, making WooPay unavailable.
			if ( $this->express_checkout_helper->is_product() ) {
				$current_product = wc_get_product();

				if ( $current_product && $this->express_checkout_helper->is_product_subscription( $current_product ) ) {
					return false;
				}
			}

			// On cart or checkout page with a subscription product in cart, but not logged in, making WooPay unavailable.
			if ( ( $this->express_checkout_helper->is_checkout() || $this->express_checkout_helper->is_cart() ) && class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
				// Check cart for subscription products.
				return false;
			}

			// If guest checkout is not allowed, and customer is not logged in, disable the WooPay button.
			if ( ! $this->woopay_utilities->is_guest_checkout_enabled() ) {
				return false;
			}
		}

		/**
		 * TODO: We need to do some research here and see if there are any product types that we
		 * absolutely cannot support with WooPay at this time. There are some examples in the
		 * `WC_Payments_Payment_Request_Button_Handler->is_product_supported()` method.
		 */

		return true;
	}

	/**
	 * Display the payment request button.
	 */
	public function display_woopay_button_html() {
		if ( ! $this->should_show_woopay_button() ) {
			return;
		}

		$settings = $this->get_button_settings();
		$radius   = WC_Payments_Features::is_stripe_ece_enabled() ? $settings['radius'] : WC_Payments_Express_Checkout_Button_Handler::DEFAULT_BORDER_RADIUS_IN_PX;

		?>
		<div id="wcpay-woopay-button" data-product_page=<?php echo esc_attr( $this->express_checkout_helper->is_product() ); ?>>
			<?php // The WooPay express checkout button React component will go here. This is rendered as disabled for now, until the page is initialized. ?>
			<button
				class="woopay-express-button"
				aria-label="<?php esc_attr_e( 'WooPay', 'woocommerce-payments' ); ?>"
				data-type="<?php echo esc_attr( $settings['type'] ); ?>"
				data-theme="<?php echo esc_attr( $settings['theme'] ); ?>"
				data-size="<?php echo esc_attr( $settings['size'] ); ?>"
				style="height: <?php echo esc_attr( $settings['height'] ); ?>px; border-radius: <?php echo esc_attr( $radius ); ?>px"
				disabled
			></button>
		</div>
		<?php
	}

	/**
	 * Whether the product page has a product compatible with the WooPay Express button.
	 *
	 * @return boolean
	 */
	private function is_product_supported() {
		$product      = $this->express_checkout_helper->get_product();
		$is_supported = true;

		if ( ! is_object( $product ) ) {
			$is_supported = false;
		}

		// External/affiliate products are not supported.
		if ( is_a( $product, 'WC_Product' ) && $product->is_type( 'external' ) ) {
			$is_supported = false;
		}

		// Pre Orders products to be charged upon release are not supported.
		if ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
			$is_supported = false;
		}

		// WC Bookings require confirmation products are not supported.
		if ( is_a( $product, 'WC_Product_Booking' ) && $product->get_requires_confirmation() ) {
			$is_supported = false;
		}

		return apply_filters( 'wcpay_woopay_button_is_product_supported', $is_supported, $product );
	}

	/**
	 * Checks the cart to see if the WooPay Express button supports all of its items.
	 *
	 * @todo Abstract this. This is a copy of the same method in the `WC_Payments_Payment_Request_Button_Handler` class.
	 *
	 * @return boolean
	 */
	private function has_allowed_items_in_cart() {
		$is_supported = true;

		/**
		 * Psalm throws an error here even though we check the class existence.
		 *
		 * @psalm-suppress UndefinedClass
		 */
		// We don't support pre-order products to be paid upon release.
		if ( class_exists( 'WC_Pre_Orders_Cart' ) && class_exists( 'WC_Pre_Orders_Product' ) ) {
			if (
				WC_Pre_Orders_Cart::cart_contains_pre_order() &&
				WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() )
			) {
				$is_supported = false;
			}
		}

		return apply_filters( 'wcpay_platform_checkout_button_are_cart_items_supported', $is_supported );
	}
}
