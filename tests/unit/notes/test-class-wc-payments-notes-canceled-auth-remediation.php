<?php
/**
 * Class WC_Payments_Notes_Canceled_Auth_Remediation_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Notes_Canceled_Auth_Remediation tests.
 */
class WC_Payments_Notes_Canceled_Auth_Remediation_Test extends WCPAY_UnitTestCase {
	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		require_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-canceled-auth-remediation.php';

		// Clean up any existing options.
		delete_option( 'wcpay_fee_remediation_status' );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		delete_option( 'wcpay_fee_remediation_status' );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_AFFECTED_ORDERS_HOOK );
		}

		parent::tear_down();
	}

	/**
	 * Tests for WC_Payments_Notes_Canceled_Auth_Remediation::get_note()
	 */
	public function test_get_note() {
		$note = WC_Payments_Notes_Canceled_Auth_Remediation::get_note();

		$this->assertInstanceOf( 'Automattic\WooCommerce\Admin\Notes\Note', $note );
		$this->assertEquals( 'WooPayments: Fix incorrect order data', $note->get_title() );
		$this->assertStringContainsString( 'canceled payment authorizations', $note->get_content() );
		$this->assertStringContainsString( 'negative values', $note->get_content() );
		$this->assertEquals( 'warning', $note->get_type() );
		$this->assertEquals( 'wc-payments-notes-canceled-auth-remediation', $note->get_name() );
		$this->assertEquals( 'woocommerce-payments', $note->get_source() );

		$actions = $note->get_actions();
		$this->assertCount( 1, $actions );
		$this->assertEquals( 'Go to Tools page', $actions[0]->label );
		$this->assertStringContainsString( 'wc-status&tab=tools', $actions[0]->query );
	}

	/**
	 * Tests that note cannot be added when remediation is complete.
	 */
	public function test_can_be_added_returns_false_when_complete() {
		update_option( 'wcpay_fee_remediation_status', 'completed' );

		$result = WC_Payments_Notes_Canceled_Auth_Remediation::can_be_added();

		$this->assertFalse( $result );
	}

	/**
	 * Tests that can_be_added returns false when no affected orders were found.
	 */
	public function test_can_be_added_returns_false_when_no_affected_orders() {
		update_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY, 'no_affected_orders' );

		$result = WC_Payments_Notes_Canceled_Auth_Remediation::can_be_added();

		$this->assertFalse( $result );
	}

	/**
	 * Tests that can_be_added returns false while async check is scheduled.
	 */
	public function test_can_be_added_returns_false_when_scheduled() {
		update_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY, 'scheduled' );

		$result = WC_Payments_Notes_Canceled_Auth_Remediation::can_be_added();

		$this->assertFalse( $result );
	}

	/**
	 * Tests that when no state exists, can_be_added schedules the async check and returns false.
	 */
	public function test_can_be_added_schedules_check_when_no_state() {
		$result = WC_Payments_Notes_Canceled_Auth_Remediation::can_be_added();

		$this->assertFalse( $result );
		$this->assertEquals(
			'scheduled',
			get_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY )
		);
		$this->assertTrue(
			as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_AFFECTED_ORDERS_HOOK )
		);
	}

	/**
	 * Tests that the async callback caches the query result.
	 */
	public function test_check_and_cache_sets_no_affected_orders() {
		$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();
		$remediation->check_and_cache_affected_orders();

		// No affected orders in the test DB.
		$this->assertEquals(
			'no_affected_orders',
			get_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY )
		);
	}

	/**
	 * Tests that init registers the Action Scheduler hook.
	 */
	public function test_init_registers_check_hook() {
		$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();
		$remediation->init();

		$this->assertNotFalse(
			has_action(
				WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_AFFECTED_ORDERS_HOOK,
				[ $remediation, 'check_and_cache_affected_orders' ]
			)
		);
	}
}
