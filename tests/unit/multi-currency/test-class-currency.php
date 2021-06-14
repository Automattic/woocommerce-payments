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
			'{"code":"USD","rate":1,"name":"United States (US) dollar","id":"usd","is_default":true,"flag":"\ud83c\uddfa\ud83c\uddf8","symbol":"$"}',
			$json
		);
	}

	public function test_should_decode_entities_when_serialized() {
		$json = wp_json_encode( new WCPay\Multi_Currency\Currency( 'WST' ) );

		$this->assertSame(
			'{"code":"WST","rate":1,"name":"Samoan t\u0101l\u0101","id":"wst","is_default":false,"flag":"\ud83c\uddfc\ud83c\uddf8","symbol":"T"}',
			$json
		);
	}
}
