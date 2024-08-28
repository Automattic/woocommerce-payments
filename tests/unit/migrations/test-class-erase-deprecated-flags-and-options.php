<?php
/**
 * Class Erase_Deprecated_Flags_And_Options_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Erase_Deprecated_Flags_And_Options;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Erase_Deprecated_Flags_And_Options unit tests.
 */
class Erase_Deprecated_Flags_And_Options_Test extends WCPAY_UnitTestCase {

	/**
	 * @var Erase_Deprecated_Flags_And_Options
	 */
	private $migration;

	public function set_up() {
		$this->migration = new Erase_Deprecated_Flags_And_Options();
		// one of the old options, for which we'll test for.
		update_option( '_wcpay_feature_grouped_settings', '1' );
		// an unrelated option, that should not be deleted.
		update_option( '_wcpay__unrelated_option', 'fake-value' );
	}

	public function tear_down() {
		delete_option( '_wcpay_feature_grouped_settings' );
		delete_option( '_wcpay__unrelated_option' );

		parent::tear_down();
	}

	/**
	 * @dataProvider versions_not_applying_migration_provider
	 */
	public function test_it_does_nothing_if_migration_was_already_applied( string $stored_wcpay_version ) {
		$this->setup_environment( $stored_wcpay_version );

		$this->migration->maybe_migrate();

		// all options remains untouched.
		$this->assertEquals( '1', get_option( '_wcpay_feature_grouped_settings' ) );
		$this->assertEquals( 'fake-value', get_option( '_wcpay__unrelated_option' ) );
	}

	public function test_it_migrates_if_stored_woopayments_version_is_too_old() {
		$this->setup_environment( '8.0.0' );

		$this->migration->maybe_migrate();

		// this option has been deleted.
		$this->assertEquals( 'default-value', get_option( '_wcpay_feature_grouped_settings', 'default-value' ) );
		// this option remains untouched.
		$this->assertEquals( 'fake-value', get_option( '_wcpay__unrelated_option' ) );
	}

	private function setup_environment( $stored_wcpay_version ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_wcpay_version );
	}

	public function versions_not_applying_migration_provider() {
		return [
			'newer major version' => [ '8.2.0' ],
			'newer minor version' => [ '8.1.1' ],
			'same version'        => [ '8.1.0' ],
		];
	}
}
