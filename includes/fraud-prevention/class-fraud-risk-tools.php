<?php
/**
 * Class Fraud_Risk_Tools
 *
 * @package WooCommerce\Payments\FraudRiskTools
 */

namespace WCPay\Fraud_Prevention;

use WC_Payments_Features;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Fraud and Risk tools functionality.
 */
class Fraud_Risk_Tools {
	/**
	 * The single instance of the class.
	 *
	 * @var ?Fraud_Risk_Tools
	 */
	protected static $instance = null;

	/**
	 * Main FraudRiskTools Instance.
	 *
	 * Ensures only one instance of FraudRiskTools is loaded or can be loaded.
	 *
	 * @static
	 * @return Fraud_Risk_Tools - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			add_action( 'admin_menu', [ $this, 'init_advanced_settings_page' ] );
		}
	}

	/**
	 * Initialize the Fraud & Risk Tools Advanced Settings Page.
	 *
	 * @return void
	 */
	public function init_advanced_settings_page() {
		// Settings page generation on the incoming CLI and async job calls.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'WPCOM_JOBS' ) && WPCOM_JOBS ) ) {
			return;
		}

		// Skip registering the page if the fraud and risk tools feature is not enabled.
		if ( ! WC_Payments_Features::is_fraud_protection_settings_enabled() ) {
			return;
		}

		if ( ! function_exists( 'wc_admin_register_page' ) ) {
			return;
		}

		wc_admin_register_page(
			[
				'id'       => 'wc-payments-fraud-protection',
				'title'    => __( 'Fraud protection', 'woocommerce-payments' ),
				'parent'   => 'wc-payments',
				'path'     => '/payments/fraud-protection',
				'nav_args' => [
					'parent' => 'wc-payments',
					'order'  => 50,
				],
			]
		);
		remove_submenu_page( 'wc-admin&path=/payments/overview', 'wc-admin&path=/payments/fraud-protection' );
	}

	/**
	 * Returns the default protection settings.
	 *
	 * @return  array
	 */
	public static function get_default_protection_settings() {
		return [
			'avs_mismatch'                  => [
				'enabled' => false,
				'block'   => false,
			],
			'cvc_verification'              => [
				'enabled' => false,
				'block'   => false,
			],
			'address_mismatch'              => [
				'enabled' => false,
				'block'   => false,
			],
			'international_ip_address'      => [
				'enabled' => false,
				'block'   => false,
			],
			'international_billing_address' => [
				'enabled' => false,
				'block'   => false,
			],
			'order_velocity'                => [
				'enabled'    => false,
				'block'      => false,
				'max_orders' => 0,
				'interval'   => 12,
			],
			'order_items_threshold'         => [
				'enabled'   => false,
				'block'     => false,
				'min_items' => 0,
				'max_items' => 0,
			],
			'purchase_price_threshold'      => [
				'enabled'    => false,
				'block'      => false,
				'min_amount' => 0,
				'max_amount' => 0,
			],
		];
	}
}
