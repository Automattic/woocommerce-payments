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
	 * Tests for WC_Payments_Notes_Stripe_Billing_Deprecation::can_be_added()
	 */
	public function test_cannot_be_added() {
		// Test when Stripe Billing is enabled and WooCommerce Subscriptions is not active.
		$this->set_feature_flag_option( WC_Payments_Features::STRIPE_BILLING_FLAG_NAME, '1' );
		$this->assertFalse( WC_Payments_Notes_Stripe_Billing_Deprecation::can_be_added() );

		// Test when Stripe Billing is disabled.
		$this->set_feature_flag_option( WC_Payments_Features::STRIPE_BILLING_FLAG_NAME, '0' );
		$this->assertFalse( WC_Payments_Notes_Stripe_Billing_Deprecation::can_be_added() );

		// Test when WooCommerce Subscriptions is active.
		$this->set_feature_flag_option( WC_Payments_Features::STRIPE_BILLING_FLAG_NAME, '1' );
		add_filter( 'pre_option_wc_subscriptions_active', '__return_true' );
		$this->assertFalse( WC_Payments_Notes_Stripe_Billing_Deprecation::can_be_added() );
		remove_filter( 'pre_option_wc_subscriptions_active', '__return_true' );
	}

	/**
	 * Tests for WC_Payments_Notes_Stripe_Billing_Deprecation::get_note()
	 */
	public function test_get_note() {
		$title   = 'Important information regarding subscriptions in WooPayments';
		$content = 'From version 9.7 of WooPayments (scheduled for 23 July, 2025), you\'ll no longer be able to offer new product subscriptions using the built-in subscriptions functionality. To avoid disruption, please install WooCommerce Subscriptions for free.';

		$note = WC_Payments_Notes_Stripe_Billing_Deprecation::get_note();

		$this->assertInstanceOf( 'Automattic\WooCommerce\Admin\Notes\Note', $note );
		$this->assertEquals( $title, $note->get_title() );
		$this->assertEquals( $content, $note->get_content() );
		$this->assertEquals( 'info', $note->get_type() );
		$this->assertEquals( 'wc-payments-notes-stripe-billing-deprecation', $note->get_name() );
		$this->assertEquals( 'woocommerce-payments', $note->get_source() );

		$actions = $note->get_actions();
		$this->assertCount( 1, $actions );
		$this->assertEquals( 'Install WooCommerce Subscriptions', $actions[0]->label );
		$this->assertEquals( 'https://woocommerce.com/products/woocommerce-subscriptions/', $actions[0]->query );
	}

	/**
	 * Set a feature flag option value.
	 *
	 * @param string $option The option name.
	 * @param string $value  The option value.
	 */
	private function set_feature_flag_option( string $option, string $value ) {
		add_filter(
			'pre_option_' . $option,
			function () use ( $value ) {
				return $value;
			}
		);
	}
}
