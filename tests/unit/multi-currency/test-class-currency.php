<?php
/**
 * Class WCPay_Multi_Currency_Currency_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;

/**
 * WCPay\MultiCurrency\Currency unit tests.
 */
class WCPay_Multi_Currency_Currency_Tests extends WCPAY_UnitTestCase {
	/**
	 * Currency instance.
	 *
	 * @var Currency
	 */
	private $currency;

	/**
	 * @var int
	 */
	private $timestamp_for_testing;

	/**
	 * WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $localization_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->timestamp_for_testing = strtotime( '1 January 2021' );
		$this->localization_service  = new WC_Payments_Localization_Service();
		$this->currency              = new Currency( $this->localization_service, 'USD', 1.0, $this->timestamp_for_testing );
	}

	public function test_should_include_all_data_when_serialized() {
		$json = wp_json_encode( $this->currency );

		$this->assertSame(
			'{"code":"USD","rate":1,"name":"United States (US) dollar","id":"usd","is_default":true,"flag":"\ud83c\uddfa\ud83c\uddf8","symbol":"$","symbol_position":"left","is_zero_decimal":false,"last_updated":' . $this->timestamp_for_testing . '}',
			$json
		);
	}

	public function test_should_decode_entities_when_serialized() {
		$json = wp_json_encode( new Currency( $this->localization_service, 'WST' ) );

		$this->assertSame(
			'{"code":"WST","rate":1,"name":"Samoan t\u0101l\u0101","id":"wst","is_default":false,"flag":"\ud83c\uddfc\ud83c\uddf8","symbol":"T","symbol_position":"left","is_zero_decimal":false,"last_updated":null}',
			$json
		);
	}

	public function test_is_zero_decimal_returns_right_value() {
		$decimal_currency      = new Currency( $this->localization_service, 'USD' );
		$zero_decimal_currency = new Currency( $this->localization_service, 'BIF' );

		$this->assertFalse( $decimal_currency->get_is_zero_decimal() );
		$this->assertTrue( $zero_decimal_currency->get_is_zero_decimal() );
	}
}
