<?php
/**
 * Class Dispute_Ledger_Backfill_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WC_Payments_Dispute_Ledger_Backfill_Service;
use WCPay\Migrations\Dispute_Ledger_Backfill;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Dispute_Ledger_Backfill unit tests.
 */
class Dispute_Ledger_Backfill_Test extends WCPAY_UnitTestCase {

	const STATE_OPTION   = 'wcpay_dispute_ledger_backfill_state';
	const VERSION_OPTION = 'woocommerce_woocommerce_payments_version';

	/**
	 * @var Dispute_Ledger_Backfill
	 */
	private $migration;

	public function set_up() {
		parent::set_up();
		$this->migration = new Dispute_Ledger_Backfill();
		delete_option( self::STATE_OPTION );
	}

	public function tear_down() {
		delete_option( self::STATE_OPTION );
		delete_option( self::VERSION_OPTION );
		parent::tear_down();
	}

	public function test_queues_the_backfill_for_an_existing_install() {
		update_option( self::VERSION_OPTION, '10.9.0' );

		$this->migration->maybe_migrate();

		$state = get_option( self::STATE_OPTION );
		$this->assertSame( 'pending', $state['status'] );

		// The platform's pages start at 1; asking for a page it does not have reads as an empty
		// scan, which is indistinguishable from having finished.
		$this->assertSame( 1, $state['page'] );

		$this->assertSame( 0, $state['attempts'] );
		$this->assertNotEmpty( $state['created_before'] );
	}

	public function test_does_nothing_for_fresh_install() {
		delete_option( self::VERSION_OPTION );

		$this->migration->maybe_migrate();

		$this->assertFalse( get_option( self::STATE_OPTION ) );
	}

	public function test_does_nothing_when_already_on_this_version() {
		update_option( self::VERSION_OPTION, '11.0.0' );

		$this->migration->maybe_migrate();

		$this->assertFalse( get_option( self::STATE_OPTION ) );
	}

	public function test_does_nothing_when_on_a_newer_version() {
		update_option( self::VERSION_OPTION, '11.2.0' );

		$this->migration->maybe_migrate();

		$this->assertFalse( get_option( self::STATE_OPTION ) );
	}

	/**
	 * A backfill already under way must not be rewound to page zero by a second upgrade.
	 */
	public function test_leaves_an_existing_state_untouched() {
		update_option( self::VERSION_OPTION, '10.9.0' );
		update_option(
			self::STATE_OPTION,
			[
				'status'         => 'pending',
				'page'           => 7,
				'created_before' => '2026-01-01 00:00:00',
				'attempts'       => 0,
			]
		);

		$this->migration->maybe_migrate();

		$state = get_option( self::STATE_OPTION );
		$this->assertSame( 7, $state['page'] );
	}

	/**
	 * Guards against the migration's option literal drifting from the canonical constant.
	 */
	public function test_option_name_matches_service_constant() {
		$this->assertSame(
			WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION,
			Dispute_Ledger_Backfill::STATE_OPTION
		);
	}
}
