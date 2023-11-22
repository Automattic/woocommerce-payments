<?php
/**
 * Class WC_Payments_Notes_Additional_Payment_Methods_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Notes_Additional_Payment_Methods tests.
 */
class WC_Payments_Notes_Additional_Payment_Methods_Test extends WCPAY_UnitTestCase {
	public function set_up() {
		parent::set_up();

		wp_set_current_user( 1 );
		update_option( '_wcpay_feature_upe_settings_preview', '1' );
		update_option( '_wcpay_feature_upe', '0' );
	}

	public function tear_down() {
		parent::tear_down();

		delete_option( '_wcpay_feature_upe_settings_preview' );
		delete_option( '_wcpay_feature_upe' );
	}

	public function test_maybe_enable_feature_flag_redirects_to_onboarding_when_account_not_connected() {
		$account_mock = $this->getMockBuilder( \WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'is_stripe_connected', 'redirect_to_onboarding_welcome_page' ] )->getMock();
		$account_mock->expects( $this->atLeastOnce() )->method( 'is_stripe_connected' )->will( $this->returnValue( false ) );
		$account_mock->expects( $this->once() )->method( 'redirect_to_onboarding_welcome_page' );
		WC_Payments_Notes_Additional_Payment_Methods::set_account( $account_mock );
		$_GET['page']   = 'wc-settings';
		$_GET['action'] = 'enable-upe';

		WC_Payments_Notes_Additional_Payment_Methods::maybe_enable_upe_feature_flag();

		$this->assertSame( '0', get_option( '_wcpay_feature_upe' ) );
	}
}
