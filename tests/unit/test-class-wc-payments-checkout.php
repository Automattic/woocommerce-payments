<?php
/**
 * Class WC_Payments_Checkout_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WC_Payments_Checkout;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Payment_Method;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Payment_Methods\Bancontact_Payment_Method;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Eps_Payment_Method;
use WCPay\Payment_Methods\Giropay_Payment_Method;
use WCPay\Payment_Methods\Ideal_Payment_Method;
use WCPay\Payment_Methods\Link_Payment_Method;
use WCPay\Payment_Methods\P24_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\Payment_Methods\Sofort_Payment_Method;

/**
 * Class WC_Payments_Checkout_Test
 *
 * @package WooCommerce\Payments\Tests
 */
class WC_Payments_Checkout_Test extends WP_UnitTestCase {

	/**
	 * Holds the object, which will be tested.
	 *
	 * @var WC_Payments_Checkout
	 */
	private $system_under_test;

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
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
	 * Mock Token Service.
	 *
	 * @var WC_Payments_Token_Service|MockObject
	 */
	private $mock_token_service;

	/**
	 * Default gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $default_gateway;

	public function set_up() {
		parent::set_up();

		// Setup the gateway mock.
		$this->mock_wcpay_gateway     = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->onlyMethods( [ 'get_account_domestic_currency', 'get_payment_method_ids_enabled_at_checkout', 'should_use_stripe_platform_on_checkout_page', 'should_support_saved_payments', 'is_saved_cards_enabled', 'save_payment_method_checkbox', 'get_account_statement_descriptor', 'get_icon_url', 'get_payment_method_ids_enabled_at_checkout_filtered_by_fees', 'is_subscription_item_in_cart', 'wc_payments_get_payment_method_by_id', 'display_gateway_html' ] )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_wcpay_gateway->id = 'woocommerce_payments';
		$this->mock_wcpay_gateway
			->method( 'get_account_domestic_currency' )
			->willReturn( 'USD' );

		$this->mock_woopay_utilities = $this->createMock( WooPay_Utilities::class );
		$this->mock_woopay_utilities = $this->getMockBuilder( WooPay_Utilities::class )
			->onlyMethods( [ 'should_enable_woopay', 'should_enable_woopay_on_cart_or_checkout' ] )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_wcpay_account    = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_fraud_service    = $this->createMock( WC_Payments_Fraud_Service::class );

		$this->mock_wcpay_gateway
			->method( 'get_account_statement_descriptor' )
			->willReturn( 'localhost' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout_filtered_by_fees' )
			->willReturn( [] );

		$this->mock_token_service = $this->createMock( WC_Payments_Token_Service::class );

		// This is needed to ensure that only the mocked gateway is always used by the checkout class.
		$this->default_gateway = WC_Payments::get_gateway();
		WC_Payments::set_gateway( $this->mock_wcpay_gateway );

		// Use a callback to suppresses the output buffering being printed to the CLI.
		$this->setOutputCallback(
			function ( $output ) {
				preg_match_all( '/.*<fieldset.*id="wc-woocommerce_payments-upe-form".*<\/fieldset>.*/s', $output );
			}
		);

		$this->system_under_test = new WC_Payments_Checkout( $this->mock_wcpay_gateway, $this->mock_woopay_utilities, $this->mock_wcpay_account, $this->mock_customer_service, $this->mock_fraud_service );
	}

	public function tear_down() {
		parent::tear_down();
		WC_Payments::set_gateway( $this->default_gateway );
	}

	public function test_save_payment_method_checkbox_not_called_when_saved_cards_disabled() {
		// given: prepare the dependencies.
		wp_set_current_user( 1 );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

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
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

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
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

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

	public function test_display_gateway_html_called() {
			$this->mock_wcpay_gateway
				->expects( $this->any() )
				->method( 'get_payment_method_ids_enabled_at_checkout' )
				->willReturn( [] );

		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'display_gateway_html' );

		$this->system_under_test->payment_fields();
	}

	public function test_is_woopay_enabled_when_should_enable_woopay_and_enable_it_on_cart_or_checkout() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( true );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_cart_or_checkout' )->willReturn( true );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertTrue( $is_woopay_enabled );
	}

	public function test_is_woopay_enabled_false_when_should_not_enable_woopay() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( false );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_cart_or_checkout' )->willReturn( true );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertFalse( $is_woopay_enabled );
	}

	public function test_is_woopay_enabled_false_when_should_enable_woopay_but_not_enable_it_on_cart_or_checkout() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( true );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_cart_or_checkout' )->willReturn( false );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertFalse( $is_woopay_enabled );
	}

	public function test_return_icon_url() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_wcpay_gateway
			->method( 'get_icon_url' )
			->willReturn( 'assets/images/payment-methods/cc.svg' );

		$returned_icon = $this->system_under_test->get_payment_fields_js_config()['icon'];

		$this->assertNotNull( $returned_icon );
		$this->assertStringContainsString( 'assets/images/payment-methods/cc.svg', $returned_icon );
	}

	public function test_force_network_saved_cards_enabled_when_should_use_stripe_platform() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_wcpay_gateway
			->method( 'should_use_stripe_platform_on_checkout_page' )
			->willReturn( true );

		$force_network_saved_cards = $this->system_under_test->get_payment_fields_js_config()['forceNetworkSavedCards'];
		$this->assertTrue( $force_network_saved_cards );
	}

	public function test_force_network_saved_cards_disabled_when_should_not_use_stripe_platform() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_wcpay_gateway
			->method( 'should_use_stripe_platform_on_checkout_page' )
			->willReturn( false );

		$force_network_saved_cards = $this->system_under_test->get_payment_fields_js_config()['forceNetworkSavedCards'];
		$this->assertFalse( $force_network_saved_cards );
	}

	public function test_link_payment_method_provided_when_card_enabled() {
		$icon_url      = 'test-icon-url';
		$dark_icon_url = 'test-dark-icon-url';
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card', 'link' ] );

		$this->mock_wcpay_gateway
			->method( 'is_saved_cards_enabled' )
			->willReturn( true );

		$this->mock_wcpay_gateway
			->method( 'is_subscription_item_in_cart' )
			->willReturn( false );

		$payment_methods = [
			'card' => [ 'base' => 0.1 ],
			'link' => [
				'base' => 0.1,
			],
		];

		$card_pm = $this->getMockBuilder( CC_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service ] )
			->onlyMethods( [ 'get_icon', 'get_dark_icon' ] )
			->getMock();

		$link_pm = $this->getMockBuilder( Link_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service ] )
			->onlyMethods( [ 'get_icon', 'get_dark_icon' ] )
			->getMock();

		$card_pm->expects( $this->any() )
			->method( 'get_icon' )
			->will(
				$this->returnValue( $icon_url )
			);
		$card_pm->expects( $this->any() )
			->method( 'get_dark_icon' )
			->will(
				$this->returnValue( $dark_icon_url )
			);

		$link_pm->expects( $this->any() )
			->method( 'get_icon' )
			->will(
				$this->returnValue( $icon_url )
			);
		$link_pm->expects( $this->any() )
			->method( 'get_dark_icon' )
			->will(
				$this->returnValue( $dark_icon_url )
			);

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->withConsecutive( [ 'card' ], [ 'link' ] )
			->willReturnOnConsecutiveCalls( $card_pm, $link_pm );

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_fees' )
			->willReturn( $payment_methods );

		$this->assertSame(
			$this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'],
			[
				'card' => [
					'isReusable'             => true,
					'title'                  => 'Credit card / debit card',
					'icon'                   => $icon_url,
					'darkIcon'               => $dark_icon_url,
					'showSaveOption'         => true,
					'countries'              => [],
					'testingInstructions'    => '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://woo.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">here</a>.',
					'forceNetworkSavedCards' => false,
				],
				'link' => [
					'isReusable'             => true,
					'title'                  => 'Link',
					'icon'                   => $icon_url,
					'darkIcon'               => $dark_icon_url,
					'showSaveOption'         => true,
					'countries'              => [],
					'testingInstructions'    => '',
					'forceNetworkSavedCards' => false,
				],
			]
		);
	}

		/**
		 * @dataProvider non_reusable_payment_method_provider
		 */
	public function test_no_save_option_for_non_reusable_payment_method( $payment_method_id, $payment_method_class ) {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn(
				[
					$payment_method_id,
				]
			);

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn(
				new $payment_method_class( $this->mock_token_service )
			);

		$this->assertSame( false, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ $payment_method_id ]['showSaveOption'] );
	}

	public function non_reusable_payment_method_provider() {
		return [
			[ Payment_Method::BANCONTACT, Bancontact_Payment_Method::class ],
			[ Payment_Method::EPS, Eps_Payment_Method::class ],
			[ Payment_Method::GIROPAY, Giropay_Payment_Method::class ],
			[ Payment_Method::IDEAL, Ideal_Payment_Method::class ],
			[ Payment_Method::P24, P24_Payment_Method::class ],
			[ Payment_Method::SOFORT, Sofort_Payment_Method::class ],
		];
	}

	public function test_no_save_option_for_reusable_payment_payment_with_subscription_in_cart() {
		$this->mock_wcpay_gateway
			->method( 'is_subscription_item_in_cart' )
			->willReturn( true );

		$this->mock_wcpay_gateway
			->method( 'is_saved_cards_enabled' )
			->willReturn( true );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn(
				[
					Payment_Method::CARD,
				]
			);

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn(
				new CC_Payment_Method( $this->mock_token_service )
			);
			$this->assertSame( false, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::CARD ]['showSaveOption'] );
	}

	public function test_no_save_option_for_reusable_payment_payment_but_with_saved_cards_disabled() {
		$this->mock_wcpay_gateway
			->method( 'is_subscription_item_in_cart' )
			->willReturn( false );

		$this->mock_wcpay_gateway
			->method( 'is_saved_cards_enabled' )
			->willReturn( false );

			$this->mock_wcpay_gateway
				->expects( $this->any() )
				->method( 'get_payment_method_ids_enabled_at_checkout' )
				->willReturn(
					[
						Payment_Method::CARD,
					]
				);

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn(
				new CC_Payment_Method( $this->mock_token_service )
			);
			$this->assertSame( false, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::CARD ]['showSaveOption'] );
	}

	public function test_save_option_for_reusable_payment_payment() {
		$this->mock_wcpay_gateway
			->method( 'is_subscription_item_in_cart' )
			->willReturn( false );

		$this->mock_wcpay_gateway
			->method( 'is_saved_cards_enabled' )
			->willReturn( true );

			$this->mock_wcpay_gateway
				->expects( $this->any() )
				->method( 'get_payment_method_ids_enabled_at_checkout' )
				->willReturn(
					[
						Payment_Method::CARD,
					]
				);

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn(
				new CC_Payment_Method( $this->mock_token_service )
			);
			$this->assertSame( true, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::CARD ]['showSaveOption'] );
	}
}
