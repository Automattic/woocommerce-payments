<?php
/**
 * Class Fraud_Risk_Tools
 *
 * @package WooCommerce\Payments\Fraud_Risk_Tools
 */

namespace WCPay\Fraud_Prevention;

require_once dirname( __FILE__ ) . '/models/class-check.php';
require_once dirname( __FILE__ ) . '/models/class-rule.php';

use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Features;
use WC_Payments_API_Client;
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
	 * Main Fraud_Risk_Tools Instance.
	 *
	 * Ensures only one instance of Fraud_Risk_Tools is loaded or can be loaded.
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

		// Adds the required parameter on server.
		if ( WC_Payments_Features::is_fraud_protection_settings_enabled() ) {
			add_filter(
				'wcpay_api_request_params',
				function( $params, $api, $method ) {
					if ( false !== strpos( $api, WC_Payments_API_Client::INTENTIONS_API ) && WC_Payments_API_Client::POST === $method ) {
						$params['fraud_settings_enabled'] = 'true';
					}

					return $params;
				},
				10,
				3
			);
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
			new Rule(
				self::RULE_INTERNATIONAL_IP_ADDRESS,
				Rule::FRAUD_OUTCOME_REVIEW,
				Check::check(
					'ip_country',
					self::get_selling_locations_type_operator(),
					self::get_selling_locations_string()
				)
			),
			// REVIEW An order exceeds $1,000.00 or 10 items.
			new Rule(
				self::RULE_ORDER_ITEMS_THRESHOLD,
				Rule::FRAUD_OUTCOME_REVIEW,
				Check::check(
					'item_count',
					Check::OPERATOR_GT,
					10
				)
			),
			new Rule(
				self::RULE_PURCHASE_PRICE_THRESHOLD,
				Rule::FRAUD_OUTCOME_REVIEW,
				Check::check(
					'order_total',
					Check::OPERATOR_GT,
					implode( '|', [ 1000 * 100, 'usd' ] )
				)
			),
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
			new Rule(
				self::RULE_INTERNATIONAL_IP_ADDRESS,
				Rule::FRAUD_OUTCOME_BLOCK,
				Check::check(
					'ip_country',
					self::get_selling_locations_type_operator(),
					self::get_selling_locations_string()
				)
			),
			// BLOCK An order exceeds $1,000.00.
			new Rule(
				self::RULE_PURCHASE_PRICE_THRESHOLD,
				Rule::FRAUD_OUTCOME_BLOCK,
				Check::check(
					'order_total',
					Check::OPERATOR_GT,
					implode( '|', [ 1000 * 100, 'usd' ] )
				)
			),
			// REVIEW An order has less than 2 items or more than 10 items.
			new Rule(
				self::RULE_ORDER_ITEMS_THRESHOLD,
				Rule::FRAUD_OUTCOME_REVIEW,
				Check::list(
					Check::LIST_OPERATOR_OR,
					[
						Check::check( 'item_count', Check::OPERATOR_LT, 2 ),
						Check::check( 'item_count', Check::OPERATOR_GT, 10 ),
					]
				)
			),
			// REVIEW The shipping and billing address don't match.
			new Rule(
				self::RULE_ADDRESS_MISMATCH,
				Rule::FRAUD_OUTCOME_REVIEW,
				Check::check(
					'billing_shipping_address_same',
					Check::OPERATOR_EQUALS,
					false
				)
			),
			// REVIEW An order is shipping or billing to a non-domestic address.
			new Rule(
				self::RULE_INTERNATIONAL_BILLING_ADDRESS,
				Rule::FRAUD_OUTCOME_REVIEW,
				Check::check(
					'billing_country',
					self::get_selling_locations_type_operator(),
					self::get_selling_locations_string()
				)
			),
		];

		return self::get_ruleset_array( $rules );
	}

	/**
	 * Returns the matching predef for a given ruleset array, if nothing matches, returns "advanced".
	 *
	 * @param   array $fraud_ruleset  The ruleset config to match to.
	 *
	 * @return  string  The matching protection level.
	 */
	public static function get_matching_protection_level( $fraud_ruleset ) {
		// Check if the ruleset contains the basic protection config.
		$target_ruleset = self::get_basic_protection_settings();
		if ( $target_ruleset === $fraud_ruleset ) {
			return 'basic';
		}

		// Check if the ruleset contains the standard protection config.
		$target_ruleset = self::get_standard_protection_settings();
		if ( $target_ruleset === $fraud_ruleset ) {
			return 'standard';
		}

		// Check if the ruleset contains the high protection config.
		$target_ruleset = self::get_high_protection_settings();
		if ( $target_ruleset === $fraud_ruleset ) {
			return 'high';
		}

		// The ruleset contains custom configuration.
		return 'advanced';
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

	/**
	 * Returns the check operator for international checks according to the WC Core selling locations setting.
	 *
	 * @return  string  The related operator.
	 */
	private static function get_selling_locations_type_operator() {
		$selling_locations_type = get_option( 'woocommerce_allowed_countries', 'all' );
		if ( 'specific' === $selling_locations_type ) {
				return Check::OPERATOR_NOT_IN;
		}
		return Check::OPERATOR_IN;
	}

	/**
	 * Returns the countries to sell to, or not, as a | delimited string array.
	 *
	 * @return  string  The array imploded with | character.
	 */
	private static function get_selling_locations_string() {
		$selling_locations_type = get_option( 'woocommerce_allowed_countries', 'all' );
		switch ( $selling_locations_type ) {
			case 'specific':
				return implode( '|', get_option( 'woocommerce_specific_allowed_countries', [] ) );
			case 'all_except':
				return implode( '|', get_option( 'woocommerce_all_except_countries', [] ) );
			case 'all':
				return '';
			default:
				return '';
		}
	}
}
