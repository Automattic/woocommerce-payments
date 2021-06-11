<?php
/**
 * Class Allowed_Payment_Request_Button_Types_Update_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use WP_UnitTestCase;

/**
 * WCPay\Migrations\Allowed_Payment_Request_Button_Types_Update unit tests.
 */
class Allowed_Payment_Request_Button_Types_Update_Test extends WP_UnitTestCase {

	/**
	 * @var Allowed_Payment_Request_Button_Types_Update
	 */
	private $migration;

	public function setUp() {
		$this->migration = new Allowed_Payment_Request_Button_Types_Update();
	}

	/**
	 * @dataProvider versions_without_applying_migration_provider
	 */
	public function test_it_does_nothing_if_migration_was_already_applied_in_previous_version( string $stored_wcpay_version ) {
		$old_settings = [ 'payment_request_button_type' => 'branded' ];

		$this->setup_environment( $stored_wcpay_version, $old_settings );

		$this->migration->maybe_migrate();

		$this->assertEquals( $old_settings, $this->get_wcpay_settings() );
	}

	public function test_it_migrates_if_stored_wcpay_version_is_missing() {
		$old_settings = [ 'payment_request_button_type' => 'custom' ];

		$this->setup_environment( false, $old_settings );

		$this->migration->maybe_migrate();

		$expected_settings = [ 'payment_request_button_type' => 'buy' ];

		$this->assertEquals( $expected_settings, $this->get_wcpay_settings() );
	}

	/**
	 * @dataProvider button_type_setting_missing_provider
	 */
	public function test_it_does_not_update_value_if_there_is_no_existing_value( $old_settings ) {
		$this->setup_environment( '2.5.0', $old_settings );

		$this->migration->maybe_migrate();

		$this->assertEquals( $old_settings, $this->get_wcpay_settings() );
	}

	/**
	 * @dataProvider deprecated_value_mapping_provider
	 */
	public function test_it_maps_deprecated_button_type_values( string $button_type, string $branded_type = null, string $expected_mapped_value ) {
		$old_settings = [
			'payment_request_button_type'         => $button_type,
			'payment_request_button_branded_type' => $branded_type,
		];

		$this->setup_environment( '2.5.0', $old_settings );

		$this->migration->maybe_migrate();

		$expected_settings = [
			'payment_request_button_type'         => $expected_mapped_value,
			'payment_request_button_branded_type' => $branded_type,
		];

		$this->assertEquals( $expected_settings, $this->get_wcpay_settings() );
	}

	private function setup_environment( $stored_wcpay_version, $settings ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_wcpay_version );
		update_option( 'woocommerce_woocommerce_payments_settings', $settings );
	}

	private function get_wcpay_settings() {
		return get_option( 'woocommerce_woocommerce_payments_settings' );
	}

	public function versions_without_applying_migration_provider() {
		return [
			'newer major version' => [ '2.7.0' ],
			'newer minor version' => [ '2.6.1' ],
			'same version'        => [ '2.6.0' ],
		];
	}

	public function deprecated_value_mapping_provider() {
		return [
			'branded with type = short mapped to default' => [ 'branded', 'short', 'default' ],
			'branded with type != short mapped to buy'    => [ 'branded', 'foo', 'buy' ],
			'branded with missing type mapped to buy'     => [ 'branded', null, 'buy' ],
			'custom mapped to buy'                        => [ 'custom', null, 'buy' ],
		];
	}

	public function button_type_setting_missing_provider() {
		return [
			'existing settings = []'    => [ [] ],
			'existing settings = null'  => [ null ],
			'existing settings = false' => [ false ],
			'button type not set'       => [ [ 'payment_request_button_branded_type' => 'short' ] ],
		];
	}
}
