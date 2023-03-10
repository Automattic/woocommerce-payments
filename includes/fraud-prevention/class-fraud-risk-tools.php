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
use WC_Payments_API_Client;
use WC_Payments_Features;
use WCPay\Exceptions\API_Exception;
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
		return [];
	}

	/**
	 * Returns the standard protection rules.
	 *
	 * @return  array
	 */
	public static function get_standard_protection_settings() {
		$rules = [
			// BLOCK The billing address does not match what is on file with the card issuer.
			new Rule( self::RULE_AVS_MISMATCH, Rule::FRAUD_OUTCOME_BLOCK, Check::check( 'avs_check', Check::OPERATOR_EQUALS, false ) ),
			// REVIEW The card's issuing bank cannot verify the CVV.
			new Rule( self::RULE_CVC_VERIFICATION, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'cvc_check', Check::OPERATOR_EQUALS, false ) ),
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
			// BLOCK The billing address does not match what is on file with the card issuer.
			new Rule( self::RULE_AVS_MISMATCH, Rule::FRAUD_OUTCOME_BLOCK, Check::check( 'avs_check', Check::OPERATOR_EQUALS, false ) ),
			// BLOCK An order originates from an IP address outside your country.
			new Rule( self::RULE_INTERNATIONAL_IP_ADDRESS, Rule::FRAUD_OUTCOME_BLOCK, Check::check( 'ip_country_same_with_account_country', Check::OPERATOR_EQUALS, false ) ),
			// BLOCK An order exceeds $1,000.00.
			new Rule( self::RULE_PURCHASE_PRICE_THRESHOLD, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'order_total', Check::OPERATOR_GT, 1000 ) ),
			// BLOCK The same card or IP Address submits 5 orders within 72 hours.
			new Rule( self::RULE_ORDER_VELOCITY, Rule::FRAUD_OUTCOME_BLOCK, Check::check( 'orders_since_72h', Check::OPERATOR_EQUALS, 5 ) ),
			// REVIEW The card's issuing bank cannot verify the CVV.
			new Rule( self::RULE_CVC_VERIFICATION, Rule::FRAUD_OUTCOME_REVIEW, Check::check( 'cvc_check', Check::OPERATOR_EQUALS, false ) ),
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
