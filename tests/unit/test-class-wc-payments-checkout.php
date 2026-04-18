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
use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\PaymentMethods\Configs\Definitions\CardDefinition;
use WCPay\PaymentMethods\Configs\Definitions\BancontactDefinition;
use WCPay\PaymentMethods\Configs\Definitions\EpsDefinition;
use WCPay\PaymentMethods\Configs\Definitions\IdealDefinition;
use WCPay\PaymentMethods\Configs\Definitions\LinkDefinition;
use WCPay\PaymentMethods\Configs\Definitions\P24Definition;

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
			->onlyMethods(
				[
					'get_account_domestic_currency',
					'should_use_stripe_platform_on_checkout_page',
					'should_support_saved_payments',
					'is_saved_cards_enabled',
					'save_payment_method_checkbox',
					'get_account_statement_descriptor',
					'get_icon_url',
					'get_payment_method_ids_enabled_at_checkout',
					'get_payment_method_ids_enabled_at_checkout_filtered_by_fees',
					'is_subscription_item_in_cart',
					'wc_payments_get_payment_method_by_id',
					'display_gateway_html',
					'init_settings',
				]
			)
			->disableOriginalConstructor()
			->getMock();
		$this->mock_wcpay_gateway->id = 'woocommerce_payments';
		$this->mock_wcpay_gateway
			->method( 'get_account_domestic_currency' )
			->willReturn( 'USD' );

		$this->mock_woopay_utilities = $this->createMock( WooPay_Utilities::class );
		$this->mock_woopay_utilities = $this->getMockBuilder( WooPay_Utilities::class )
			->onlyMethods( [ 'should_enable_woopay', 'should_enable_woopay_on_guest_checkout' ] )
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
			->method( 'get_payment_method_ids_enabled_at_checkout_filtered_by_fees' )
			->willReturn( [] );

		$this->mock_token_service = $this->createMock( WC_Payments_Token_Service::class );

		// This is needed to ensure that only the mocked gateway is always used by the checkout class.
		$this->default_gateway = WC_Payments::get_gateway();
		WC_Payments::set_gateway( $this->mock_wcpay_gateway );

		// Use a callback to suppresses the output buffering being printed to the CLI.
		$this->setOutputCallback(
			function ( $output ) {
				preg_match_all( '/.*<fieldset.*class="wc-payment-form".*<\/fieldset>.*/s', $output );
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
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_guest_checkout' )->willReturn( true );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertTrue( $is_woopay_enabled );
	}

	public function test_is_woopay_enabled_false_when_should_not_enable_woopay() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( false );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_guest_checkout' )->willReturn( true );

		$is_woopay_enabled = $this->system_under_test->get_payment_fields_js_config()['isWooPayEnabled'];
		$this->assertFalse( $is_woopay_enabled );
	}

	public function test_is_woopay_enabled_false_when_should_enable_woopay_but_not_enable_it_on_cart_or_checkout() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_woopay_utilities->method( 'should_enable_woopay' )->willReturn( true );
		$this->mock_woopay_utilities->method( 'should_enable_woopay_on_guest_checkout' )->willReturn( false );

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
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );
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

		$card_pm = $this->getMockBuilder( UPE_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service, CardDefinition::class ] )
			->onlyMethods( [ 'get_icon', 'get_dark_icon' ] )
			->getMock();

		$link_pm = $this->getMockBuilder( UPE_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service, LinkDefinition::class ] )
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
			->willReturnMap(
				[
					[ 'card', $card_pm ],
					[ 'link', $link_pm ],
				]
			);

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_fees' )
			->willReturn( $payment_methods );

		$this->assertSame(
			[
				'card' => [
					'isReusable'             => true,
					'isBnpl'                 => false,
					'isExpressCheckout'      => false,
					'title'                  => 'Card',
					'icon'                   => $icon_url,
					'darkIcon'               => $dark_icon_url,
					'showSaveOption'         => true,
					'countries'              => [],
					'gatewayId'              => 'woocommerce_payments',
					'testingInstructions'    => 'Use test card <button type="button" class="js-woopayments-copy-test-number" aria-label="Click to copy the test number to clipboard" title="Copy to clipboard"><i></i><span>4242 4242 4242 4242</button> or refer to our <a href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">testing guide</a>.',
					'forceNetworkSavedCards' => false,
				],
				'link' => [
					'isReusable'             => true,
					'isBnpl'                 => false,
					'isExpressCheckout'      => false,
					'title'                  => 'Link',
					'icon'                   => $icon_url,
					'darkIcon'               => $dark_icon_url,
					'showSaveOption'         => true,
					'countries'              => [],
					'gatewayId'              => 'woocommerce_payments_link',
					'testingInstructions'    => '',
					'forceNetworkSavedCards' => false,
				],
			],
			$this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig']
		);
	}

		/**
		 * @dataProvider non_reusable_payment_method_provider
		 */
	public function test_no_save_option_for_non_reusable_payment_method( $payment_method_id, $payment_method_class ) {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

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
				new UPE_Payment_Method( $this->mock_token_service, $payment_method_class )
			);

		$this->assertSame( false, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ $payment_method_id ]['showSaveOption'] );
	}

	public function non_reusable_payment_method_provider() {
		return [
			[ Payment_Method::BANCONTACT, BancontactDefinition::class ],
			[ Payment_Method::EPS, EpsDefinition::class ],
			[ Payment_Method::IDEAL, IdealDefinition::class ],
			[ Payment_Method::P24, P24Definition::class ],
		];
	}

	public function test_no_save_option_for_reusable_payment_payment_with_subscription_in_cart() {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );
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
				new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class )
			);
			$this->assertSame( false, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::CARD ]['showSaveOption'] );
	}

	public function test_no_save_option_for_reusable_payment_payment_but_with_saved_cards_disabled() {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );
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
				new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class )
			);
			$this->assertSame( false, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::CARD ]['showSaveOption'] );
	}

	public function test_save_option_for_reusable_payment_payment() {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );
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
				new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class )
			);
			$this->assertSame( true, $this->system_under_test->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::CARD ]['showSaveOption'] );
	}

	/**
	 * Data provider for testing different country card numbers
	 */
	public function country_test_cards_provider(): array {
		return [
			'US card'                        => [
				'country'       => 'US',
				'expected_card' => '4242 4242 4242 4242',
			],
			'Brazil card'                    => [
				'country'       => 'BR',
				'expected_card' => '4000 0007 6000 0002',
			],
			'UK card'                        => [
				'country'       => 'GB',
				'expected_card' => '4000 0082 6000 0000',
			],
			'Invalid country defaults to US' => [
				'country'       => 'XX',
				'expected_card' => '4242 4242 4242 4242',
			],
		];
	}

	/**
	 * @dataProvider country_test_cards_provider
	 */
	public function test_credit_card_testing_instructions_by_country( string $country, string $expected_card ) {
		$cc_payment_method = new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class );

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( $country );

		$this->mock_wcpay_gateway
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card' ] );

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn( $cc_payment_method );

		$config = $this->system_under_test->get_payment_fields_js_config();

		$expected_instructions = sprintf(
			'Use test card <button type="button" class="js-woopayments-copy-test-number" aria-label="Click to copy the test number to clipboard" title="Copy to clipboard"><i></i><span>%s</button> or refer to our <a href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards" target="_blank">testing guide</a>.',
			$expected_card
		);

		$this->assertEquals(
			$expected_instructions,
			$config['paymentMethodsConfig']['card']['testingInstructions']
		);
	}

	/**
	 * Tests that get_enabled_payment_method_config uses get_payment_method_ids_enabled_at_checkout
	 * which filters payment methods by currency. This ensures Link is only available for USD.
	 */
	public function test_get_enabled_payment_method_config_uses_currency_filtered_payment_methods() {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

		// Simulate that get_payment_method_ids_enabled_at_checkout returns only 'card'
		// (because Link would be filtered out for non-USD currency).
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card' ] );

		$card_pm = new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class );

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->with( 'card' )
			->willReturn( $card_pm );

		$config = $this->system_under_test->get_enabled_payment_method_config();

		// Link should not be in the config because get_payment_method_ids_enabled_at_checkout
		// filters it out based on currency.
		$this->assertArrayHasKey( 'card', $config );
		$this->assertArrayNotHasKey( 'link', $config );
	}

	public function test_add_payment_methods_config_to_fragments_returns_unchanged_when_payment_fragment_missing() {
		$fragments = [
			'.some-other-selector' => '<div>Some content</div>',
		];

		$result = $this->system_under_test->add_payment_methods_config_to_update_order_review_fragments( $fragments );

		$this->assertSame( $fragments, $result );
	}

	public function test_add_payment_methods_config_to_fragments_includes_dynamic_config_values() {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card' ] );

		$card_pm = new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class );

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn( $card_pm );

		$original_payment_html = '<div class="woocommerce-checkout-payment">Stuff</div>';

		$fragments = [
			'.woocommerce-checkout-payment' => $original_payment_html,
		];

		$result = $this->system_under_test->add_payment_methods_config_to_update_order_review_fragments( $fragments );

		// The original content should still be there.
		$this->assertStringContainsString( $original_payment_html, $result['.woocommerce-checkout-payment'] );
		// A script tag should be appended.
		$this->assertStringContainsString( '<script>', $result['.woocommerce-checkout-payment'] );
		$this->assertStringContainsString( 'window.wcpay_upe_config', $result['.woocommerce-checkout-payment'] );
		$this->assertStringContainsString( 'Object.assign', $result['.woocommerce-checkout-payment'] );
		// Check that the script contains the expected dynamic config keys.
		$this->assertStringContainsString( 'paymentMethodsConfig', $result['.woocommerce-checkout-payment'] );
		$this->assertStringContainsString( 'currency', $result['.woocommerce-checkout-payment'] );
		$this->assertStringContainsString( 'cartTotal', $result['.woocommerce-checkout-payment'] );
	}

	public function test_payment_fields_js_config_includes_is_express_checkout_in_payment_methods_enabled_when_feature_and_option_enabled() {
		// Requires WooCommerce 10.6.0+ for the feature flag (or dev mode).
		WC_Payments::mode()->dev();

		update_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME, '1' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_wcpay_gateway->update_option( 'express_checkout_in_payment_methods', 'yes' );

		$js_config = $this->system_under_test->get_payment_fields_js_config();

		$this->assertTrue( $js_config['isExpressCheckoutInPaymentMethodsEnabled'] );

		delete_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME );
		WC_Payments::mode()->live();
	}

	public function test_payment_fields_js_config_includes_is_express_checkout_in_payment_methods_enabled_false_when_option_is_no() {
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_wcpay_gateway->update_option( 'express_checkout_in_payment_methods', 'no' );

		$js_config = $this->system_under_test->get_payment_fields_js_config();

		$this->assertFalse( $js_config['isExpressCheckoutInPaymentMethodsEnabled'] );
	}

	public function test_payment_fields_js_config_includes_is_express_checkout_in_payment_methods_enabled_false_when_feature_disabled() {
		delete_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME );
		WC_Payments::mode()->live();

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		$this->mock_wcpay_gateway->update_option( 'express_checkout_in_payment_methods', 'yes' );

		$js_config = $this->system_under_test->get_payment_fields_js_config();

		// Even with option 'yes', result should be false when feature flag is disabled.
		$this->assertFalse( $js_config['isExpressCheckoutInPaymentMethodsEnabled'] );
	}

	public function test_get_enabled_payment_method_config_includes_express_methods_when_feature_enabled() {
		WC_Payments::mode()->dev();
		update_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME, '1' );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_in_payment_methods', 'yes' );

		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card' ] );

		// Set up payment gateway map so is_payment_request_enabled() returns true.
		$mock_google_pay_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_google_pay_gateway->method( 'is_enabled' )->willReturn( true );

		$reflection = new \ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$original_map = $property->getValue( null );

		// Add gateways for card, google_pay, and apple_pay.
		$mock_card_gateway     = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_card_gateway->id = 'woocommerce_payments';
		$mock_card_gateway->method( 'should_use_stripe_platform_on_checkout_page' )->willReturn( false );
		$mock_apple_pay_gateway     = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_apple_pay_gateway->id = 'woocommerce_payments_apple_pay';
		$mock_apple_pay_gateway->method( 'should_use_stripe_platform_on_checkout_page' )->willReturn( false );
		$mock_google_pay_gateway->id = 'woocommerce_payments_google_pay';
		$mock_google_pay_gateway->method( 'should_use_stripe_platform_on_checkout_page' )->willReturn( false );

		$property->setValue(
			null,
			[
				'card'       => $mock_card_gateway,
				'google_pay' => $mock_google_pay_gateway,
				'apple_pay'  => $mock_apple_pay_gateway,
			]
		);

		// Mock payment method lookups.
		$card_pm = new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class );

		$mock_apple_pay_pm = $this->createMock( UPE_Payment_Method::class );
		$mock_apple_pay_pm->method( 'is_reusable' )->willReturn( false );
		$mock_apple_pay_pm->method( 'is_bnpl' )->willReturn( false );
		$mock_apple_pay_pm->method( 'is_express_checkout' )->willReturn( true );
		$mock_apple_pay_pm->method( 'get_title' )->willReturn( 'Apple Pay' );
		$mock_apple_pay_pm->method( 'get_icon' )->willReturn( '' );
		$mock_apple_pay_pm->method( 'get_dark_icon' )->willReturn( '' );
		$mock_apple_pay_pm->method( 'get_countries' )->willReturn( [] );
		$mock_apple_pay_pm->method( 'get_testing_instructions' )->willReturn( '' );

		$mock_google_pay_pm = $this->createMock( UPE_Payment_Method::class );
		$mock_google_pay_pm->method( 'is_reusable' )->willReturn( false );
		$mock_google_pay_pm->method( 'is_bnpl' )->willReturn( false );
		$mock_google_pay_pm->method( 'is_express_checkout' )->willReturn( true );
		$mock_google_pay_pm->method( 'get_title' )->willReturn( 'Google Pay' );
		$mock_google_pay_pm->method( 'get_icon' )->willReturn( '' );
		$mock_google_pay_pm->method( 'get_dark_icon' )->willReturn( '' );
		$mock_google_pay_pm->method( 'get_countries' )->willReturn( [] );
		$mock_google_pay_pm->method( 'get_testing_instructions' )->willReturn( '' );

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturnCallback(
				function ( $id ) use ( $card_pm, $mock_apple_pay_pm, $mock_google_pay_pm ) {
					$map = [
						'card'       => $card_pm,
						'apple_pay'  => $mock_apple_pay_pm,
						'google_pay' => $mock_google_pay_pm,
					];
					return $map[ $id ] ?? null;
				}
			);

		$config = $this->system_under_test->get_enabled_payment_method_config();

		$this->assertArrayHasKey( 'card', $config );
		$this->assertArrayHasKey( 'apple_pay', $config );
		$this->assertArrayHasKey( 'google_pay', $config );
		$this->assertTrue( $config['apple_pay']['isExpressCheckout'] );
		$this->assertTrue( $config['google_pay']['isExpressCheckout'] );

		$property->setValue( null, $original_map );
		$property->setAccessible( false );
		delete_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME );
		WC_Payments::mode()->live();
	}

	public function test_get_enabled_payment_method_config_excludes_express_methods_when_feature_disabled() {
		delete_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME );
		WC_Payments::mode()->live();

		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card' ] );

		$card_pm = new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class );
		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn( $card_pm );

		$config = $this->system_under_test->get_enabled_payment_method_config();

		$this->assertArrayHasKey( 'card', $config );
		$this->assertArrayNotHasKey( 'apple_pay', $config );
		$this->assertArrayNotHasKey( 'google_pay', $config );
	}

	public function test_get_config_for_payment_method_returns_empty_when_gateway_not_found() {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'nonexistent_method' ] );

		// Return a valid payment method object so we pass the first null guard.
		$mock_pm = $this->createMock( UPE_Payment_Method::class );
		$mock_pm->method( 'is_reusable' )->willReturn( false );
		$mock_pm->method( 'is_bnpl' )->willReturn( false );
		$mock_pm->method( 'is_express_checkout' )->willReturn( false );
		$mock_pm->method( 'get_title' )->willReturn( 'Test' );
		$mock_pm->method( 'get_icon' )->willReturn( '' );
		$mock_pm->method( 'get_dark_icon' )->willReturn( '' );
		$mock_pm->method( 'get_countries' )->willReturn( [] );

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->with( 'nonexistent_method' )
			->willReturn( $mock_pm );

		// 'nonexistent_method' is not in the gateway map, so
		// wc_payments_get_payment_gateway_by_id returns false → empty config.
		$config = $this->system_under_test->get_enabled_payment_method_config();

		$this->assertArrayHasKey( 'nonexistent_method', $config );
		$this->assertSame( [], $config['nonexistent_method'] );
	}

	public function test_update_order_review_fragments_include_express_methods_when_feature_enabled() {
		WC_Payments::mode()->dev();
		update_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME, '1' );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_in_payment_methods', 'yes' );

		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card' ] );

		// Set up payment gateway map so is_payment_request_enabled() returns true.
		$reflection = new \ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$original_map = $property->getValue( null );

		$mock_google_pay_gateway     = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_google_pay_gateway->id = 'woocommerce_payments_google_pay';
		$mock_google_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$mock_google_pay_gateway->method( 'should_use_stripe_platform_on_checkout_page' )->willReturn( false );

		$mock_apple_pay_gateway     = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_apple_pay_gateway->id = 'woocommerce_payments_apple_pay';
		$mock_apple_pay_gateway->method( 'should_use_stripe_platform_on_checkout_page' )->willReturn( false );

		$mock_card_gateway     = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_card_gateway->id = 'woocommerce_payments';
		$mock_card_gateway->method( 'should_use_stripe_platform_on_checkout_page' )->willReturn( false );

		$property->setValue(
			null,
			[
				'card'       => $mock_card_gateway,
				'google_pay' => $mock_google_pay_gateway,
				'apple_pay'  => $mock_apple_pay_gateway,
			]
		);

		// Mock payment method lookups.
		$card_pm = new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class );

		$mock_express_pm = $this->createMock( UPE_Payment_Method::class );
		$mock_express_pm->method( 'is_reusable' )->willReturn( false );
		$mock_express_pm->method( 'is_bnpl' )->willReturn( false );
		$mock_express_pm->method( 'is_express_checkout' )->willReturn( true );
		$mock_express_pm->method( 'get_title' )->willReturn( 'Express' );
		$mock_express_pm->method( 'get_icon' )->willReturn( '' );
		$mock_express_pm->method( 'get_dark_icon' )->willReturn( '' );
		$mock_express_pm->method( 'get_countries' )->willReturn( [] );
		$mock_express_pm->method( 'get_testing_instructions' )->willReturn( '' );

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturnCallback(
				function ( $id ) use ( $card_pm, $mock_express_pm ) {
					if ( 'card' === $id ) {
						return $card_pm;
					}
					if ( in_array( $id, [ 'apple_pay', 'google_pay' ], true ) ) {
						return $mock_express_pm;
					}
					return null;
				}
			);

		$fragments = [
			'.woocommerce-checkout-payment' => '<div class="woocommerce-checkout-payment">Payment</div>',
		];

		$result = $this->system_under_test->add_payment_methods_config_to_update_order_review_fragments( $fragments );

		$this->assertStringContainsString( 'apple_pay', $result['.woocommerce-checkout-payment'] );
		$this->assertStringContainsString( 'google_pay', $result['.woocommerce-checkout-payment'] );

		$property->setValue( null, $original_map );
		$property->setAccessible( false );
		delete_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME );
		WC_Payments::mode()->live();
	}

	public function test_payment_method_config_includes_is_express_checkout() {
		$this->mock_wcpay_account
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ 'card' ] );

		$card_pm = new UPE_Payment_Method( $this->mock_token_service, CardDefinition::class );

		$this->mock_wcpay_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturn( $card_pm );

		$js_config = $this->system_under_test->get_payment_fields_js_config();

		$this->assertArrayHasKey( 'isExpressCheckout', $js_config['paymentMethodsConfig']['card'] );
		$this->assertFalse( $js_config['paymentMethodsConfig']['card']['isExpressCheckout'] );
	}
}
