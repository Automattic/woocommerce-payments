<?php
/**
 * Class Fraud_Risk_Tools
 *
 * @package WooCommerce\Payments\FraudRiskTools
 */

namespace WCPay\Fraud_Prevention;

require_once dirname( __FILE__ ) . '/models/class-check.php';
require_once dirname( __FILE__ ) . '/models/class-rule.php';

use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Features;
use WCPay\Fraud_Prevention\Models\Check;
use WCPay\Fraud_Prevention\Models\Rule;

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
	 * Instance of WC_Payments_Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $payments_account;

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
			self::$instance = new self( WC_Payments::get_account_service() );
		}
		return self::$instance;
	}

	// Rule names.
	const RULE_ADDRESS_MISMATCH              = 'address_mismatch';
	const RULE_INTERNATIONAL_IP_ADDRESS      = 'international_ip_address';
	const RULE_INTERNATIONAL_BILLING_ADDRESS = 'international_billing_address';
	const RULE_ORDER_VELOCITY                = 'order_velocity';
	const RULE_ORDER_ITEMS_THRESHOLD         = 'order_items_threshold';
	const RULE_PURCHASE_PRICE_THRESHOLD      = 'purchase_price_threshold';

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_Account $payments_account WC_Payments_Account instance.
	 */
	public function __construct( WC_Payments_Account $payments_account ) {
		$this->payments_account = $payments_account;
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

		if ( ! $this->payments_account->is_stripe_connected() ) {
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
	 * Returns the basic protection rules.
	 *
	 * @return  array
	 */
	public static function get_basic_protection_settings() {
		$rules = [];

		return self::get_ruleset_array( $rules );
	}

	/**
	 * Returns the standard protection rules.
	 *
	 * @return  array
	 */
	public static function get_standard_protection_settings() {
		$rules = [
			// REVIEW An order originates from an IP address outside your country.
			new Rule( self::RULE_INTERNATIONAL_IP_ADDRESS, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'ip_country_same_with_account_country', Check::OPERATOR_EQUALS, false ) ),
			// REVIEW An order exceeds $1,000.00 or 10 items.
			new Rule( self::RULE_ORDER_ITEMS_THRESHOLD, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'item_count', Check::OPERATOR_LT, 10 ) ),
			new Rule( self::RULE_PURCHASE_PRICE_THRESHOLD, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'order_total', Check::OPERATOR_GT, 1000 ) ),
			// REVIEW The same card or IP address submits 5 orders within 72 hours.
			new Rule( self::RULE_ORDER_VELOCITY, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'orders_since_72h', Check::OPERATOR_EQUALS, 5 ) ),
		];

		return self::get_ruleset_array( $rules );
	}

	/**
	 * Returns the default protection settings.
	 *
	 * @return  array
	 */
	public static function get_high_protection_settings() {
		$rules = [
			// BLOCK An order originates from an IP address outside your country.
			new Rule( self::RULE_INTERNATIONAL_IP_ADDRESS, Rule::FRAUD_OUTCOME_BLOCK, Check::check( 'ip_country_same_with_account_country', Check::OPERATOR_EQUALS, false ) ),
			// BLOCK An order exceeds $1,000.00.
			new Rule( self::RULE_PURCHASE_PRICE_THRESHOLD, Rule::FRAUD_OUTCOME_BLOCK, Check::check( 'order_total', Check::OPERATOR_GT, 1000 ) ),
			// BLOCK The same card or IP Address submits 5 orders within 72 hours.
			new Rule( self::RULE_ORDER_VELOCITY, Rule::FRAUD_OUTCOME_BLOCK, Check::check( 'orders_since_72h', Check::OPERATOR_EQUALS, 5 ) ),
			// REVIEW An order has less than 2 items or more than 10 items.
			new Rule( self::RULE_ORDER_ITEMS_THRESHOLD, Rule::FRAUD_OUTCOME_REVIEW, Check::list( Check::LIST_OPERATOR_OR, [ Check::check( 'item_count', Check::OPERATOR_LT, 2 ), Check::check( 'item_count', Check::OPERATOR_GT, 10 ) ] ) ),
			// REVIEW The shipping and billing address don't match.
			new Rule( self::RULE_ADDRESS_MISMATCH, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'billing_shipping_address_same', Check::OPERATOR_EQUALS, false ) ),
			// REVIEW An order is shipping or billing to a non-domestic address.
			new Rule( self::RULE_INTERNATIONAL_BILLING_ADDRESS, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'billing_country_same_with_account_country', Check::OPERATOR_EQUALS, false ) ),
		];

		return self::get_ruleset_array( $rules );
	}

	/**
	 * Returns the array representation of ruleset.
	 *
	 * @param array $array The array of Rule objects.
	 *
	 * @return  array
	 */
	private static function get_ruleset_array( $array ) {
		return array_map(
			function ( Rule $rule ) {
				return $rule->to_array();
			},
			$array
		);
	}
}
