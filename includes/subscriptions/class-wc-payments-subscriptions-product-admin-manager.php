<?php
/**
 * Class WC_Payments_Subscriptions_Product_Admin_Manager
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A class to add extra functionality to subscription product administration.
 */
class WC_Payments_Subscriptions_Product_Admin_Manager {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Initialize the class and attach callbacks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_and_styles' ] );
	}

	/**
	 * Enqueues the admin scripts needed on the add/edit product screen.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts_and_styles( $hook_suffix ) {
		global $post;

		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$script_src_url    = plugins_url( 'dist/subscriptions-product-admin.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/subscriptions-product-admin.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			'wcpay-subscriptions-product-admin',
			$script_src_url,
			$script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/subscriptions-product-admin.js' ),
			true
		);

		wp_localize_script(
			'wcpay-subscriptions-product-admin',
			'wcpaySubscriptionsProductAdmin',
			[
				'connectUrl' => WC_Payments_Account::get_connect_url( 'WC_SUBSCRIPTIONS_PUBLISH_PRODUCT' ),
			]
		);

		wp_register_style(
			'wcpay-subscriptions-product-admin',
			plugins_url( 'dist/subscriptions-product-admin.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/subscriptions-product-admin.css' )
		);

		wp_enqueue_script( 'wcpay-subscriptions-product-admin' );
		wp_enqueue_style( 'wcpay-subscriptions-product-admin' );
	}
}
