<?php
/**
 * Class WCPay_Multi_Currency_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Multi_Currency unit tests.
 */
class WCPay_Multi_Currency_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\Multi_Currency\Multi_Currency instance.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency
	 */
	private $multi_currency;

	public function setUp() {
		parent::setUp();

		$this->multi_currency = WCPay\Multi_Currency\Multi_Currency::instance();
	}

	public function tearDown() {
		WC()->session->__unset( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY );

		parent::tearDown();
	}

	public function test_get_selected_currency_returns_default_currency_for_empty_session() {
		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_currency_from_session() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( 'GBP', $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_parameter_not_set() {
		$this->multi_currency->update_selected_currency_by_url();

		$this->assertNull( WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_currency_not_enabled() {
		$_GET['currency'] = 'BIF';

		$this->multi_currency->update_selected_currency_by_url();

		$this->assertNull( WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_updates_session_when_currency_is_enabled() {
		$_GET['currency'] = 'GBP';

		$this->multi_currency->update_selected_currency_by_url();

		$this->assertSame( 'GBP', WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}
}
