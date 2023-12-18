<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WC_Payments_UPE_Checkout;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Payment_Methods\UPE_Split_Payment_Gateway;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;

/**
 * Class WC_Payments_UPE_Checkout_Test
 *
 * @package WooCommerce\Payments\Tests
 */
class WC_Payments_UPE_Checkout_Test extends WP_UnitTestCase {

	/**
	 * Holds the object, which will be tested.
	 *
	 * @var WC_Payments_UPE_Checkout
	 */
	private $system_under_test;

	/**
	 * UPE_Split_Payment_Gateway instance.
	 *
	 * @var UPE_Split_Payment_Gateway|MockObject
	 */
	private $mock_wcpay_gateway;

	/**
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities|MockObject
	 */
	private $mock_woopay_utilities;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_wcpay_account;


	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock Fraud Service.
	 *
	 * @var WC_Payments_Fraud_Service|MockObject
	 */
	private $mock_fraud_service;

	/**
	 * Default gateway.
	 *
	 * @var UPE_Split_Payment_Gateway
	 */
	private $default_gateway;

	public function set_up() {
		parent::set_up();

		// Setup the gateway mock.
		$this->mock_wcpay_gateway     = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->onlyMethods( [ 'get_payment_method_ids_enabled_at_checkout', 'should_use_stripe_platform_on_checkout_page', 'should_support_saved_payments', 'is_saved_cards_enabled', 'save_payment_method_checkbox', 'get_account_statement_descriptor', 'get_icon_url', 'get_payment_method_ids_enabled_at_checkout_filtered_by_fees' ] )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_wcpay_gateway->id = 'woocommerce_payments';

		$this->mock_woopay_utilities = $this->createMock( WooPay_Utilities::class );
		$this->mock_woopay_utilities = $this->getMockBuilder( WooPay_Utilities::class )
			->onlyMethods( [ 'should_enable_woopay', 'should_enable_woopay_on_cart_or_checkout' ] )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_wcpay_account    = $this->createMock( WC_Payments_Account::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_fraud_service    = $this->createMock( WC_Payments_Fraud_Service::class );

		$this->mock_wcpay_gateway
			->method( 'get_account_statement_descriptor' )
			->willReturn( 'localhost' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout_filtered_by_fees' )
			->willReturn( [] );

		// This is needed to ensure that only the mocked gateway is always used by the checkout class.
		$this->default_gateway = WC_Payments::get_registered_card_gateway();
		WC_Payments::set_registered_card_gateway( $this->mock_wcpay_gateway );

		// Use a callback to suppresses the output buffering being printed to the CLI.
		$this->setOutputCallback(
			function ( $output ) {
				preg_match_all( '/.*<fieldset.*id="wc-woocommerce_payments-upe-form".*<\/fieldset>.*/s', $output );
			}
		);

		$this->system_under_test = new WC_Payments_UPE_Checkout( $this->mock_wcpay_gateway, $this->mock_woopay_utilities, $this->mock_wcpay_account, $this->mock_customer_service, $this->mock_fraud_service );
	}

	public function tear_down() {
		parent::tear_down();
		WC_Payments::set_registered_card_gateway( $this->default_gateway );
	}

	public function test_fraud_prevention_token_added_when_prevention_service_enabled() {
		$token_value                   = 'test-token';
		$fraud_prevention_service_mock = $this->getMockBuilder( Fraud_Prevention_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'get_token' )
			->willReturn( $token_value );

		Fraud_Prevention_Service::set_instance( $fraud_prevention_service_mock );

		// Use a callback to get and test the output (also suppresses the output buffering being printed to the CLI).
		$this->setOutputCallback(
			function ( $output ) use ( $token_value ) {
				$result = preg_match_all( '/<input[^>]*type="hidden"[^>]*name="wcpay-fraud-prevention-token"[^>]*value="' . preg_quote( $token_value, '/' ) . '"[^>]*>/', $output );

				$this->assertSame( 1, $result );
			}
		);

		$this->system_under_test->payment_fields();
	}

	public function test_fraud_prevention_token_not_added_when_prevention_service_disabled() {
		$token_value                   = 'test-token';
		$fraud_prevention_service_mock = $this->getMockBuilder( Fraud_Prevention_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		Fraud_Prevention_Service::set_instance( $fraud_prevention_service_mock );

		// Use a callback to get and test the output (also suppresses the output buffering being printed to the CLI).
		$this->setOutputCallback(
			function ( $output ) use ( $token_value ) {
				$result = preg_match_all( '/<input[^>]*type="hidden"[^>]*name="wcpay-fraud-prevention-token"[^>]*value="' . preg_quote( $token_value, '/' ) . '"[^>]*>/', $output );

				$this->assertSame( 0, $result );
			}
		);

		$this->system_under_test->payment_fields();
	}

	public function test_save_payment_method_checkbox_not_called_when_saved_cards_disabled() {
		// given: prepare the dependencies.
		wp_set_current_user( 1 );

		$this->mock_wcpay_gateway
			->method( 'is_saved_cards_enabled' )
			->willReturn( false );

		// then: check that the save_payment_method_checkbox method was called.
		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'save_payment_method_checkbox' );

		$this->system_under_test->payment_fields();
	}

	public function test_save_payment_method_checkbox_not_called_for_non_logged_in_user() {
		// given: prepare the dependencies.
		wp_set_current_user( 0 );

		$this->mock_wcpay_gateway
			->method( 'is_saved_cards_enabled' )
			->willReturn( true );

		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'should_support_saved_payments' )
			->willReturn( true );

		// then: check that the save_payment_method_checkbox method was called.
		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'save_payment_method_checkbox' );

		$this->system_under_test->payment_fields();
	}

