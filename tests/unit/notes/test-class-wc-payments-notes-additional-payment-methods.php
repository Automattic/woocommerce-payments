<?php
/**
 * Class WC_Payments_Notes_Additional_Payment_Methods_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Notes_Additional_Payment_Methods tests.
 */
class WC_Payments_Notes_Additional_Payment_Methods_Test extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		wp_set_current_user( 1 );
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
		$this->assertSame( 'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments. <a href="https://woocommerce.com/document/payments/additional-payment-methods/" target="wcpay_upe_learn_more">Learn more</a>', $note->get_content() );
		$this->assertSame( 'info', $note->get_type() );
		$this->assertSame( 'wc-payments-notes-additional-payment-methods', $note->get_name() );
		$this->assertSame( 'woocommerce-payments', $note->get_source() );

		list( $enable_upe_action ) = $note->get_actions();
		$this->assertSame( 'wc-payments-notes-additional-payment-methods', $enable_upe_action->name );
		$this->assertSame( 'Enable on your store', $enable_upe_action->label );
		$this->assertStringStartsWith( 'http://example.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&action=enable-upe', $enable_upe_action->query );
		$this->assertSame( true, $enable_upe_action->primary );
	}

	public function test_get_note_does_not_return_note_when_account_is_not_connected() {
		$account_mock = $this->getMockBuilder( \WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'is_stripe_connected' ] )->getMock();
		$account_mock->expects( $this->atLeastOnce() )->method( 'is_stripe_connected' )->will( $this->returnValue( false ) );
		WC_Payments_Notes_Additional_Payment_Methods::set_account( $account_mock );

		$note = WC_Payments_Notes_Additional_Payment_Methods::get_note();

		$this->assertNull( $note );
	}

	public function test_get_note_returns_note_when_account_is_connected() {
		$account_mock = $this->getMockBuilder( \WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'is_stripe_connected' ] )->getMock();
		$account_mock->expects( $this->atLeastOnce() )->method( 'is_stripe_connected' )->will(
			$this->returnValue(
				true
			)
		);
		WC_Payments_Notes_Additional_Payment_Methods::set_account( $account_mock );

		$note = WC_Payments_Notes_Additional_Payment_Methods::get_note();

		$this->assertSame( 'Boost your sales by accepting new payment methods', $note->get_title() );
	}

	public function test_maybe_enable_feature_flag_redirects_to_onboarding_when_account_not_connected() {
		$account_mock = $this->getMockBuilder( \WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'is_stripe_connected', 'redirect_to_onboarding_page' ] )->getMock();
		$account_mock->expects( $this->atLeastOnce() )->method( 'is_stripe_connected' )->will( $this->returnValue( false ) );
		$account_mock->expects( $this->once() )->method( 'redirect_to_onboarding_page' );
		WC_Payments_Notes_Additional_Payment_Methods::set_account( $account_mock );
		$_GET['page']   = 'wc-settings';
		$_GET['action'] = 'enable-upe';

		WC_Payments_Notes_Additional_Payment_Methods::maybe_enable_upe_feature_flag();

		$this->assertSame( '0', get_option( '_wcpay_feature_upe' ) );
	}
}
