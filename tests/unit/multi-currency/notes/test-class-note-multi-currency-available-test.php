<?php
/**
 * Class Note_Multi_Currency_Available_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Notes\NoteMultiCurrencyAvailable;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyAccountInterface;

/**
 * Class Note_Multi_Currency_Available_Test tests.
 */
class Note_Multi_Currency_Available_Test extends WCPAY_UnitTestCase {
	/**
	 * The account the note class held before the test.
	 *
	 * @var MultiCurrencyAccountInterface|null
	 */
	private $original_account;

	public function set_up() {
		parent::set_up();

		$this->original_account = $this->get_account_property()->getValue();
	}

	public function tear_down() {
		$this->get_account_property()->setValue( null, $this->original_account );

		parent::tear_down();
	}

	public function test_removes_note_on_extension_deactivation() {
		// Trigger WCPay extension deactivation callback.
		wcpay_multi_currency_deactivated();

		$note_id = NoteMultiCurrencyAvailable::NOTE_NAME;
		$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
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

		/**
		 * The $primary property was deprecated from WooCommerce core. Keeping this to maintain the compatibility with old WooCommerce versions.
		 * @see https://github.com/woocommerce/woocommerce/blob/ff2d7d704a8f72aeb4990811b6972097aa167bea/plugins/woocommerce/src/Admin/Notes/Note.php#L623-L623.
		 * @see https://github.com/woocommerce/woocommerce-admin/pull/8474
		 */
		if ( isset( $actions->primary ) ) {
			$this->assertSame( true, $actions->primary );
		}
	}


	public function test_possibly_add_note_without_account() {
		NoteMultiCurrencyAvailable::possibly_add_note();

		$this->assertSame( false, NoteMultiCurrencyAvailable::note_exists() );
	}

	public function test_possibly_add_note_with_account_not_connected() {
		$account_mock = $this->createMock( MultiCurrencyAccountInterface::class );
		$account_mock->method( 'is_provider_connected' )->willReturn( false );
		NoteMultiCurrencyAvailable::set_account( $account_mock );

		NoteMultiCurrencyAvailable::possibly_add_note();

		$this->assertSame( false, NoteMultiCurrencyAvailable::note_exists() );
	}

	public function test_possibly_add_note_with_connected_account() {
		$account_mock = $this->createMock( MultiCurrencyAccountInterface::class );
		$account_mock->method( 'is_provider_connected' )->willReturn( true );
		NoteMultiCurrencyAvailable::set_account( $account_mock );

		NoteMultiCurrencyAvailable::possibly_add_note();

		$this->assertSame( true, NoteMultiCurrencyAvailable::note_exists() );
	}

	private function get_account_property(): ReflectionProperty {
		$property = new ReflectionProperty( NoteMultiCurrencyAvailable::class, 'account' );
		$property->setAccessible( true );

		return $property;
	}
}
