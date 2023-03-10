<?php
/**
 * Class Fraud_Risk_Tools
 *
 * @package WooCommerce\Payments\FraudRiskTools
 */

namespace WCPay\Fraud_Prevention;

require_once dirname( __FILE__ ) . '/models/class-check.php';
require_once dirname( __FILE__ ) . '/models/class-rule.php';

require_once dirname( __FILE__ ) . '/rules/class-base-rule.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-address-mismatch.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-avs-mismatch.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-cvc-verification.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-international-billing-address.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-international-ip-address.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-order-items-threshold.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-order-velocity.php';
require_once dirname( __FILE__ ) . '/rules/class-rule-purchase-price-threshold.php';
require_once dirname( __FILE__ ) . '/class-fraud-rule-adapter.php';

use WC_Payments;
use WC_Payments_API_Client;
use WC_Payments_Features;
use WCPay\Exceptions\API_Exception;
use WCPay\Fraud_Prevention\Models\Fraud_Rule_Adapter;

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
	 * Payments API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $api_client;

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
			self::$instance = new self( WC_Payments::get_payments_api_client() );
		}
		return self::$instance;
	}

	// Rule names.
	const RULE_AVS_MISMATCH                  = 'avs_mismatch';
	const RULE_CVC_VERIFICATION              = 'cvc_verification';
	const RULE_ADDRESS_MISMATCH              = 'address_mismatch';
	const RULE_INTERNATIONAL_IP_ADDRESS      = 'international_ip_address';
	const RULE_INTERNATIONAL_BILLING_ADDRESS = 'international_billing_address';
	const RULE_ORDER_VELOCITY                = 'order_velocity';
	const RULE_ORDER_ITEMS_THRESHOLD         = 'order_items_threshold';
	const RULE_PURCHASE_PRICE_THRESHOLD      = 'purchase_price_threshold';

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_API_Client $api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;

		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			add_action( 'admin_menu', [ $this, 'init_advanced_settings_page' ] );
			add_action( 'admin_head', [ $this, 'maybe_pull_server_settings' ] );
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
	 * Fetches the server side settings and overwrites the current one if needed. Caches the settings for 1 day.
	 *
	 * @return  void
	 */
	public function maybe_pull_server_settings() {
		if ( isset( $_GET['path'] ) && '/payments/fraud-protection' === $_GET['path'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$cached_server_settings = get_transient( 'wcpay_fraud_protection_settings' );
			if ( ! $cached_server_settings ) {
				try {
					$latest_server_ruleset = $this->api_client->get_latest_fraud_ruleset();
					if ( isset( $latest_server_ruleset['ruleset_config'] ) ) {
						$ui_config = Fraud_Rule_Adapter::to_ui_settings( $latest_server_ruleset['ruleset_config'] );
						set_transient( 'wcpay_fraud_protection_settings', $ui_config, 1 * DAY_IN_SECONDS );
					}
				} catch ( API_Exception $ex ) {
					return;
				}
			}
		}
	}

	/**
	 * Returns the default protection settings.
	 *
	 * @return  array
	 */
	public static function get_default_protection_settings() {
		return [
			self::RULE_AVS_MISMATCH                  => [
				'enabled' => false,
				'block'   => false,
			],
			self::RULE_CVC_VERIFICATION              => [
				'enabled' => false,
				'block'   => false,
			],
			self::RULE_ADDRESS_MISMATCH              => [
				'enabled' => false,
				'block'   => false,
			],
			self::RULE_INTERNATIONAL_IP_ADDRESS      => [
				'enabled' => false,
				'block'   => false,
			],
			self::RULE_INTERNATIONAL_BILLING_ADDRESS => [
				'enabled' => false,
				'block'   => false,
			],
			self::RULE_ORDER_VELOCITY                => [
				'enabled'    => false,
				'block'      => false,
				'max_orders' => 0,
				'interval'   => 12,
			],
			self::RULE_ORDER_ITEMS_THRESHOLD         => [
				'enabled'   => false,
				'block'     => false,
				'min_items' => 0,
				'max_items' => 0,
			],
			self::RULE_PURCHASE_PRICE_THRESHOLD      => [
				'enabled'    => false,
				'block'      => false,
				'min_amount' => 0,
				'max_amount' => 0,
			],
		];
	}

	/**
	 * Returns the default protection settings.
	 *
	 * @return  array
	 */
	public static function get_standard_protection_settings() {
		$base_settings = self::get_default_protection_settings();
		// BLOCK The billing address does not match what is on file with the card issuer.
		$base_settings[ self::RULE_AVS_MISMATCH ]['enabled'] = true;
		$base_settings[ self::RULE_AVS_MISMATCH ]['block']   = true;
		// REVIEW The card's issuing bank cannot verify the CVV.
		$base_settings[ self::RULE_CVC_VERIFICATION ]['enabled'] = true;
		// REVIEW An order originates from an IP address outside your country.
		$base_settings[ self::RULE_INTERNATIONAL_IP_ADDRESS ]['enabled'] = true;
		// REVIEW An order exceeds $1,000.00 or 10 items.
		$base_settings[ self::RULE_ORDER_ITEMS_THRESHOLD ]['enabled']       = true;
		$base_settings[ self::RULE_ORDER_ITEMS_THRESHOLD ]['max_items']     = 10;
		$base_settings[ self::RULE_PURCHASE_PRICE_THRESHOLD ]['enabled']    = true;
		$base_settings[ self::RULE_PURCHASE_PRICE_THRESHOLD ]['max_amount'] = 1000;
		// REVIEW The same card or IP address submits 5 orders within 72 hours.
		$base_settings[ self::RULE_ORDER_VELOCITY ]['enabled']    = true;
		$base_settings[ self::RULE_ORDER_VELOCITY ]['max_orders'] = 5;
		$base_settings[ self::RULE_ORDER_VELOCITY ]['interval']   = 72;
		return $base_settings;
	}

	/**
	 * Returns the default protection settings.
	 *
	 * @return  array
	 */
	public static function get_high_protection_settings() {
		$base_settings = self::get_default_protection_settings();
		// BLOCK The billing address does not match what is on file with the card issuer.
		$base_settings[ self::RULE_AVS_MISMATCH ]['enabled'] = true;
		$base_settings[ self::RULE_AVS_MISMATCH ]['block']   = true;
		// BLOCK An order originates from an IP address outside your country.
		$base_settings[ self::RULE_INTERNATIONAL_IP_ADDRESS ]['enabled'] = true;
		$base_settings[ self::RULE_INTERNATIONAL_IP_ADDRESS ]['block']   = true;
		// BLOCK An order exceeds $1,000.00.
		$base_settings[ self::RULE_PURCHASE_PRICE_THRESHOLD ]['enabled']    = true;
		$base_settings[ self::RULE_PURCHASE_PRICE_THRESHOLD ]['block']      = true;
		$base_settings[ self::RULE_PURCHASE_PRICE_THRESHOLD ]['max_amount'] = 1000;
		// BLOCK The same card or IP Address submits 5 orders within 72 hours.
		$base_settings[ self::RULE_ORDER_VELOCITY ]['enabled']    = true;
		$base_settings[ self::RULE_ORDER_VELOCITY ]['block']      = true;
		$base_settings[ self::RULE_ORDER_VELOCITY ]['max_orders'] = 5;
		$base_settings[ self::RULE_ORDER_VELOCITY ]['interval']   = 72;
		// REVIEW The card's issuing bank cannot verify the CVV.
		$base_settings[ self::RULE_CVC_VERIFICATION ]['enabled'] = true;
		// REVIEW An order has less than 2 items or more than 10 items.
		$base_settings[ self::RULE_ORDER_ITEMS_THRESHOLD ]['enabled']   = true;
		$base_settings[ self::RULE_ORDER_ITEMS_THRESHOLD ]['min_items'] = 2;
		$base_settings[ self::RULE_ORDER_ITEMS_THRESHOLD ]['max_items'] = 10;
		// REVIEW The shipping and billing address don't match.
		$base_settings[ self::RULE_ADDRESS_MISMATCH ]['enabled'] = true;
		// REVIEW An order is shipping or billing to a non-domestic address.
		$base_settings[ self::RULE_INTERNATIONAL_BILLING_ADDRESS ]['enabled'] = true;

		return $base_settings;
	}
}
