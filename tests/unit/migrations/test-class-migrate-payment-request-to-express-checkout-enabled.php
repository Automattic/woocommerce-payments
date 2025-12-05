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

		$this->original_payment_gateway_map = $this->get_payment_gateway_map();

		$this->google_pay_gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->apple_pay_gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->migration = new Migrate_Payment_Request_To_Express_Checkout_Enabled();

		update_option( 'woocommerce_woocommerce_payments_version', '10.3.0' );
	}

	public function tear_down() {
		$this->set_payment_gateway_map( $this->original_payment_gateway_map );
		delete_option( 'woocommerce_woocommerce_payments_version' );
		parent::tear_down();
	}

	public function test_it_does_nothing_if_version_is_10_4_0_or_higher() {
		update_option( 'woocommerce_woocommerce_payments_version', '10.4.0' );
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'yes' ] );
		$this->mock_get_payment_gateway_by_id();

		$this->google_pay_gateway_mock->expects( $this->never() )->method( 'update_option' );
		$this->apple_pay_gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->migration->maybe_migrate();

		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayHasKey( 'payment_request', $settings );
	}

	public function test_it_does_nothing_if_payment_request_setting_does_not_exist() {
		update_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->set_payment_gateway_map( [] );

		$this->migration->maybe_migrate();

		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
	}

	public function test_it_migrates_payment_request_enabled_to_google_pay_and_apple_pay() {
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'yes' ] );
		$this->mock_get_payment_gateway_by_id();

		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		$this->migration->maybe_migrate();

		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
	}

	public function test_it_migrates_payment_request_disabled_to_google_pay_and_apple_pay() {
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'no' ] );
		$this->mock_get_payment_gateway_by_id();

		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->migration->maybe_migrate();

		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
	}

	public function test_it_handles_missing_payment_request_value_as_disabled() {
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => '' ] );
		$this->mock_get_payment_gateway_by_id();

		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'no' );

		$this->migration->maybe_migrate();
	}

	public function test_it_handles_missing_google_pay_gateway() {
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'yes' ] );
		$this->mock_get_payment_gateway_by_id( false, true );

		$this->google_pay_gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->apple_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		$this->migration->maybe_migrate();
	}

	public function test_it_handles_missing_apple_pay_gateway() {
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'payment_request' => 'yes' ] );
		$this->mock_get_payment_gateway_by_id( true, false );

		$this->google_pay_gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'enabled', 'yes' );

		$this->apple_pay_gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->migration->maybe_migrate();
	}

	public function test_it_preserves_other_settings_in_card_gateway() {
		update_option(
			'woocommerce_woocommerce_payments_settings',
			[
				'payment_request' => 'yes',
				'enabled'         => 'yes',
				'test_mode'       => 'no',
				'other_setting'   => 'some_value',
			]
		);
		$this->mock_get_payment_gateway_by_id();

		$this->google_pay_gateway_mock->method( 'update_option' );
		$this->apple_pay_gateway_mock->method( 'update_option' );

		$this->migration->maybe_migrate();

		$settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertArrayNotHasKey( 'payment_request', $settings );
		$this->assertEquals( 'yes', $settings['enabled'] );
		$this->assertEquals( 'no', $settings['test_mode'] );
		$this->assertEquals( 'some_value', $settings['other_setting'] );
	}

	/**
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
}
