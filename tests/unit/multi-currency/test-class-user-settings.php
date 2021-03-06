<?php
/**
 * Class WCPay_Multi_Currency_User_Settings_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;

/**
 * WCPay\MultiCurrency\UserSettings unit tests.
 */
class WCPay_Multi_Currency_User_Settings_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\MultiCurrency\UserSettings instance.
	 *
	 * @var WCPay\MultiCurrency\UserSettings
	 */
	private $user_settings;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn(
				[
					new Currency( 'USD' ),
					new Currency( 'GBP' ),
					new Currency( 'EUR' ),
				]
			);

		$this->user_settings = new WCPay\MultiCurrency\UserSettings( $this->mock_multi_currency );
	}

	public function test_add_presentment_currency_switch_renders_markup() {
		$this->user_settings->add_presentment_currency_switch();
		$this->expectOutputRegex( '/<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">/' );
		$this->expectOutputRegex( '/<label for="wcpay_selected_currency">Default currency<\/label>/' );
		$this->expectOutputRegex( '/<select.+name="wcpay_selected_currency"/s' );
		$this->expectOutputRegex( '/<span><em>Select your preferred currency for shopping and payments.<\/em><\/span>/' );
		$this->expectOutputRegex( '/<div class="clear"><\/div>/' );
	}

	public function test_add_presentment_currency_switch_renders_enabled_currencies() {
		$this->user_settings->add_presentment_currency_switch();
		$this->expectOutputRegex( '/<option value="USD">&#36; USD<\/option>/' );
		$this->expectOutputRegex( '/<option value="GBP">&pound; GBP<\/option>/' );
		$this->expectOutputRegex( '/<option value="EUR">&euro; EUR<\/option>/' );
	}

	public function test_add_presentment_currency_switch_selects_selected_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( 'EUR' ) );
		$this->user_settings->add_presentment_currency_switch();
		$this->expectOutputRegex( '/<option value="USD">&#36; USD<\/option>/' );
		$this->expectOutputRegex( '/<option value="GBP">&pound; GBP<\/option>/' );
		$this->expectOutputRegex( '/<option value="EUR" selected>&euro; EUR<\/option>/' );
	}

	public function test_save_presentment_currency() {
		$_POST['wcpay_selected_currency'] = 'GBP';
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'update_selected_currency' )
			->with( 'GBP' );
		$this->user_settings->save_presentment_currency();
	}
}
