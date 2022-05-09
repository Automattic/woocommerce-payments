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
	 * @dataProvider provider
	 */
	public function test_strings_for_captured_event( array $captured_event, array $expecation ) {
		$this->captured_event_note = new WC_Payments_Captured_Event_Note( $captured_event );
		$this->assertSame( $expecation['fxString'] ?? null, $this->captured_event_note->compose_fx_string() );
		$this->assertSame( $expecation['feeString'], $this->captured_event_note->compose_fee_string() );

	}

	public function provider() {

		$res   = [];
		$files = glob( dirname( __FILE__, 2 ) . '/fixtures/captured-payments/*.json' );
		foreach ( $files as $file ) {
			$array_from_file = wp_json_file_decode( $file, [ 'associative' => true ] );
			$title           = $array_from_file['title'];
			$captured_event  = $array_from_file['capturedEvent'];
			$expectation     = $array_from_file['expectation'];

			$res[ $title ] = [ $captured_event, $expectation ];
		}

		return $res;
	}
}
