<?php
/**
 * Class Payment_Request_To_Digital_Wallets_Settings_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use WP_UnitTestCase;

/**
 * WCPay\Migrations\Payment_Request_To_Digital_Wallets_Settings unit tests.
 */
class Payment_Request_To_Digital_Wallets_Settings_Test extends WP_UnitTestCase {

	/**
	 * @var string[]
	 */
	const OLD_TO_NEW_SETTING_NAMES_MAPPING = [
		'payment_request'              => 'is_digital_wallets_enabled',
		'payment_request_button_theme' => 'digital_wallets_button_theme',
		'payment_request_button_type'  => 'digital_wallets_button_type',
	];

	/**
	 * @var Payment_Request_To_Digital_Wallets_Settings
	 */
	private $migration;

	public function setUp() {
		$this->migration = new Payment_Request_To_Digital_Wallets_Settings();
	}

	/**
	 * @dataProvider versions_without_applying_migration_provider
	 */
	public function test_it_does_nothing_if_migration_was_applied_in_previous_version( string $stored_wcpay_version ) {
		$old_settings = [
			'payment_request' => true,
		];

		$this->setup_environment( $stored_wcpay_version, $old_settings );

		$this->migration->maybe_migrate();

		$this->assertEquals( $old_settings, $this->get_wcpay_settings() );
	}

	public function test_it_migrates_if_wcpay_version_is_missing() {
		$old_settings = [
			'payment_request' => true,
		];

		$this->setup_environment( null, $old_settings );

		$this->migration->maybe_migrate();

		$expected_settings = array_merge(
			$old_settings,
			[
				'is_digital_wallets_enabled' => true,
			]
		);

		$this->assertEquals( $expected_settings, $this->get_wcpay_settings() );
	}

	public function test_it_copies_settings_under_new_names() {
		$old_settings = [
			'payment_request'              => true,
			'payment_request_button_theme' => 'light',
			'payment_request_button_type'  => 'buy',
		];

		$this->setup_environment( '2.5.0', $old_settings );

		$this->migration->maybe_migrate();

		$expected_settings = array_merge(
			$old_settings,
			[
				'is_digital_wallets_enabled'   => true,
				'digital_wallets_button_theme' => 'light',
				'digital_wallets_button_type'  => 'buy',
			]
		);

		$this->assertEquals( $expected_settings, $this->get_wcpay_settings() );
	}

	/**
	 * @dataProvider partially_missing_settings_provider
	 */
	public function test_it_does_not_set_any_values_for_missing_settings( array $old_settings ) {
		$this->setup_environment( '2.5.0', $old_settings );

		$this->migration->maybe_migrate();

		$expected_settings = $old_settings;
		foreach ( $old_settings as $old_key => $value ) {
			$expected_settings[ self::OLD_TO_NEW_SETTING_NAMES_MAPPING[ $old_key ] ] = $value;
		}

		$this->assertEquals( $expected_settings, $this->get_wcpay_settings() );
	}

	/**
	 * @dataProvider empty_settings_provider
	 */
	public function test_it_does_not_migrate_settings_if_none_are_set( $old_settings ) {
		$this->setup_environment( '2.5.0', $old_settings );

		$this->migration->maybe_migrate();

		$this->assertEquals( $old_settings, $this->get_wcpay_settings() );
	}

	public function test_it_does_not_migrate_if_it_would_overwrite_existing_setting_under_new_name() {
		$old_settings = [
			'payment_request'              => true,
			'payment_request_button_theme' => 'light',
			'payment_request_button_type'  => 'buy',
			'is_digital_wallets_enabled'   => false,
		];

		$this->setup_environment( '2.5.0', $old_settings );

		$this->migration->maybe_migrate();

		$expected_settings = array_merge(
			$old_settings,
			[
				'is_digital_wallets_enabled'   => false,
				'digital_wallets_button_theme' => 'light',
				'digital_wallets_button_type'  => 'buy',
			]
		);

		$this->assertEquals( $expected_settings, $this->get_wcpay_settings() );
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

		$expected_settings = array_merge(
			$old_settings,
			[
				'digital_wallets_button_type' => $expected_mapped_value,
			]
		);

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

	public function partially_missing_settings_provider() {
		return [
			'missing payment_request'              => [
				[
					'payment_request_button_theme' => 'light',
					'payment_request_button_type'  => 'buy',
				],
			],
			'missing payment_request_button_theme' => [
				[
					'payment_request'             => true,
					'payment_request_button_type' => 'buy',
				],
			],
			'missing payment_request_button_type'  => [
				[
					'payment_request'              => true,
					'payment_request_button_theme' => 'light',
				],
			],
		];
	}

	public function empty_settings_provider() {
		return [
			'existing settings = []'    => [ [] ],
			'existing settings = null'  => [ null ],
			'existing settings = false' => [ false ],
		];
	}
}
