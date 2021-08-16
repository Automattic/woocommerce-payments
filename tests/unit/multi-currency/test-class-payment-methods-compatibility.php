<?php
/**
 * Class WCPay_Multi_Currency_PaymentMethodsCompatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\PaymentMethodsCompatibility unit tests.
 */
class WCPay_Multi_Currency_Payment_Methods_Compatibility_Tests extends WP_UnitTestCase {
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
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

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
				]
			)
			->getMock();

		$this->payment_methods_compatibility = new \WCPay\MultiCurrency\PaymentMethodsCompatibility( $this->multi_currency_mock, $this->gateway_mock );
	}

	public function test_it_should_not_update_available_currencies_when_enabled_payment_methods_do_not_need_it() {
		$this->multi_currency_mock->expects( $this->never() )->method( $this->anything() );
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );

		$this->payment_methods_compatibility->add_missing_currencies();
	}

	public function test_it_should_not_update_available_currencies_when_not_needed() {
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				'card',
				'bancontact',
				'giropay',
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_enabled_currencies' )->willReturn(
			[
				'EUR' => new \WCPay\MultiCurrency\Currency( 'EUR' ),
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_available_currencies' )->willReturn(
			[
				'EUR' => new \WCPay\MultiCurrency\Currency( 'EUR' ),
				'USD' => new \WCPay\MultiCurrency\Currency( 'USD' ),
			]
		);
		$this->multi_currency_mock->expects( $this->never() )->method( 'set_enabled_currencies' );

		$this->payment_methods_compatibility->add_missing_currencies();
	}

	public function test_it_should_update_available_currencies_when_needed() {
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				'card',
				'bancontact',
				'giropay',
				'sepa_debit',
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_enabled_currencies' )->willReturn(
			[
				'USD' => new \WCPay\MultiCurrency\Currency( 'USD' ),
			]
		);
		$this->multi_currency_mock->expects( $this->atLeastOnce() )->method( 'get_available_currencies' )->willReturn(
			[
				'EUR' => new \WCPay\MultiCurrency\Currency( 'EUR' ),
				'USD' => new \WCPay\MultiCurrency\Currency( 'USD' ),
			]
		);
		$this->multi_currency_mock
			->expects( $this->once() )
			->method( 'set_enabled_currencies' )
			->with(
				$this->equalTo(
					[
						'USD',
						'EUR',
					]
				)
			);

		$this->payment_methods_compatibility->add_missing_currencies();
	}
}
