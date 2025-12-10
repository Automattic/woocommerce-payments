<?php
/**
 * Class Migrate_Payment_Request_To_Express_Checkout_Enabled_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Payment_Gateway_WCPay;
use WC_Payments;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Migrate_Payment_Request_To_Express_Checkout_Enabled unit tests.
 */
class Migrate_Payment_Request_To_Express_Checkout_Enabled_Test extends WCPAY_UnitTestCase {

	/**
	 * Google Pay gateway mock.
	 *
	 * @var MockObject|WC_Payment_Gateway_WCPay
	 */
	private $google_pay_gateway_mock;

	/**
	 * Apple Pay gateway mock.
	 *
	 * @var MockObject|WC_Payment_Gateway_WCPay
	 */
	private $apple_pay_gateway_mock;

	/**
	 * @var Migrate_Payment_Request_To_Express_Checkout_Enabled
	 */
	private $migration;

	/**
	 * Backup of the original payment_gateway_map
	 *
	 * @var array
	 */
	private $original_payment_gateway_map;

	public function set_up() {
		parent::set_up();

		// Save the original payment_gateway_map before any test modifications.
		$this->original_payment_gateway_map = $this->get_payment_gateway_map();

		$this->google_pay_gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->apple_pay_gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->migration = new Migrate_Payment_Request_To_Express_Checkout_Enabled();
	}

	public function tear_down() {
		// Restore the original payment gateway map to prevent test pollution.
		$this->set_payment_gateway_map( $this->original_payment_gateway_map );
		parent::tear_down();
	}

	public function test_it_does_nothing_if_payment_request_setting_does_not_exist() {
		// Setup: Card gateway without payment_request setting.
		update_option( 'woocommerce_woocommerce_payments_settings', [] );

		// Mock WC_Payments::get_payment_gateway_by_id to never be called.
		$this->mock_get_payment_gateway_by_id_to_never_be_called();

		$this->migration->maybe_migrate();

		// Assert payment_request setting still doesn't exist.
		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
	}

	public function test_it_migrates_payment_request_enabled_to_google_pay_and_apple_pay() {
		// Setup: Card gateway with payment_request = 'yes'.
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'yes' ] );

		// Mock the gateway retrieval.
		$this->mock_get_payment_gateway_by_id();

		// Expect both gateways to have 'enabled' set to 'yes'.
		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		$this->migration->maybe_migrate();

		// Assert payment_request setting was deleted.
		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
	}

	public function test_it_migrates_payment_request_disabled_to_google_pay_and_apple_pay() {
		// Setup: Card gateway with payment_request = 'no'.
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'no' ] );

		// Mock the gateway retrieval.
		$this->mock_get_payment_gateway_by_id();

		// Expect both gateways to have 'enabled' set to 'no'.
		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->migration->maybe_migrate();

		// Assert payment_request setting was deleted.
		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
	}

	public function test_it_handles_missing_payment_request_value_as_disabled() {
		// Setup: Card gateway with payment_request key present but empty.
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => '' ] );

		// Mock the gateway retrieval.
		$this->mock_get_payment_gateway_by_id();

		// Expect both gateways to have 'enabled' set to 'no' (default).
		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->migration->maybe_migrate();
	}

	public function test_it_handles_missing_google_pay_gateway() {
		// Setup: Card gateway with payment_request = 'yes'.
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'yes' ] );

		// Mock only Apple Pay gateway available.
		$this->mock_get_payment_gateway_by_id( false, true );

		// Google Pay update should not be called.
		$this->google_pay_gateway_mock->expects( $this->never() )
			->method( 'update_option' );

		// Apple Pay should still be updated.
		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		$this->migration->maybe_migrate();
	}

	public function test_it_handles_missing_apple_pay_gateway() {
		// Setup: Card gateway with payment_request = 'yes'.
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'yes' ] );

		// Mock only Google Pay gateway available.
		$this->mock_get_payment_gateway_by_id( true, false );

		// Google Pay should be updated.
		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		// Apple Pay update should not be called.
		$this->apple_pay_gateway_mock->expects( $this->never() )
			->method( 'update_option' );

		$this->migration->maybe_migrate();
	}

	public function test_it_preserves_other_settings_in_card_gateway() {
		// Setup: Card gateway with payment_request and other settings.
		update_option(
			'woocommerce_woocommerce_payments_settings',
			[
				'payment_request' => 'yes',
				'enabled'         => 'yes',
				'test_mode'       => 'no',
				'other_setting'   => 'some_value',
			]
		);

		// Mock the gateway retrieval.
		$this->mock_get_payment_gateway_by_id();

		// Setup gateway mocks to accept update_option calls.
		$this->google_pay_gateway_mock->method( 'update_option' );
		$this->apple_pay_gateway_mock->method( 'update_option' );

		$this->migration->maybe_migrate();

		// Assert other settings are preserved, only payment_request was deleted.
		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
		$this->assertEquals( 'yes', $settings['enabled'] );
		$this->assertEquals( 'no', $settings['test_mode'] );
		$this->assertEquals( 'some_value', $settings['other_setting'] );
	}

	/**
	 * Mock WC_Payments::get_payment_gateway_by_id to return gateway mocks.
	 *
	 * @param bool $has_google_pay Whether Google Pay gateway should be available.
	 * @param bool $has_apple_pay  Whether Apple Pay gateway should be available.
	 */
	private function mock_get_payment_gateway_by_id( $has_google_pay = true, $has_apple_pay = true ) {
		$gateway_map = [];

		if ( $has_google_pay ) {
			$gateway_map['google_pay'] = $this->google_pay_gateway_mock;
		}

		if ( $has_apple_pay ) {
			$gateway_map['apple_pay'] = $this->apple_pay_gateway_mock;
		}

		$this->set_payment_gateway_map( $gateway_map );
	}

	/**
	 * Mock WC_Payments::get_payment_gateway_by_id to never be called.
	 */
	private function mock_get_payment_gateway_by_id_to_never_be_called() {
		// For this test, we just set an empty map - the method shouldn't be called at all
		// if the payment_request setting doesn't exist.
		$this->set_payment_gateway_map( [] );
	}

	/**
	 * Helper to get the current payment_gateway_map for backup purposes.
	 *
	 * @return array The current payment_gateway_map.
	 */
	private function get_payment_gateway_map() {
		$reflection = new \ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$value = $property->getValue( null );
		$property->setAccessible( false );
		return $value;
	}

	/**
	 * Helper to set up mock gateways in the payment_gateway_map for testing.
	 *
	 * @param array $gateway_map Associative array of gateway_id => gateway_instance.
	 */
	private function set_payment_gateway_map( $gateway_map ) {
		$reflection = new \ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$property->setValue( null, $gateway_map );
		$property->setAccessible( false );
	}
}
