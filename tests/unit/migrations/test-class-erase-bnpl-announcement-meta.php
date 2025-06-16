<?php
/**
 * Class Erase_Bnpl_Announcement_Meta_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Erase_Bnpl_Announcement_Meta;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Erase_Bnpl_Announcement_Meta unit tests.
 */
class Erase_Bnpl_Announcement_Meta_Test extends WCPAY_UnitTestCase {

	/**
	 * @var Erase_Bnpl_Announcement_Meta
	 */
	private $migration;

	public function set_up() {
		$this->migration = new Erase_Bnpl_Announcement_Meta();
		set_transient( 'wcpay_bnpl_april15_successful_purchases_count', '5', 10 * DAY_IN_SECONDS );
		add_user_meta( 1, '__unrelated_meta', 'fake-value', true );
		// adding the meta for a couple of users, just in case.
		add_user_meta( 1, '_wcpay_bnpl_april15_viewed', '1', true );
		add_user_meta( 2, '_wcpay_bnpl_april15_viewed', '1', true );
	}

	public function tear_down() {
		delete_transient( 'wcpay_bnpl_april15_successful_purchases_count' );
		delete_user_meta( 1, '__unrelated_meta' );
		delete_user_meta( 1, '_wcpay_bnpl_april15_viewed' );
		delete_user_meta( 2, '_wcpay_bnpl_april15_viewed' );

		parent::tear_down();
	}

	/**
	 * @dataProvider versions_without_applying_migration_provider
	 */
	public function test_it_does_nothing_if_migration_was_already_applied( string $stored_wcpay_version ) {
		$this->setup_environment( $stored_wcpay_version );

		$this->migration->maybe_migrate();

		// no updates.
		$this->assertEquals( '1', get_user_meta( 1, '_wcpay_bnpl_april15_viewed', true ) );
		$this->assertEquals( '1', get_user_meta( 2, '_wcpay_bnpl_april15_viewed', true ) );
		$this->assertEquals( '5', get_transient( 'wcpay_bnpl_april15_successful_purchases_count' ) );
		$this->assertEquals( 'fake-value', get_user_meta( 1, '__unrelated_meta', true ) );
	}

	public function test_it_migrates_if_stored_woopayments_version_is_too_old() {
		$this->setup_environment( '8.0.0' );

		$this->migration->maybe_migrate();

		// user meta has been deleted.
		$this->assertEquals( '', get_user_meta( 1, '_wcpay_bnpl_april15_viewed', true ) );
		$this->assertEquals( '', get_user_meta( 2, '_wcpay_bnpl_april15_viewed', true ) );
		$this->assertEquals( false, get_transient( 'wcpay_bnpl_april15_successful_purchases_count' ) );
		// this meta remains untouched.
		$this->assertEquals( 'fake-value', get_user_meta( 1, '__unrelated_meta', true ) );
	}

	private function setup_environment( $stored_wcpay_version ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_wcpay_version );
	}

	public function versions_without_applying_migration_provider() {
		return [
			'newer major version' => [ '8.2.0' ],
			'newer minor version' => [ '8.1.1' ],
			'same version'        => [ '8.1.0' ],
		];
	}
}
