<?php
/**
 * Class WC_Payments_Notes_Set_Up_Refund_Policy_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Notes_Set_Up_Refund_Policy tests.
 */
class WC_Payments_Notes_Set_Up_Refund_Policy_Test extends WP_UnitTestCase {
	public function test_removes_note_on_extension_deactivation() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			// Trigger WCPay extension deactivation callback.
			wcpay_deactivated();

			$note_id = WC_Payments_Notes_Set_Up_Refund_Policy::NOTE_NAME;
			$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
		} else {
			$this->markTestSkipped( 'The used WC components are not forward compatible' );
		}
	}

	public function test_adds_note_on_extension_activation() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			// Trigger WCPay extension activation callback.
			wcpay_activated();

			$note_id = WC_Payments_Notes_Set_Up_Refund_Policy::NOTE_NAME;
			$this->assertNotSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
		} else {
			$this->markTestSkipped( 'The used WC components are not forward compatible' );
		}
	}
}