	public function test_save_payment_method_checkbox_called() {
		// given: prepare the dependencies.
		wp_set_current_user( 1 );

		$this->mock_wcpay_gateway
			->method( 'is_saved_cards_enabled' )
			->willReturn( true );

		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'should_support_saved_payments' )
			->willReturn( true );

		// then: check that the save_payment_method_checkbox method was called.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'save_payment_method_checkbox' );

		$this->system_under_test->payment_fields();
	}

	public function test_is_woopay_enabled_when_should_enable_woopay_and_enable_it_on_cart_or_checkout() {
		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( true );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_cart_or_checkout' )->willReturn( true );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertTrue( $is_woopay_enabled );
	}

	public function test_is_woopay_enabled_false_when_should_not_enable_woopay() {
		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( false );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_cart_or_checkout' )->willReturn( true );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertFalse( $is_woopay_enabled );
	}

	public function test_is_woopay_enabled_false_when_should_enable_woopay_but_not_enable_it_on_cart_or_checkout() {
		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( true );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_cart_or_checkout' )->willReturn( false );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertFalse( $is_woopay_enabled );
	}

	public function test_return_icon_url() {
		$this->mock_wcpay_gateway
			->method( 'get_icon_url' )
			->willReturn( 'assets/images/payment-methods/cc.svg' );

		$returned_icon = $this->system_under_test->get_payment_fields_js_config()['icon'];

		$this->assertNotNull( $returned_icon );
		$this->assertStringContainsString( 'assets/images/payment-methods/cc.svg', $returned_icon );
	}

	public function test_force_network_saved_cards_enabled_when_should_use_stripe_platform() {
		$this->mock_wcpay_gateway
			->method( 'should_use_stripe_platform_on_checkout_page' )
			->willReturn( true );

		$force_network_saved_cards = $this->system_under_test->get_payment_fields_js_config()['forceNetworkSavedCards'];
		$this->assertTrue( $force_network_saved_cards );
	}

	public function test_force_network_saved_cards_disabled_when_should_not_use_stripe_platform() {
		$this->mock_wcpay_gateway
			->method( 'should_use_stripe_platform_on_checkout_page' )
			->willReturn( false );

		$force_network_saved_cards = $this->system_under_test->get_payment_fields_js_config()['forceNetworkSavedCards'];
		$this->assertFalse( $force_network_saved_cards );
	}

}
