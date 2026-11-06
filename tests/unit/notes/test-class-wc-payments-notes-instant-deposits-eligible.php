<?php
/**
 * Class WC_Payments_Notes_Instant_Deposits_Eligible_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Notes_Instant_Deposits_Eligible tests.
 */
class WC_Payments_Notes_Instant_Deposits_Eligible_Test extends WCPAY_UnitTestCase {
	public function test_removes_note_on_extension_deactivation() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
			$note_id = WC_Payments_Notes_Instant_Deposits_Eligible::NOTE_NAME;
			WC_Payments_Notes_Instant_Deposits_Eligible::get_note()->save();
			$this->assertNotSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );

			// Trigger WCPay extension deactivation callback.
			wcpay_deactivated();

			$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
		} else {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
		}
	}
}
