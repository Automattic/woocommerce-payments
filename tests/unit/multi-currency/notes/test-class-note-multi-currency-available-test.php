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

	public function test_get_note() {
		$note = NoteMultiCurrencyAvailable::get_note();

		$this->assertSame( 'Sell worldwide in multiple currencies', $note->get_title() );
		$this->assertSame( 'Boost your international sales by allowing your customers to shop and pay in their local currency.', $note->get_content() );
		$this->assertSame( 'info', $note->get_type() );
		$this->assertSame( 'wc-payments-notes-multi-currency-available', $note->get_name() );
		$this->assertSame( 'woocommerce-payments', $note->get_source() );

		list( $actions ) = $note->get_actions();
		$this->assertSame( 'wc-payments-notes-multi-currency-available', $actions->name );
		$this->assertSame( 'Set up now', $actions->label );
		$this->assertStringStartsWith( 'admin.php?page=wc-admin&path=/payments/multi-currency-setup', $actions->query );
		$this->assertSame( true, $actions->primary );
	}


	public function test_possibly_add_note_without_account() {
		NoteMultiCurrencyAvailable::possibly_add_note();

		$this->assertSame( false, NoteMultiCurrencyAvailable::note_exists() );
	}

	public function test_possibly_add_note_with_account_not_connected() {
		$account_mock = $this->createMock( WC_Payments_Account::class );
		$account_mock->method( 'is_stripe_connected' )->willReturn( false );
		NoteMultiCurrencyAvailable::set_account( $account_mock );

		NoteMultiCurrencyAvailable::possibly_add_note();

		$this->assertSame( false, NoteMultiCurrencyAvailable::note_exists() );
	}

	public function test_possibly_add_note_with_connected_account() {
		$account_mock = $this->createMock( WC_Payments_Account::class );
		$account_mock->method( 'is_stripe_connected' )->willReturn( true );
		NoteMultiCurrencyAvailable::set_account( $account_mock );

		NoteMultiCurrencyAvailable::possibly_add_note();

		$this->assertSame( true, NoteMultiCurrencyAvailable::note_exists() );
	}
}
