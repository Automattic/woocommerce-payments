<?php
/**
 * Class WCPay_Multi_Currency_Admin_Notices_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\AdminNotices unit tests.
 */
class WCPay_Multi_Currency_Admin_Notices_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\MultiCurrency\AdminNotices instance.
	 *
	 * @var WCPay\MultiCurrency\AdminNotices
	 */
	private $admin_notices;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->admin_notices = new WCPay\MultiCurrency\AdminNotices();
	}

	public function test_admin_notices_displays_currency_changed_notice() {
		wp_set_current_user( 1 );

		$currencies = [ 'Canadian dollar', 'Euro', 'Monopoly money' ];
		update_option( 'wcpay_multi_currency_show_store_currency_changed_notice', $currencies );
		$regex = '/The following currencies are set to manual rates and may need updates: Canadian dollar, Euro, Monopoly money/';

		$this->admin_notices->admin_notices();
		$this->expectOutputRegex( $regex );
	}

	public function test_admin_notices_hides_currency_changed_notice() {
		wp_set_current_user( 1 );

		$currencies = [ 'Canadian dollar', 'Euro', 'Monopoly money' ];
		update_option( 'wcpay_multi_currency_show_store_currency_changed_notice', $currencies );
		$_GET['_wcpay_multi_currency_notice_nonce'] = wp_create_nonce( 'wcpay_multi_currency_hide_notices_nonce' );
		$_GET['wcpay-multi-currency-hide-notice']   = 'currency_changed';

		$this->admin_notices->hide_notices();
		$this->admin_notices->admin_notices();
		$this->expectOutputString( '' );
	}
}
