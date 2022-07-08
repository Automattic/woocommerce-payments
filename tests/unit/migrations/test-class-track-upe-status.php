<?php
/**
 * Class Track_Upe_Status_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use WCPay\Tracker;
use WCPAY_UnitTestCase;
use WC_Payments_Features;

/**
 * WCPay\Migrations\Track_Upe_Status unit tests.
 */
class Track_Upe_Status_Test extends WCPAY_UnitTestCase {

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Track_Upe_Status::IS_TRACKED_OPTION );
	}

	/**
	 * After-test cleanup
	 */
	public function tear_down() {
		parent::tear_down();

		Tracker::remove_admin_event( 'wcpay_upe_enabled' );
		Tracker::remove_admin_event( 'wcpay_upe_disabled' );
	}

	/**
	 * Make sure the 'wcpay_upe_enabled' event is registered when upe is enabled.
	 */
	public function test_track_enabled_on_upgrade() {
		update_option( WC_Payments_Features::UPE_FLAG_NAME, '1' );

		Track_Upe_Status::maybe_track();

		$this->assertEquals(
			[
				'wcpay_upe_enabled' => [],
			],
			Tracker::get_admin_events()
		);

		$this->assertSame( '1', get_option( Track_Upe_Status::IS_TRACKED_OPTION ) );
	}

	public function test_track_disabled_on_upgrade() {
		update_option( WC_Payments_Features::UPE_FLAG_NAME, 'disabled' );

		Track_Upe_Status::maybe_track();

		$this->assertEquals(
			[
				'wcpay_upe_disabled' => [],
			],
			Tracker::get_admin_events()
		);

		$this->assertSame( '1', get_option( Track_Upe_Status::IS_TRACKED_OPTION ) );
	}

	public function test_do_nothing_default_on_upgrade() {
		delete_option( WC_Payments_Features::UPE_FLAG_NAME );

		Track_Upe_Status::maybe_track();

		$this->assertEquals( [], Tracker::get_admin_events() );

		$this->assertSame( '1', get_option( Track_Upe_Status::IS_TRACKED_OPTION ) );
	}

	public function test_do_nothing_when_tracked() {
		update_option( Track_Upe_Status::IS_TRACKED_OPTION, '1' );

		Track_Upe_Status::maybe_track();

		$this->assertEquals( [], Tracker::get_admin_events() );
	}
}
