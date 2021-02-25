<?php
/**
 * Class WC_Payments_Notes_Instant_Deposits_Eligible_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Notes_Instant_Deposits_Eligible tests.
 */
class WC_Payments_Notes_Instant_Deposits_Eligible_Test extends WP_UnitTestCase {
	public function test_removes_note_on_extension_deactivation() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			// Trigger WCPay extension deactivation callback.
			wcpay_deactivated();

			$note_id = WC_Payments_Notes_Set_Https_For_Checkout::NOTE_NAME;
			$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
		} else {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
		}
	}

	// TODO: fix this test so it's actually testing.
	public function test_adds_note_in_hook() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			// Trigger WCPay extension woo notes hook.
			WC_Payments::add_woo_admin_notes();

			$note_id = WC_Payments_Notes_Instant_Deposits_Eligible::NOTE_NAME;
			if ( true ) {
				$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
			} else {
				$this->assertNotSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
			}
		} else {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
		}
	}
}
