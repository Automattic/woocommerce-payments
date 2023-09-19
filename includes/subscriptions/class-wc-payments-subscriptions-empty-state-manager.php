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
 * Class for loading WooPayments Subscription empty state screen.
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
		}
	}

	/**
	 * Enqueues the WCPay Subscription empty state scripts and styles.
	 */
	public function enqueue_scripts_and_styles() {
		$screen = get_current_screen();

		// Only enqueue the scripts on the admin subscriptions screen.
		if ( ! $screen || 'edit-shop_subscription' !== $screen->id || wcs_do_subscriptions_exist() ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( 'WCPAY_SUBSCRIPTIONS_EMPTY_STATE', 'dist/subscriptions-empty-state' );
		$wcpay_settings = [
			'connectUrl'    => WC_Payments_Account::get_connect_url( 'WC_SUBSCRIPTIONS_TABLE' ),
			'isConnected'   => $this->account->is_stripe_connected(),
			'newProductUrl' => WC_Subscriptions_Admin::add_subscription_url(),
		];

		wp_localize_script(
			'WCPAY_SUBSCRIPTIONS_EMPTY_STATE',
			'wcpay',
			$wcpay_settings
		);

		WC_Payments_Utils::enqueue_style(
			'WCPAY_SUBSCRIPTIONS_EMPTY_STATE',
			plugins_url( 'dist/subscriptions-empty-state.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/subscriptions-empty-state.css' ),
			'all'
		);

		wp_enqueue_script( 'WCPAY_SUBSCRIPTIONS_EMPTY_STATE' );
	}

	/**
	 * Replaces the default empty subscriptions state HTML with a wrapper for our content to be placed into.
	 *
	 * @deprecated 6.3.0
	 * @param string $default_empty_state_html The default Subscriptions empty state HTML.
	 * @return string The empty subscriptions sate wrapper.
	 */
	public function replace_subscriptions_empty_state( $default_empty_state_html ) {
		wc_deprecated_function( __FUNCTION__, '6.3.0' );
		if ( wcs_do_subscriptions_exist() ) {
			return $default_empty_state_html;
		}

		return '<div id="wcpay_subscriptions_empty_state"></div>';
	}
}
