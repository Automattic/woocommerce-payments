<?php
/**
 * Class PlatformCheckoutSaveUser
 *
 * @package WooCommerce\Payments\PlatformCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class that adds a section to save new user for platform checkout on the frontend.
 */
class Platform_Checkout_Save_User {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_review_order_after_payment', [ $this, 'add_checkout_page_save_user_container' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_checkout_page_scripts' ] );
	}

	/** Adds a section in checkout page to save new user for platform checkout. */
	public function add_checkout_page_save_user_container() {
		echo '<div id="checkout-page-save-user-container"></div>';
	}

	/**
	 * Load scripts and styles for checkout page.
	 */
	public function register_checkout_page_scripts() {
		$script_src_url    = plugins_url( 'dist/platform-checkout.js', WCPAY_PLUGIN_FILE );
		$style_url         = plugins_url( 'dist/platform-checkout.css', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/platform-checkout.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_style(
			'WCPAY_PLATFORM_CHECKOUT',
			$style_url,
			[],
			\WC_Payments::get_file_version( 'dist/platform-checkout.css' )
		);

		wp_register_script(
			'WCPAY_PLATFORM_CHECKOUT',
			$script_src_url,
			$script_asset['dependencies'],
			\WC_Payments::get_file_version( 'dist/platform-checkout.js' ),
			true
		);

		wp_enqueue_style( 'WCPAY_PLATFORM_CHECKOUT' );
		wp_enqueue_script( 'WCPAY_PLATFORM_CHECKOUT' );
	}
}
