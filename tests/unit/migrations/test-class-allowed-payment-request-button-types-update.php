<?php
/**
 * Class Allowed_Payment_Request_Button_Types_Update_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Payment_Gateway_WCPay;
use WP_UnitTestCase;

/**
 * WCPay\Migrations\Allowed_Payment_Request_Button_Types_Update unit tests.
 */
class Allowed_Payment_Request_Button_Types_Update_Test extends WP_UnitTestCase {

	/**
	 * WCPay gateway mock.
	 *
	 * @var MockObject|WC_Payment_Gateway_WCPay
	 */
	private $gateway_mock;

	/**
	 * @var Allowed_Payment_Request_Button_Types_Update
	 */
	private $migration;

	public function setUp() {
		$this->gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$this->migration    = new Allowed_Payment_Request_Button_Types_Update( $this->gateway_mock );
	}

	/**
	 * @dataProvider versions_without_applying_migration_provider
	 */
	public function test_it_does_nothing_if_migration_was_already_applied_in_previous_version( string $stored_wcpay_version ) {
		$old_settings = [ 'payment_request_button_type' => 'branded' ];
		$this->setup_environment( $stored_wcpay_version, $old_settings );
		$this->gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->migration->maybe_migrate();
	}

	public function test_it_migrates_if_stored_wcpay_version_is_too_old() {
		$old_settings = [ 'payment_request_button_type' => 'custom' ];
		$this->setup_environment( '2.5.9', $old_settings );
		$this->gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'payment_request_button_type', 'buy' );

		$this->migration->maybe_migrate();
	}

	public function test_it_migrates_if_stored_wcpay_version_is_missing() {
		$old_settings = [ 'payment_request_button_type' => 'custom' ];
		$this->setup_environment( false, $old_settings );
		$this->gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'payment_request_button_type', 'buy' );

		$this->migration->maybe_migrate();
	}

	/**
	 * @dataProvider deprecated_values_provider
	 */
	public function test_it_maps_deprecated_button_type_values( string $button_type, string $branded_type = null, string $expected_mapped_value ) {
		$old_settings = [
			'payment_request_button_type'         => $button_type,
			'payment_request_button_branded_type' => $branded_type,
		];

		$this->setup_environment( '2.5.0', $old_settings );
		$this->gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'payment_request_button_type', $expected_mapped_value );

		$this->migration->maybe_migrate();
	}

	/**
	 * @dataProvider not_deprecated_values_provider
	 */
	public function test_it_does_not_map_values_other_than_deprecated( $button_type ) {
		$this->setup_environment( '2.5.0', [ 'payment_request_button_type' => $button_type ] );
		$this->gateway_mock->expects( $this->once() )
			->method( 'update_option' )
			->with( 'payment_request_button_type', $button_type );

		$this->migration->maybe_migrate();
	}

	private function setup_environment( $stored_wcpay_version, $settings ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_wcpay_version );
		$this->gateway_mock->method( 'get_option' )
			->willReturnCallback(
				function ( $key ) use ( $settings ) {
					return $settings[ $key ] ?? '';
				}
			);
	}

	public function versions_without_applying_migration_provider() {
		return [
			'newer major version' => [ '2.7.0' ],
			'newer minor version' => [ '2.6.1' ],
			'same version'        => [ '2.6.0' ],
		];
	}

	public function deprecated_values_provider() {
		return [
			'branded with type = short mapped to default' => [ 'branded', 'short', 'default' ],
			'branded with type != short mapped to buy'    => [ 'branded', 'foo', 'buy' ],
			'branded with missing type mapped to buy'     => [ 'branded', null, 'buy' ],
			'custom mapped to buy'                        => [ 'custom', null, 'buy' ],
		];
	}

	public function not_deprecated_values_provider() {
		return [
			'empty value' => [ '' ],
			[ 'foo' ],
			[ 'default' ],
			[ 'buy' ],
			[ 'donate' ],
		];
	}
}
