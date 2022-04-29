<?php
/**
 * Class WC_Payments_Captured_Event_Note_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Captured_Event_Note_Test unit tests.
 */
class WC_Payments_Captured_Event_Note_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Captured_Event_Note
	 */
	private $captured_event_note;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$input_fx_payment = wp_json_file_decode( dirname( __FILE__ ) . '/stubs/timeline-decimal-fx-payments.json', [ 'associative' => true ] );

		$capture_event = current(
			array_filter(
				$input_fx_payment['data'],
				function ( array $event ) {
					return 'captured' === $event['type'];
				}
			)
		);

		$this->captured_event_note = new WC_Payments_Captured_Event_Note( $capture_event );

	}

	public function test_decimal_fx_payments() {
		$this->assertTrue( $this->captured_event_note->is_fx_event() );
		$this->assertSame( '1 VND → 0.000044 USD: $100.04 USD', $this->captured_event_note->compose_fx_string() );
	}
}
