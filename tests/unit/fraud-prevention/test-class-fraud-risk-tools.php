<?php
/**
 * Class Fraud_Prevention_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Fraud_Prevention\Fraud_Risk_Tools;

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
				'value'    => 'US|CA',
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
				'value'    => 'US|CA',
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

	public function test_it_gets_basic_protection_settings() {
		update_option( 'woocommerce_allowed_countries', 'all' );

		$settings = $this->fraud_risk_tools->get_basic_protection_settings();

		$this->assertSame( $this->basic_protection_level, $settings );
	}

	public function test_it_gets_standard_protection_settings() {
		update_option( 'woocommerce_allowed_countries', 'all' );

		$settings = $this->fraud_risk_tools->get_standard_protection_settings();

		$this->assertSame( $this->standard_protection_level, $settings );
	}

	public function test_it_gets_high_protection_settings() {
		update_option( 'woocommerce_allowed_countries', 'all' );

		$settings = $this->fraud_risk_tools->get_high_protection_settings();

		$this->assertSame( $this->high_protection_level, $settings );
	}

	public function test_it_gets_high_protection_empty_allowed_countries_settings() {
		update_option( 'woocommerce_allowed_countries', '' );

		$settings = $this->fraud_risk_tools->get_high_protection_settings();

		$this->assertSame( $this->high_protection_level, $settings );
	}

	public function test_it_gets_the_correct_for_specific_allowed_selling_locations_type() {
		update_option( 'woocommerce_allowed_countries', 'specific' );
		update_option( 'woocommerce_specific_allowed_countries', [ 'US', 'CA' ] );

		$settings = $this->fraud_risk_tools->get_standard_protection_settings();

		$this->assertSame( $this->standard_protection_level_with_specific_selling_locations, $settings );
	}

	public function test_it_gets_the_correct_for_all_except_selling_locations_type() {
		update_option( 'woocommerce_allowed_countries', 'all_except' );
		update_option( 'woocommerce_all_except_countries', [ 'US', 'CA' ] );

		$settings = $this->fraud_risk_tools->get_standard_protection_settings();

		$this->assertSame( $this->standard_protection_level_with_all_except_selling_locations, $settings );
	}

	/**
	 * @dataProvider get_matching_protection_level_provider
	 */
	public function test_it_get_matching_protection_level( $ruleset, $expected ) {
		update_option( 'woocommerce_allowed_countries', 'all' );

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
}
