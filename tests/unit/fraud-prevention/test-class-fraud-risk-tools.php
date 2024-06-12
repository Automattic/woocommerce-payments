<?php
/**
 * Class Fraud_Prevention_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Country_Code;
use WCPay\Fraud_Prevention\Fraud_Risk_Tools;
use WCPay\Fraud_Prevention\Models\Rule;

/**
 * Fraud_Prevention_Service_Test unit tests.
 */
class Fraud_Risk_Tools_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_account;

	/**
	 * Fraud_Risk_Tools mock object.
	 *
	 * @var PHPUnit_Framework_MockObject_MockObject|Fraud_Risk_Tools
	 */
	private $fraud_risk_tools;

	/**
	 * Basic protection level
	 *
	 * @var array
	 */
	private $basic_protection_level = [];

	/**
	 * Standard protection level
	 *
	 * @var array
	 */
	private $standard_protection_level = [
		[
			'key'     => 'international_ip_address',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'ip_country',
				'operator' => 'in',
				'value'    => '',
			],
		],
		[
			'key'     => 'order_items_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'item_count',
				'operator' => 'greater_than',
				'value'    => 10,
			],
		],
		[
			'key'     => 'purchase_price_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'order_total',
				'operator' => 'greater_than',
				'value'    => '100000|usd',
			],
		],
		[
			'key'     => 'ip_address_mismatch',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'ip_billing_country_same',
				'operator' => 'equals',
				'value'    => false,
			],
		],
	];

	/**
	 * Standard protection level with specific selling locations
	 *
	 * @var array
	 */
	private $standard_protection_level_with_specific_selling_locations = [
		[
			'key'     => 'international_ip_address',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'ip_country',
				'operator' => 'not_in',
				'value'    => 'us|ca',
			],
		],
		[
			'key'     => 'order_items_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'item_count',
				'operator' => 'greater_than',
				'value'    => 10,
			],
		],
		[
			'key'     => 'purchase_price_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'order_total',
				'operator' => 'greater_than',
				'value'    => '100000|usd',
			],
		],
		[
			'key'     => 'ip_address_mismatch',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'ip_billing_country_same',
				'operator' => 'equals',
				'value'    => false,
			],
		],
	];

	/**
	 * Standard protection level with all except selling locations
	 *
	 * @var array
	 */
	private $standard_protection_level_with_all_except_selling_locations = [
		[
			'key'     => 'international_ip_address',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'ip_country',
				'operator' => 'in',
				'value'    => 'us|ca',
			],
		],
		[
			'key'     => 'order_items_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'item_count',
				'operator' => 'greater_than',
				'value'    => 10,
			],
		],
		[
			'key'     => 'purchase_price_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'order_total',
				'operator' => 'greater_than',
				'value'    => '100000|usd',
			],
		],
		[
			'key'     => 'ip_address_mismatch',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'ip_billing_country_same',
				'operator' => 'equals',
				'value'    => false,
			],
		],
	];

	/**
	 * High protection level
	 *
	 * @var array
	 */
	private $high_protection_level = [
		[
			'key'     => 'international_ip_address',
			'outcome' => 'block',
			'check'   => [
				'key'      => 'ip_country',
				'operator' => 'in',
				'value'    => '',
			],
		],
		[
			'key'     => 'purchase_price_threshold',
			'outcome' => 'block',
			'check'   => [
				'key'      => 'order_total',
				'operator' => 'greater_than',
				'value'    => '100000|usd',
			],
		],
		[
			'key'     => 'order_items_threshold',
			'outcome' => 'review',
			'check'   => [
				'operator' => 'or',
				'checks'   => [
					[
						'key'      => 'item_count',
						'operator' => 'less_than',
						'value'    => 2,
					],
					[
						'key'      => 'item_count',
						'operator' => 'greater_than',
						'value'    => 10,
					],
				],
			],
		],
		[
			'key'     => 'address_mismatch',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'billing_shipping_address_same',
				'operator' => 'equals',
				'value'    => false,
			],
		],
		[
			'key'     => 'ip_address_mismatch',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'ip_billing_country_same',
				'operator' => 'equals',
				'value'    => false,
			],
		],
	];

	/**
	 * Advanced protection level
	 *
	 * @var array
	 */
	private $advanced_protection_level = [
		[
			'key'     => 'international_ip_address',
			'outcome' => 'block',
			'check'   => [
				'key'      => 'ip_country',
				'operator' => 'in',
				'value'    => '',
			],
		],
		[
			'key'     => 'purchase_price_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'order_total',
				'operator' => 'greater_than',
				'value'    => 100000,
			],
		],
		[
			'key'     => 'order_items_threshold',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'item_count',
				'operator' => 'greater_than',
				'value'    => 100000,
			],
		],
		[
			'key'     => 'address_mismatch',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'billing_shipping_address_same',
				'operator' => 'equals',
				'value'    => true,
			],
		],
		[
			'key'     => 'international_billing_address',
			'outcome' => 'review',
			'check'   => [
				'key'      => 'billing_country',
				'operator' => 'in',
				'value'    => '',
			],
		],
	];

	public function set_up() {
		parent::set_up();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->fraud_risk_tools = new Fraud_Risk_Tools( $this->mock_wcpay_account );
	}

	public function test_registers_action_properly() {
		wp_set_current_user( 1 );
		$this->set_is_admin( true );
		$this->set_current_user_can( true );
		$this->fraud_risk_tools->init_hooks();
		$this->assertNotFalse( has_action( 'admin_menu', [ $this->fraud_risk_tools, 'init_advanced_settings_page' ] ) );
	}

	public function test_it_gets_basic_protection_settings() {
		update_option( 'woocommerce_allowed_countries', 'all' );

		$this->basic_protection_level = $this->fix_outcomes( $this->basic_protection_level );
		$settings                     = $this->fraud_risk_tools->get_basic_protection_settings();

		$this->assertSame( $this->basic_protection_level, $settings );
	}

	public function test_it_gets_standard_protection_settings() {
		update_option( 'woocommerce_allowed_countries', 'all' );

		$this->standard_protection_level = $this->fix_outcomes( $this->standard_protection_level );
		$settings                        = $this->fraud_risk_tools->get_standard_protection_settings();

		$this->assertSame( $this->standard_protection_level, $settings );
	}

	public function test_it_gets_high_protection_settings() {
		update_option( 'woocommerce_allowed_countries', 'all' );

		$this->high_protection_level = $this->fix_outcomes( $this->high_protection_level );
		$settings                    = $this->fraud_risk_tools->get_high_protection_settings();

		$this->assertSame( $this->high_protection_level, $settings );
	}

	public function test_it_gets_high_protection_empty_allowed_countries_settings() {
		update_option( 'woocommerce_allowed_countries', '' );

		$this->high_protection_level = $this->fix_outcomes( $this->high_protection_level );
		$settings                    = $this->fraud_risk_tools->get_high_protection_settings();

		$this->assertSame( $this->high_protection_level, $settings );
	}

	public function test_it_gets_the_correct_for_specific_allowed_selling_locations_type() {
		update_option( 'woocommerce_allowed_countries', 'specific' );
		update_option( 'woocommerce_specific_allowed_countries', [ Country_Code::UNITED_STATES, Country_Code::CANADA ] );

		$this->standard_protection_level_with_specific_selling_locations = $this->fix_outcomes( $this->standard_protection_level_with_specific_selling_locations );
		$settings = $this->fraud_risk_tools->get_standard_protection_settings();

		$this->assertSame( $this->standard_protection_level_with_specific_selling_locations, $settings );
	}

	public function test_it_gets_the_correct_for_all_except_selling_locations_type() {
		update_option( 'woocommerce_allowed_countries', 'all_except' );
		update_option( 'woocommerce_all_except_countries', [ Country_Code::UNITED_STATES, Country_Code::CANADA ] );

		$this->standard_protection_level_with_all_except_selling_locations = $this->fix_outcomes( $this->standard_protection_level_with_all_except_selling_locations );
		$settings = $this->fraud_risk_tools->get_standard_protection_settings();

		$this->assertSame( $this->standard_protection_level_with_all_except_selling_locations, $settings );
	}

	/**
	 * @dataProvider get_matching_protection_level_provider
	 */
	public function test_it_get_matching_protection_level( $ruleset, $expected ) {
		update_option( 'woocommerce_allowed_countries', 'all' );

		$ruleset          = $this->fix_outcomes( $ruleset );
		$protection_level = $this->fraud_risk_tools->get_matching_protection_level( $ruleset );

		$this->assertSame( $expected, $protection_level );
	}

	public function get_matching_protection_level_provider() {
		return [
			'basic'    => [ $this->basic_protection_level, 'basic' ],
			'standard' => [ $this->standard_protection_level, 'standard' ],
			'high'     => [ $this->high_protection_level, 'high' ],
			'advanced' => [ $this->advanced_protection_level, 'advanced' ],
		];
	}

	private function fix_outcomes( $ruleset ) {
		$review_feature_enabled = WC_Payments_Features::is_frt_review_feature_active();
		foreach ( $ruleset as &$rule ) {
			if ( Rule::FRAUD_OUTCOME_REVIEW === $rule['outcome'] && ! $review_feature_enabled ) {
				$rule['outcome'] = Rule::FRAUD_OUTCOME_BLOCK;
			}
		}
		return $ruleset;
	}

	/**
	 * @param bool $is_admin
	 */
	private function set_is_admin( bool $is_admin ) {
		global $current_screen;

		if ( ! $is_admin ) {
			$current_screen = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
			return;
		}

		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_screen = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( $is_admin );
	}

	/**
	 * @param bool $can
	 */
	private function set_current_user_can( bool $can ) {
		global $current_user_can;

		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_user_can = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'current_user_can' ] )
			->getMock();

		$current_user_can->method( 'current_user_can' )->willReturn( $can );
	}
}
