<?php
/**
 * Class Note_Multi_Currency_Available_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Notes\NoteMultiCurrencyAvailable;

/**
 * Class Note_Multi_Currency_Available_Test tests.
 */
class Note_Multi_Currency_Available_Test extends WP_UnitTestCase {
	public function test_removes_note_on_extension_deactivation() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			// Trigger WCPay extension deactivation callback.
			wcpay_multi_currency_deactivated();

			$note_id = NoteMultiCurrencyAvailable::NOTE_NAME;
			$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
		} else {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
		}
	}
}
