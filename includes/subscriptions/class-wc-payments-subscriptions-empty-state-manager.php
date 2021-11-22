<?php
/**
 * Class WC_Payments_Subscriptions_Empty_State.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for loading WooCommerce Payments Subscription empty state screen.
 */
class WC_Payments_Subscriptions_Empty_State_Manager {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Subscriptions_Empty_State Constructor
	 *
	 * @param WC_Payments_Account $account Account class instance.
	 */
	public function __construct( WC_Payments_Account $account ) {
		$this->account = $account;

		if ( ! $this->is_subscriptions_plugin_active() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_and_styles' ] );
			add_filter( 'woocommerce_subscriptions_not_found_label', [ $this, 'replace_subscriptions_empty_state' ] );
		}
	}

	/**
	 * Enqueues the WCPay Subscription empty state scripts and styles.
	 */
	public function enqueue_scripts_and_styles() {
		$screen = get_current_screen();

		// Only enqueue the scripts on the admin subscriptions screen.
		if ( ! $screen || 'edit-shop_subscription' !== $screen->id ) {
			return;
		}

		$script_src_url    = plugins_url( 'dist/subscriptions-empty-state.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/subscriptions-empty-state.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];
		$wcpay_settings    = [
			'connectUrl'    => WC_Payments_Account::get_connect_url(),
			'isConnected'   => $this->account->is_stripe_connected(),
			'newProductUrl' => esc_url( admin_url( 'post-new.php?post_type=product' ) ),
		];

		wp_register_script(
			'WCPAY_SUBSCRIPTIONS_EMPTY_STATE',
			$script_src_url,
			$script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/subscriptions-empty-state.js' ),
			true
		);

		wp_localize_script(
			'WCPAY_SUBSCRIPTIONS_EMPTY_STATE',
			'wcpay',
			$wcpay_settings
		);

		wp_register_style(
			'WCPAY_SUBSCRIPTIONS_EMPTY_STATE',
			plugins_url( 'dist/subscriptions-empty-state.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/subscriptions-empty-state.css' )
		);

		wp_enqueue_script( 'WCPAY_SUBSCRIPTIONS_EMPTY_STATE' );
		wp_enqueue_style( 'WCPAY_SUBSCRIPTIONS_EMPTY_STATE' );
	}

	/**
	 * Replaces the default empty subscriptions state HTML with a wrapper for our content to be placed into.
	 *
	 * @return string The empty subscriptions sate wrapper.
	 */
	public function replace_subscriptions_empty_state() {
		return '<div id="wcpay_subscriptions_empty_state"></div>';
	}
}
