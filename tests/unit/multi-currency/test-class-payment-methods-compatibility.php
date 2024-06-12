<?php
/**
 * Class WCPay_Multi_Currency_PaymentMethodsCompatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\PaymentMethodsCompatibility unit tests.
 */
class WCPay_Multi_Currency_Payment_Methods_Compatibility_Tests extends WCPAY_UnitTestCase {
	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency
	 */
	private $multi_currency_mock;

	/**
	 * Mock WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway_mock;

	/**
	 * Instance WCPay\MultiCurrency\PaymentMethodsCompatibility.
	 *
	 * @var \WCPay\MultiCurrency\PaymentMethodsCompatibility
	 */
	private $payment_methods_compatibility;

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

		$this->multi_currency_mock = $this
			->getMockBuilder( WCPay\MultiCurrency\MultiCurrency::class )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_enabled_currencies',
					'get_available_currencies',
					'set_enabled_currencies',
				]
			)
			->getMock();

		$this->gateway_mock = $this
			->getMockBuilder( 'WC_Payment_Gateway_WCPay' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_upe_enabled_payment_method_ids',
					'get_account_country',
					'get_account_domestic_currency',
				]
			)
			->getMock();
		$this->gateway_mock->method( 'get_account_country' )->willReturn( 'US' );

		$this->payment_methods_compatibility = new \WCPay\MultiCurrency\PaymentMethodsCompatibility( $this->multi_currency_mock, $this->gateway_mock );
		$this->payment_methods_compatibility->init_hooks();

		$this->localization_service = new WC_Payments_Localization_Service();
	}

	public function test_it_should_not_update_available_currencies_when_enabled_payment_methods_do_not_need_it() {
		$this->multi_currency_mock->expects( $this->never() )->method( $this->anything() );
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_account_domestic_currency' )->willReturn( 'USD' );

		$this->payment_methods_compatibility->add_missing_currencies();
	}

	public function test_it_should_not_update_available_currencies_when_not_needed() {
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				'card',
				'au_becs_debit',
				'bancontact',
				'eps',
				'giropay',
				'sepa_debit',
			]
		);
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_account_domestic_currency' )->willReturn( 'USD' );
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_enabled_currencies' )->willReturn(
			[
				'EUR' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ),
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_available_currencies' )->willReturn(
			[
				'EUR' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ),
				'USD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
			]
		);
		$this->multi_currency_mock->expects( $this->never() )->method( 'set_enabled_currencies' );

		$this->payment_methods_compatibility->add_missing_currencies();
	}

	public function test_it_should_update_available_currencies_when_needed() {
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				'card',
				'au_becs_debit',
				'bancontact',
				'eps',
				'giropay',
				'sepa_debit',
			]
		);
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_account_domestic_currency' )->willReturn( 'USD' );
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_enabled_currencies' )->willReturn(
			[
				'USD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
				'AUD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'AUD' ),
				'EUR' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ),
			]
		);
		$this->multi_currency_mock
			->expects( $this->once() )
			->method( 'set_enabled_currencies' )
			->with(
				$this->equalTo(
					[
						'USD',
						'AUD',
						'EUR',
					]
				)
			);

		$this->payment_methods_compatibility->add_missing_currencies();
	}

	public function test_it_should_not_update_available_currencies_with_bnpl_methods() {
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				'card',
				'klarna',
			]
		);
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_account_domestic_currency' )->willReturn( 'USD' );
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_enabled_currencies' )->willReturn(
			[
				'EUR' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ),
				'USD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
				'AUD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'AUD' ),
				'EUR' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ),
			]
		);
		$this->multi_currency_mock->expects( $this->never() )->method( 'set_enabled_currencies' );

		$this->payment_methods_compatibility->add_missing_currencies();
	}

	public function test_it_should_update_available_currencies_with_bnpl_methods() {
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				'card',
				'klarna',
			]
		);
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_account_domestic_currency' )->willReturn( 'USD' );
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_enabled_currencies' )->willReturn(
			[
				'EUR' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ),
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
				'EUR' => new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ),
			]
		);
		$this->multi_currency_mock
			->expects( $this->once() )
			->method( 'set_enabled_currencies' )
			->with(
				$this->equalTo(
					[
						'EUR',
						'USD',
					]
				)
			);

		$this->payment_methods_compatibility->add_missing_currencies();
	}
}
