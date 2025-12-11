<?php
/**
 * Class Migrate_Express_Checkout_Locations_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Migrate_Express_Checkout_Locations;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Migrate_Express_Checkout_Locations unit tests.
 */
class Migrate_Express_Checkout_Locations_Test extends WCPAY_UnitTestCase {

	const CARD_SETTINGS_OPTION_KEY = 'woocommerce_woocommerce_payments_settings';

	/**
	 * @var Migrate_Express_Checkout_Locations
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		$this->migration = new Migrate_Express_Checkout_Locations();

		update_option( 'woocommerce_woocommerce_payments_version', '10.3.0' );
	}

	public function tear_down() {
		delete_option( 'woocommerce_woocommerce_payments_version' );
		delete_option( self::CARD_SETTINGS_OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * @dataProvider versions_that_should_skip_migration_provider
	 */
	public function test_it_does_nothing_if_version_is_10_4_0_or_higher( string $stored_version ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_version );
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'payment_request_button_locations'   => [ 'product', 'cart' ],
				'platform_checkout_button_locations' => [ 'cart', 'checkout' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Old settings should still exist (not migrated).
		$this->assertArrayHasKey( 'payment_request_button_locations', $settings );
		$this->assertArrayHasKey( 'platform_checkout_button_locations', $settings );

		// New settings should not exist.
		$this->assertArrayNotHasKey( 'express_checkout_product_methods', $settings );
		$this->assertArrayNotHasKey( 'express_checkout_cart_methods', $settings );
		$this->assertArrayNotHasKey( 'express_checkout_checkout_methods', $settings );
	}

	public function versions_that_should_skip_migration_provider(): array {
		return [
			'same version'        => [ '10.4.0' ],
			'newer patch version' => [ '10.4.1' ],
			'newer minor version' => [ '10.5.0' ],
			'newer major version' => [ '11.0.0' ],
		];
	}

	public function test_it_does_nothing_if_old_settings_do_not_exist() {
		update_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );
		$this->assertArrayNotHasKey( 'express_checkout_product_methods', $settings );
		$this->assertArrayNotHasKey( 'express_checkout_cart_methods', $settings );
		$this->assertArrayNotHasKey( 'express_checkout_checkout_methods', $settings );
	}

	public function test_it_does_nothing_if_new_settings_already_exist() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'payment_request_button_locations'   => [ 'product', 'cart' ],
				'platform_checkout_button_locations' => [ 'cart', 'checkout' ],
				'express_checkout_product_methods'   => [ 'payment_request' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Old settings should still exist (not migrated).
		$this->assertArrayHasKey( 'payment_request_button_locations', $settings );
		$this->assertArrayHasKey( 'platform_checkout_button_locations', $settings );

		// New settings should be unchanged.
		$this->assertEquals( [ 'payment_request' ], $settings['express_checkout_product_methods'] );
	}

	public function test_it_migrates_both_payment_request_and_woopay_locations() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'payment_request_button_locations'   => [ 'product', 'cart', 'checkout' ],
				'platform_checkout_button_locations' => [ 'product', 'cart' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Old settings should be removed.
		$this->assertArrayNotHasKey( 'payment_request_button_locations', $settings );
		$this->assertArrayNotHasKey( 'platform_checkout_button_locations', $settings );

		// New settings should exist with correct values.
		$this->assertEquals( [ 'payment_request', 'woopay' ], $settings['express_checkout_product_methods'] );
		$this->assertEquals( [ 'payment_request', 'woopay' ], $settings['express_checkout_cart_methods'] );
		$this->assertEquals( [ 'payment_request' ], $settings['express_checkout_checkout_methods'] );
	}

	public function test_it_migrates_only_payment_request_if_woopay_not_set() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'payment_request_button_locations' => [ 'product', 'checkout' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Old settings should be removed.
		$this->assertArrayNotHasKey( 'payment_request_button_locations', $settings );

		// New settings should use defaults for woopay (all locations).
		$this->assertEquals( [ 'payment_request', 'woopay' ], $settings['express_checkout_product_methods'] );
		$this->assertEquals( [ 'woopay' ], $settings['express_checkout_cart_methods'] );
		$this->assertEquals( [ 'payment_request', 'woopay' ], $settings['express_checkout_checkout_methods'] );
	}

	public function test_it_migrates_only_woopay_if_payment_request_not_set() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'platform_checkout_button_locations' => [ 'cart' ],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Old settings should be removed.
		$this->assertArrayNotHasKey( 'platform_checkout_button_locations', $settings );

		// New settings should use defaults for payment_request (all locations).
		$this->assertEquals( [ 'payment_request' ], $settings['express_checkout_product_methods'] );
		$this->assertEquals( [ 'payment_request', 'woopay' ], $settings['express_checkout_cart_methods'] );
		$this->assertEquals( [ 'payment_request' ], $settings['express_checkout_checkout_methods'] );
	}

	public function test_it_migrates_empty_arrays() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'payment_request_button_locations'   => [],
				'platform_checkout_button_locations' => [],
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Old settings should be removed.
		$this->assertArrayNotHasKey( 'payment_request_button_locations', $settings );
		$this->assertArrayNotHasKey( 'platform_checkout_button_locations', $settings );

		// New settings should be empty.
		$this->assertEquals( [], $settings['express_checkout_product_methods'] );
		$this->assertEquals( [], $settings['express_checkout_cart_methods'] );
		$this->assertEquals( [], $settings['express_checkout_checkout_methods'] );
	}

	public function test_it_preserves_other_settings_in_card_gateway() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'payment_request_button_locations'   => [ 'product' ],
				'platform_checkout_button_locations' => [ 'cart' ],
				'enabled'                            => 'yes',
				'test_mode'                          => 'no',
				'other_setting'                      => 'some_value',
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		// Old location settings should be removed.
		$this->assertArrayNotHasKey( 'payment_request_button_locations', $settings );
		$this->assertArrayNotHasKey( 'platform_checkout_button_locations', $settings );

		// Other settings should be preserved.
		$this->assertEquals( 'yes', $settings['enabled'] );
		$this->assertEquals( 'no', $settings['test_mode'] );
		$this->assertEquals( 'some_value', $settings['other_setting'] );

		// New settings should exist.
		$this->assertArrayHasKey( 'express_checkout_product_methods', $settings );
		$this->assertArrayHasKey( 'express_checkout_cart_methods', $settings );
		$this->assertArrayHasKey( 'express_checkout_checkout_methods', $settings );
	}
}
