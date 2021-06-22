<?php
/**
 * Class WC_Payments_Notes_Additional_Payment_Methods_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Admin\Notes\Note;

/**
 * Class WC_Payments_Notes_Additional_Payment_Methods tests.
 */
class WC_Payments_Notes_Additional_Payment_Methods_Test extends WP_UnitTestCase {
	public function test_get_note() {
		$note = WC_Payments_Notes_Additional_Payment_Methods::get_note();
		$this->assertSame( 'Boost your sales by accepting new payment methods', $note->get_title() );
		$this->assertSame( 'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments.', $note->get_content() );
		$this->assertSame( Note::E_WC_ADMIN_NOTE_INFORMATIONAL, $note->get_type() );
		$this->assertSame( 'wc-payments-notes-additional-payment-methods', $note->get_name() );
		$this->assertSame( 'woocommerce-payments', $note->get_source() );

		[$enable_upe_action, $learn_more_action] = $note->get_actions();
		$this->assertSame( 'wc-payments-notes-additional-payment-methods', $enable_upe_action->name );
		$this->assertSame( 'Enable on your store', $enable_upe_action->label );
		$this->assertSame( 'http://example.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&action=enable-upe', $enable_upe_action->query );
		$this->assertSame( true, $enable_upe_action->primary );

		$this->assertSame( 'learn-more', $learn_more_action->name );
		$this->assertSame( 'Learn more', $learn_more_action->label );
		$this->assertSame( 'https://docs.woocommerce.com/document/payments/', $learn_more_action->query );
		$this->assertSame( false, $learn_more_action->primary );
	}
}
