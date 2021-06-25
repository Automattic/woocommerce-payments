<?php
/**
 * Class WCPay_Multi_Currency_Utils_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Utils unit tests.
 */
class WCPay_Multi_Currency_Utils_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\Multi_Currency\Utils instance.
	 *
	 * @var WCPay\Multi_Currency\Utils
	 */
	private $utils;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->utils = new WCPay\Multi_Currency\Utils();
	}

	public function tearDown() {
		wp_set_current_user( 0 );
		remove_all_filters( 'locale' );
	}

	public function test_is_call_in_backtrace_return_false() {
		$this->assertFalse( $this->utils->is_call_in_backtrace( [ 'test' ] ) );
	}

	public function test_is_call_in_backtrace_return_true() {
		$this->assertTrue( $this->utils->is_call_in_backtrace( [ 'WCPay_Multi_Currency_Utils_Tests->test_is_call_in_backtrace_return_true' ] ) );
	}

	public function test_get_user_locale_country_returns_default_locale_country() {
		$this->assertSame( 'US', $this->utils->get_user_locale_country() );
	}

	public function test_get_user_locale_country_returns_filtered_locale_country() {
		$this->mock_locale( 'pt_BR' );

		$this->assertSame( 'BR', $this->utils->get_user_locale_country() );
	}

	public function test_get_user_locale_country_returns_user_locale_country() {
		$this->mock_locale( 'pt_BR' ); // Make sure filtered locale is ignored.

		wp_set_current_user( 1 );
		wp_get_current_user()->locale = 'en_GB';

		$this->assertSame( 'GB', $this->utils->get_user_locale_country() );
	}

	private function mock_locale( $locale ) {
		add_filter(
			'locale',
			function () use ( $locale ) {
				return $locale;
			}
		);
	}
}
