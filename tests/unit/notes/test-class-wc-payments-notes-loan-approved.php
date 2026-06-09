<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Class WC_Payments_Notes_Loan_Approved_Test
 *
 * @package WooCommerce\Payments\Tests
 */

require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-loan-approved.php';

/**
 * Class WC_Payments_Notes_Loan_Approved_Test
 */
class WC_Payments_Notes_Loan_Approved_Test extends WCPAY_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
		}
	}

	public function test_get_note_generates_content_with_formatted_price() {
		WC_Payments_Notes_Loan_Approved::set_loan_details(
			[
				'details' => [
					'advance_amount'      => 1234567,
					'advance_paid_out_at' => time(),
					'currency'            => 'USD',
				],
			]
		);

		$note = WC_Payments_Notes_Loan_Approved::get_note();

		$this->assertInstanceOf( \Automattic\WooCommerce\Admin\Notes\Note::class, $note );
		$this->assertStringContainsString( 'capital loan has been approved', $note->get_content() );
		$this->assertEquals( 'wc-payments-notes-loan-approved', $note->get_name() );
	}
}
