<?php
/**
 * Class Multi_Currency_Cache_Autodetect_Existing_Install_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Multi_Currency_Cache_Autodetect_Existing_Install;
use WCPay\MultiCurrency\MultiCurrency;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Multi_Currency_Cache_Autodetect_Existing_Install unit tests.
 */
class Multi_Currency_Cache_Autodetect_Existing_Install_Test extends WCPAY_UnitTestCase {

	const DONE_OPTION    = 'wcpay_multi_currency_cache_autodetect_done';
	const VERSION_OPTION = 'woocommerce_woocommerce_payments_version';

	/**
	 * @var Multi_Currency_Cache_Autodetect_Existing_Install
	 */
	private $migration;

	public function set_up() {
		parent::set_up();
		$this->migration = new Multi_Currency_Cache_Autodetect_Existing_Install();
		delete_option( self::DONE_OPTION );
	}

	public function tear_down() {
		delete_option( self::DONE_OPTION );
		delete_option( self::VERSION_OPTION );
		parent::tear_down();
	}

	public function test_marks_done_for_existing_install_upgrading_from_older_version() {
		update_option( self::VERSION_OPTION, '10.8.0' );

		$this->migration->maybe_migrate();

		$this->assertSame( 'yes', get_option( self::DONE_OPTION ) );
	}

	public function test_does_nothing_for_fresh_install() {
		// Fresh install: no previous version stored — auto-detection should be left to run.
		delete_option( self::VERSION_OPTION );

		$this->migration->maybe_migrate();

		$this->assertFalse( get_option( self::DONE_OPTION ) );
	}

	public function test_does_nothing_when_already_on_this_version() {
		update_option( self::VERSION_OPTION, '11.0.0' );

		$this->migration->maybe_migrate();

		$this->assertFalse( get_option( self::DONE_OPTION ) );
	}

	public function test_does_nothing_when_on_a_newer_version() {
		update_option( self::VERSION_OPTION, '11.1.0' );

		$this->migration->maybe_migrate();

		$this->assertFalse( get_option( self::DONE_OPTION ) );
	}

	/**
	 * Guards against the migration's option literal drifting from the canonical constant.
	 */
	public function test_option_name_matches_multi_currency_constant() {
		$this->assertSame(
			MultiCurrency::CACHE_AUTODETECT_DONE_OPTION,
			Multi_Currency_Cache_Autodetect_Existing_Install::AUTODETECT_DONE_OPTION
		);
	}
}
