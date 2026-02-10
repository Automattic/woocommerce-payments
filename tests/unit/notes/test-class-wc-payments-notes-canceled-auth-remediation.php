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
		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-canceled-auth-remediation.php';

		// Clean up any existing options.
		delete_option( 'wcpay_fee_remediation_status' );
		delete_option( 'wcpay_has_affected_auth_fee_orders' );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		delete_option( 'wcpay_fee_remediation_status' );
		delete_option( 'wcpay_has_affected_auth_fee_orders' );
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
	 * Tests that can_be_added returns false when cached result indicates no affected orders.
	 */
	public function test_can_be_added_returns_false_when_no_affected_orders_cached() {
		update_option( 'wcpay_has_affected_auth_fee_orders', 0 );

		$result = WC_Payments_Notes_Canceled_Auth_Remediation::can_be_added();

		$this->assertFalse( $result );
	}

	/**
	 * Tests that has_affected_orders caches the result after the first query.
	 */
	public function test_has_affected_orders_sets_option_after_query() {
		// Option should not exist initially.
		$this->assertFalse( get_option( 'wcpay_has_affected_auth_fee_orders' ) );

		// Calling can_be_added triggers has_affected_orders, which should cache the result.
		WC_Payments_Notes_Canceled_Auth_Remediation::can_be_added();

		// The option should now be set (0 since there are no affected orders in the test DB).
		$this->assertNotFalse( get_option( 'wcpay_has_affected_auth_fee_orders' ) );
	}

	/**
	 * Tests that the cached option distinguishes between 0 (no affected orders) and false (not yet checked).
	 */
	public function test_cached_zero_is_not_treated_as_unchecked() {
		update_option( 'wcpay_has_affected_auth_fee_orders', 0 );

		// Should return false without running the query — the "completed" and "running"
		// checks pass, but has_affected_orders returns false from cache.
		$result = WC_Payments_Notes_Canceled_Auth_Remediation::can_be_added();

		$this->assertFalse( $result );
		// Option should remain 0 (not re-queried and overwritten).
		$this->assertEquals( 0, get_option( 'wcpay_has_affected_auth_fee_orders' ) );
	}
}
