<?php
/**
 * Class WC_Payments_Currency_Manager_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Currency_Manager unit tests.
 */
class WC_Payments_Currency_Manager_Tests extends WCPAY_UnitTestCase {
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
	 * Instance of WC_Payments_Currency_Manager.
	 *
	 * @var \WCPay\WC_Payments_Currency_Manager
	 */
	private $currency_manager;

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

		$this->multi_currency_mock = $this->getMockBuilder( WCPay\MultiCurrency\MultiCurrency::class )
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

		$this->currency_manager = $this->getMockBuilder( \WCPay\WC_Payments_Currency_Manager::class )
			->setConstructorArgs( [ $this->gateway_mock ] )
			->setMethods( [ 'get_multi_currency_instance' ] )
			->getMock();

		// Mocking get_multi_currency_instance to return the multi_currency_mock.
		$this->currency_manager->method( 'get_multi_currency_instance' )
			->willReturn( $this->multi_currency_mock );

		$this->currency_manager->init_hooks();

		$this->localization_service = new WC_Payments_Localization_Service();
	}

	public function test_it_should_not_update_available_currencies_when_enabled_payment_methods_do_not_need_it() {
		$this->multi_currency_mock->expects( $this->never() )->method( $this->anything() );
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_account_domestic_currency' )->willReturn( 'USD' );

		$this->currency_manager->maybe_add_missing_currencies();
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

		$this->currency_manager->maybe_add_missing_currencies();
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

		$this->currency_manager->maybe_add_missing_currencies();
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

		$this->currency_manager->maybe_add_missing_currencies();
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

		$this->currency_manager->maybe_add_missing_currencies();
	}
}
