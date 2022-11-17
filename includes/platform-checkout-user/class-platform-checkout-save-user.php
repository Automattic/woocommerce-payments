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
		add_action( 'wp_enqueue_scripts', [ $this, 'register_checkout_page_scripts' ] );
		add_filter( 'wcpay_metadata_from_order', [ $this, 'maybe_add_userdata_to_metadata' ], 10, 2 );
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
			[ 'wp-components' ],
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

	/**
	 * Checks if the user chose to save their data for platform checkout and adds appropriate metadata.
	 *
	 * @param array     $metadata Metadata to be saved.
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function maybe_add_userdata_to_metadata( $metadata, $order ) {
		// If we should be creating a platform checkout customer, add the necessary customer data to the metadata.
		$should_create_platform_customer = isset( $_POST['save_user_in_platform_checkout'] ) && filter_var( wp_unslash( $_POST['save_user_in_platform_checkout'] ), FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $should_create_platform_customer && ! empty( $_POST['platform_checkout_user_phone_field']['full'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$platform_checkout_phone = wc_clean( wp_unslash( $_POST['platform_checkout_user_phone_field']['full'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

				// Add the metadata.
				$metadata['platform_checkout_primary_first_name']   = wc_clean( $order->get_billing_first_name() );
				$metadata['platform_checkout_primary_last_name']    = wc_clean( $order->get_billing_last_name() );
				$metadata['platform_checkout_primary_phone']        = wc_clean( $order->get_billing_phone() );
				$metadata['platform_checkout_primary_company']      = wc_clean( $order->get_billing_company() );
				$metadata['platform_checkout_secondary_first_name'] = wc_clean( $order->get_shipping_first_name() );
				$metadata['platform_checkout_secondary_last_name']  = wc_clean( $order->get_shipping_last_name() );
				$metadata['platform_checkout_secondary_phone']      = wc_clean( $order->get_shipping_phone() );
				$metadata['platform_checkout_secondary_company']    = wc_clean( $order->get_shipping_company() );
				$metadata['platform_checkout_phone']                = $platform_checkout_phone;
		}

		return $metadata;
	}
}
