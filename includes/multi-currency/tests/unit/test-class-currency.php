<?php
/**
 * Class WCPay_Multi_Currency_Currency_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Currency unit tests.
 */
class WCPay_Multi_Currency_Currency_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\Multi_Currency\Currency instance.
	 *
	 * @var WCPay\Multi_Currency\Currency
	 */
	private $currency;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->currency = new WCPay\Multi_Currency\Currency( 'USD' );
	}

	public function test_should_include_all_data_when_serialized() {
		$json = wp_json_encode( $this->currency );

		$this->assertSame(
			'{"code":"USD","rate":1,"name":"United States (US) dollar","id":"usd","flag":"","symbol":"&#36;"}',
			$json
		);
	}
}
