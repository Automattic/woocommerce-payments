<?php
/**
 * Class WC_Payments_Notes_Stripe_Billing_Deprecation_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Notes_Stripe_Billing_Deprecation tests.
 */
class WC_Payments_Notes_Stripe_Billing_Deprecation_Test extends WCPAY_UnitTestCase {
	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-stripe-billing-deprecation.php';
	}

	/**
	 * Tests for WC_Payments_Notes_Stripe_Billing_Deprecation::get_note()
	 */
	public function test_get_note() {
		$note = WC_Payments_Notes_Stripe_Billing_Deprecation::get_note();

		$this->assertInstanceOf( 'Automattic\WooCommerce\Admin\Notes\Note', $note );
		$this->assertEquals( 'Built-in subscriptions functionality has been removed. Here\'s what to do', $note->get_title() );
		$this->assertEquals( 'To continue offering subscriptions and gain access to your data, please install WooCommerce Subscriptions. WooPayments no longer supports this feature.', $note->get_content() );
		$this->assertEquals( 'info', $note->get_type() );
		$this->assertEquals( 'wc-payments-notes-stripe-billing-deprecation', $note->get_name() );
		$this->assertEquals( 'woocommerce-payments', $note->get_source() );

		$actions = $note->get_actions();
		$this->assertCount( 1, $actions );
		$this->assertEquals( 'Install WooCommerce Subscriptions', $actions[0]->label );
		$this->assertEquals( 'https://woocommerce.com/products/woocommerce-subscriptions/', $actions[0]->query );
	}
}
