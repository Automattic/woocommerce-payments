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
	public function setUp() {
		parent::setUp();

		update_option( '_wcpay_feature_upe_settings_preview', '1' );
		update_option( '_wcpay_feature_upe', '0' );
	}

	public function tearDown() {
		parent::tearDown();

		delete_option( '_wcpay_feature_upe_settings_preview' );
		delete_option( '_wcpay_feature_upe' );
	}


	public function test_get_note() {
		$note = WC_Payments_Notes_Additional_Payment_Methods::get_note();

		$this->assertSame( 'Boost your sales by accepting new payment methods', $note->get_title() );
		$this->assertSame( 'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments. <a href="https://docs.woocommerce.com/document/payments/additional-payment-methods/" target="wcpay_upe_learn_more">Learn more</a>', $note->get_content() );
		$this->assertSame( 'info', $note->get_type() );
		$this->assertSame( 'wc-payments-notes-additional-payment-methods', $note->get_name() );
		$this->assertSame( 'woocommerce-payments', $note->get_source() );

		list( $enable_upe_action ) = $note->get_actions();
		$this->assertSame( 'wc-payments-notes-additional-payment-methods', $enable_upe_action->name );
		$this->assertSame( 'Enable on your store', $enable_upe_action->label );
		$this->assertStringStartsWith( 'http://example.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&action=enable-upe', $enable_upe_action->query );
		$this->assertSame( true, $enable_upe_action->primary );
	}
}
