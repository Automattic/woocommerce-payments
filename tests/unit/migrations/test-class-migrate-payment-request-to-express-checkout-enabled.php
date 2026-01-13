<?php
/**
 * Class Migrate_Payment_Request_To_Express_Checkout_Enabled_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Migrate_Payment_Request_To_Express_Checkout_Enabled;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Migrate_Payment_Request_To_Express_Checkout_Enabled unit tests.
 */
class Migrate_Payment_Request_To_Express_Checkout_Enabled_Test extends WCPAY_UnitTestCase {

	const CARD_SETTINGS_OPTION_KEY = 'woocommerce_woocommerce_payments_settings';
	const GOOGLE_PAY_OPTION_KEY    = 'woocommerce_woocommerce_payments_google_pay_settings';
	const APPLE_PAY_OPTION_KEY     = 'woocommerce_woocommerce_payments_apple_pay_settings';

	/**
	 * @var Migrate_Payment_Request_To_Express_Checkout_Enabled
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		$this->migration = new Migrate_Payment_Request_To_Express_Checkout_Enabled();

		delete_option( self::GOOGLE_PAY_OPTION_KEY );
		delete_option( self::APPLE_PAY_OPTION_KEY );
		update_option( 'woocommerce_woocommerce_payments_version', '10.3.0' );
	}

	public function tear_down() {
		delete_option( 'woocommerce_woocommerce_payments_version' );
		delete_option( self::CARD_SETTINGS_OPTION_KEY );
		delete_option( self::GOOGLE_PAY_OPTION_KEY );
		delete_option( self::APPLE_PAY_OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * @dataProvider versions_that_should_skip_migration_provider
	 */
	public function test_it_does_nothing_if_version_is_10_4_0_or_higher( string $stored_version ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_version );
		update_option( self::CARD_SETTINGS_OPTION_KEY, [ 'payment_request' => 'yes' ] );

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );
		$this->assertArrayHasKey( 'payment_request', $settings );

		$this->assertFalse( get_option( self::GOOGLE_PAY_OPTION_KEY ) );
		$this->assertFalse( get_option( self::APPLE_PAY_OPTION_KEY ) );
	}

	public function versions_that_should_skip_migration_provider(): array {
		return [
			'same version'        => [ '10.4.0' ],
			'newer patch version' => [ '10.4.1' ],
			'newer minor version' => [ '10.5.0' ],
			'newer major version' => [ '11.0.0' ],
		];
	}

	public function test_it_does_nothing_if_payment_request_setting_does_not_exist() {
		update_option( self::CARD_SETTINGS_OPTION_KEY, [] );

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );

		$this->assertFalse( get_option( self::GOOGLE_PAY_OPTION_KEY ) );
		$this->assertFalse( get_option( self::APPLE_PAY_OPTION_KEY ) );
	}

	public function test_it_migrates_payment_request_enabled_to_google_pay_and_apple_pay() {
		update_option( self::CARD_SETTINGS_OPTION_KEY, [ 'payment_request' => 'yes' ] );

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );

		$google_pay_settings = get_option( self::GOOGLE_PAY_OPTION_KEY, [] );
		$apple_pay_settings  = get_option( self::APPLE_PAY_OPTION_KEY, [] );
		$this->assertEquals( 'yes', $google_pay_settings['enabled'] );
		$this->assertEquals( 'yes', $apple_pay_settings['enabled'] );
	}

	public function test_it_migrates_payment_request_disabled_to_google_pay_and_apple_pay() {
		update_option( self::CARD_SETTINGS_OPTION_KEY, [ 'payment_request' => 'no' ] );

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );

		$google_pay_settings = get_option( self::GOOGLE_PAY_OPTION_KEY, [] );
		$apple_pay_settings  = get_option( self::APPLE_PAY_OPTION_KEY, [] );
		$this->assertEquals( 'no', $google_pay_settings['enabled'] );
		$this->assertEquals( 'no', $apple_pay_settings['enabled'] );
	}

	public function test_it_handles_missing_payment_request_value_as_disabled() {
		update_option( self::CARD_SETTINGS_OPTION_KEY, [ 'payment_request' => '' ] );

		$this->migration->maybe_migrate();

		$google_pay_settings = get_option( self::GOOGLE_PAY_OPTION_KEY, [] );
		$apple_pay_settings  = get_option( self::APPLE_PAY_OPTION_KEY, [] );
		$this->assertEquals( 'no', $google_pay_settings['enabled'] );
		$this->assertEquals( 'no', $apple_pay_settings['enabled'] );
	}

	public function test_it_preserves_other_settings_in_card_gateway() {
		update_option(
			self::CARD_SETTINGS_OPTION_KEY,
			[
				'payment_request' => 'yes',
				'enabled'         => 'yes',
				'test_mode'       => 'no',
				'other_setting'   => 'some_value',
			]
		);

		$this->migration->maybe_migrate();

		$settings = get_option( self::CARD_SETTINGS_OPTION_KEY, [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
		$this->assertEquals( 'yes', $settings['enabled'] );
		$this->assertEquals( 'no', $settings['test_mode'] );
		$this->assertEquals( 'some_value', $settings['other_setting'] );
	}
}
