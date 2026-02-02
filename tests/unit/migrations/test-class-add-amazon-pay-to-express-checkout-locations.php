<?php
/**
 * Class Add_Amazon_Pay_To_Express_Checkout_Locations_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Add_Amazon_Pay_To_Express_Checkout_Locations;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Add_Amazon_Pay_To_Express_Checkout_Locations unit tests.
 */
class Add_Amazon_Pay_To_Express_Checkout_Locations_Test extends WCPAY_UnitTestCase {

	const CARD_SETTINGS_OPTION_KEY = 'woocommerce_woocommerce_payments_settings';

	/**
	 * @var Add_Amazon_Pay_To_Express_Checkout_Locations
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		$this->migration = new Add_Amazon_Pay_To_Express_Checkout_Locations();

		update_option( 'woocommerce_woocommerce_payments_version', '10.4.0' );
	}

	public function tear_down() {
		delete_option( 'woocommerce_woocommerce_payments_version' );
		delete_option( self::CARD_SETTINGS_OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * @dataProvider versions_that_should_skip_migration_provider
	 */
	public function test_it_does_nothing_if_version_is_10_5_0_or_higher( string $stored_version ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_version );
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'express_checkout_product_methods'  => [ 'payment_request', 'woopay' ],
				'express_checkout_cart_methods'     => [ 'payment_request', 'woopay' ],
				'express_checkout_checkout_methods' => [ 'payment_request', 'woopay' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// amazon_pay should NOT have been added.
		$this->assertNotContains( 'amazon_pay', $settings['express_checkout_product_methods'] );
		$this->assertNotContains( 'amazon_pay', $settings['express_checkout_cart_methods'] );
		$this->assertNotContains( 'amazon_pay', $settings['express_checkout_checkout_methods'] );
	}

	public function versions_that_should_skip_migration_provider(): array {
		return [
			'same version'        => [ '10.5.0' ],
			'newer patch version' => [ '10.5.1' ],
			'newer minor version' => [ '10.6.0' ],
			'newer major version' => [ '11.0.0' ],
		];
	}

	public function test_it_does_nothing_if_express_checkout_settings_do_not_exist() {
		update_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Settings should remain empty (no migration needed for fresh installs).
		$this->assertArrayNotHasKey( 'express_checkout_product_methods', $settings );
	}

	public function test_it_adds_amazon_pay_to_all_locations() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'express_checkout_product_methods'  => [ 'payment_request', 'woopay' ],
				'express_checkout_cart_methods'     => [ 'payment_request', 'woopay' ],
				'express_checkout_checkout_methods' => [ 'payment_request', 'woopay' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// amazon_pay should have been added to all locations.
		$this->assertContains( 'amazon_pay', $settings['express_checkout_product_methods'] );
		$this->assertContains( 'amazon_pay', $settings['express_checkout_cart_methods'] );
		$this->assertContains( 'amazon_pay', $settings['express_checkout_checkout_methods'] );

		// Existing methods should be preserved.
		$this->assertContains( 'payment_request', $settings['express_checkout_product_methods'] );
		$this->assertContains( 'woopay', $settings['express_checkout_product_methods'] );
	}

	public function test_it_does_not_add_amazon_pay_if_already_present() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'express_checkout_product_methods'  => [ 'payment_request', 'woopay', 'amazon_pay' ],
				'express_checkout_cart_methods'     => [ 'payment_request', 'woopay' ],
				'express_checkout_checkout_methods' => [ 'payment_request', 'woopay' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// amazon_pay should appear only once in product methods.
		$this->assertEquals(
			1,
			count( array_keys( $settings['express_checkout_product_methods'], 'amazon_pay', true ) )
		);

		// amazon_pay should have been added to cart and checkout.
		$this->assertContains( 'amazon_pay', $settings['express_checkout_cart_methods'] );
		$this->assertContains( 'amazon_pay', $settings['express_checkout_checkout_methods'] );
	}

	public function test_it_handles_empty_location_arrays() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'express_checkout_product_methods'  => [],
				'express_checkout_cart_methods'     => [ 'payment_request' ],
				'express_checkout_checkout_methods' => [],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// amazon_pay should be added even to empty arrays.
		$this->assertEquals( [ 'amazon_pay' ], $settings['express_checkout_product_methods'] );
		$this->assertContains( 'amazon_pay', $settings['express_checkout_cart_methods'] );
		$this->assertEquals( [ 'amazon_pay' ], $settings['express_checkout_checkout_methods'] );
	}

	public function test_it_preserves_other_settings() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'express_checkout_product_methods'  => [ 'payment_request' ],
				'express_checkout_cart_methods'     => [ 'payment_request' ],
				'express_checkout_checkout_methods' => [ 'payment_request' ],
				'enabled'                           => 'yes',
				'test_mode'                         => 'no',
				'other_setting'                     => 'some_value',
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Other settings should be preserved.
		$this->assertEquals( 'yes', $settings['enabled'] );
		$this->assertEquals( 'no', $settings['test_mode'] );
		$this->assertEquals( 'some_value', $settings['other_setting'] );
	}
}
