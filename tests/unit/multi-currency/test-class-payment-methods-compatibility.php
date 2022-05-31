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
				]
			)
			->getMock();

		$this->payment_methods_compatibility = new \WCPay\MultiCurrency\PaymentMethodsCompatibility( $this->multi_currency_mock, $this->gateway_mock );
		add_filter( 'pre_option__wcpay_feature_upe', [ $this, 'mock_upe_flag' ], 50, 3 );
	}

	public function tear_down() {
		parent::tear_down();
		remove_filter( 'pre_option__wcpay_feature_upe', [ $this, 'mock_upe_flag' ], 50 );
	}

	public function mock_upe_flag( $pre_option, $option, $default ) {
		return '1';
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
				'au_becs_debit',
				'bancontact',
				'eps',
				'giropay',
				'sepa_debit',
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
				'au_becs_debit',
				'bancontact',
				'eps',
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
				'USD' => new \WCPay\MultiCurrency\Currency( 'USD' ),
				'AUD' => new \WCPay\MultiCurrency\Currency( 'AUD' ),
				'EUR' => new \WCPay\MultiCurrency\Currency( 'EUR' ),
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
}
