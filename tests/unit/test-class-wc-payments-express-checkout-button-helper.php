<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Button_Helper.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Session_Rate_Limiter;

/**
 * WC_Payments_Payment_Request_Button_Handler_Test class.
 */
class WC_Payments_Express_Checkout_Button_Helper_Test extends WCPAY_UnitTestCase {
	/**
	 * Used to get the settings.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_wcpay_gateway;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();

		$this->mock_express_checkout_helper = new WC_Payments_Express_Checkout_Button_Helper( $this->mock_wcpay_gateway, $this->mock_wcpay_account );
	}

	public function tear_down() {
		parent::tear_down();
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_false' );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_excl' ] );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] );
	}

	public function __return_excl() {
		return 'excl';
	}

	public function __return_incl() {
		return 'incl';
	}

	public function __return_base() {
		return 'base';
	}

	/**
	 * @return WC_Payment_Gateway_WCPay
	 */
	private function make_wcpay_gateway() {
		$mock_api_client               = $this->createMock( WC_Payments_API_Client::class );
		$mock_customer_service         = $this->createMock( WC_Payments_Customer_Service::class );
		$mock_token_service            = $this->createMock( WC_Payments_Token_Service::class );
		$mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$mock_rate_limiter             = $this->createMock( Session_Rate_Limiter::class );
		$mock_order_service            = $this->createMock( WC_Payments_Order_Service::class );
		$mock_dpps                     = $this->createMock( Duplicate_Payment_Prevention_Service::class );
		$mock_payment_method           = $this->createMock( CC_Payment_Method::class );

		return new WC_Payment_Gateway_WCPay(
			$mock_api_client,
			$this->mock_wcpay_account,
			$mock_customer_service,
			$mock_token_service,
			$mock_action_scheduler_service,
			$mock_payment_method,
			[ 'card' => $mock_payment_method ],
			$mock_rate_limiter,
			$mock_order_service,
			$mock_dpps,
			$this->createMock( WC_Payments_Localization_Service::class ),
			$this->createMock( WC_Payments_Fraud_Service::class )
		);
	}

	public function test_common_get_button_settings() {
		$this->assertEquals(
			[
				'type'   => 'buy',
				'theme'  => 'dark',
				'height' => '48',
			],
			$this->mock_express_checkout_helper->get_common_button_settings()
		);
	}

	public function test_cart_prices_include_tax_with_tax_disabled() {
		add_filter( 'wc_tax_enabled', '__return_false' );
		$this->assertTrue( $this->mock_express_checkout_helper->cart_prices_include_tax() );
	}

	public function test_cart_prices_include_tax_with_tax_enabled_and_display_incl() {
		add_filter( 'wc_tax_enabled', '__return_true' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] ); // reset in tear_down.

		$this->assertTrue( $this->mock_express_checkout_helper->cart_prices_include_tax() );
	}

	public function test_cart_prices_include_tax_with_tax_enabled_and_display_excl() {
		add_filter( 'wc_tax_enabled', '__return_true' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_excl' ] ); // reset in tear_down.

		$this->assertFalse( $this->mock_express_checkout_helper->cart_prices_include_tax() );
	}

	public function test_get_total_label() {
		$this->mock_wcpay_account->method( 'get_statement_descriptor' )
			->willReturn( 'Google Pay' );

		$result = $this->mock_express_checkout_helper->get_total_label();

		$this->assertEquals( 'Google Pay (via WooCommerce)', $result );
	}

	public function test_get_total_label_with_filter() {
		$this->mock_wcpay_account->method( 'get_statement_descriptor' )
			->willReturn( 'Google Pay' );

		add_filter(
			'wcpay_payment_request_total_label_suffix',
			function() {
				return ' (via WooPayments)';
			}
		);

		$result = $this->mock_express_checkout_helper->get_total_label();

		$this->assertEquals( 'Google Pay (via WooPayments)', $result );
	}
}
