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
					'wc_payments_get_payment_method_map',
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
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'wc_payments_get_payment_method_map' )->willReturn( $this->get_mocked_payment_methods_map() );
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'get_account_domestic_currency' )->willReturn( 'USD' );
		$this->multi_currency_mock->expects( $this->never() )->method( 'set_enabled_currencies' );

		$this->currency_manager->maybe_add_missing_currencies();
	}

	public function test_it_should_not_update_available_currencies_when_not_needed() {
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'wc_payments_get_payment_method_map' )->willReturn( $this->get_mocked_payment_methods_map() );
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
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'wc_payments_get_payment_method_map' )->willReturn( $this->get_mocked_payment_methods_map() );
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
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'wc_payments_get_payment_method_map' )->willReturn( $this->get_mocked_payment_methods_map() );
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
		$this->gateway_mock->expects( $this->atLeastOnce() )->method( 'wc_payments_get_payment_method_map' )->willReturn( $this->get_mocked_payment_methods_map() );
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

	private function get_mocked_payment_methods_map() {
		$card_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$card_payment_method->method( 'get_currencies' )->willReturn( [] );
		$card_payment_method->method( 'get_title' )->willReturn( 'Card Payment Method' );
		$card_payment_method->method( 'get_id' )->willReturn( 'card' );
		$card_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( false );

		$becs_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$becs_payment_method->method( 'get_currencies' )->willReturn( [ 'AUD' ] );
		$becs_payment_method->method( 'get_title' )->willReturn( 'au_becs_debit Payment Method' );
		$becs_payment_method->method( 'get_id' )->willReturn( 'au_becs_debit' );
		$becs_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( false );

		$bancontact_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$bancontact_payment_method->method( 'get_currencies' )->willReturn( [ 'EUR' ] );
		$bancontact_payment_method->method( 'get_title' )->willReturn( 'bancontact Payment Method' );
		$bancontact_payment_method->method( 'get_id' )->willReturn( 'bancontact' );
		$bancontact_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( false );

		$eps_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$eps_payment_method->method( 'get_currencies' )->willReturn( [ 'EUR' ] );
		$eps_payment_method->method( 'get_title' )->willReturn( 'eps Payment Method' );
		$eps_payment_method->method( 'get_id' )->willReturn( 'eps' );
		$eps_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( false );

		$giropay_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$giropay_payment_method->method( 'get_currencies' )->willReturn( [ 'EUR' ] );
		$giropay_payment_method->method( 'get_title' )->willReturn( 'giropay Payment Method' );
		$giropay_payment_method->method( 'get_id' )->willReturn( 'giropay' );
		$giropay_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( false );

		$sepa_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$sepa_payment_method->method( 'get_currencies' )->willReturn( [ 'EUR' ] );
		$sepa_payment_method->method( 'get_title' )->willReturn( 'sepa_debit Payment Method' );
		$sepa_payment_method->method( 'get_id' )->willReturn( 'sepa_debit' );
		$sepa_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( false );

		$klarna_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$klarna_payment_method->method( 'get_currencies' )->willReturn( [ 'USD', 'GBP', 'EUR', 'DKK', 'NOK', 'SEK' ] );
		$klarna_payment_method->method( 'get_title' )->willReturn( 'klarna Payment Method' );
		$klarna_payment_method->method( 'get_id' )->willReturn( 'klarna' );
		$klarna_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( true );

		$link_payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$link_payment_method->method( 'get_currencies' )->willReturn( [ 'USD' ] );
		$link_payment_method->method( 'get_title' )->willReturn( 'link Payment Method' );
		$link_payment_method->method( 'get_id' )->willReturn( 'link' );
		$link_payment_method->method( 'has_domestic_transactions_restrictions' )->willReturn( false );

		return [
			'card'          => $card_payment_method,
			'au_becs_debit' => $becs_payment_method,
			'bancontact'    => $bancontact_payment_method,
			'eps'           => $eps_payment_method,
			'giropay'       => $giropay_payment_method,
			'sepa_debit'    => $sepa_payment_method,
			'klarna'        => $klarna_payment_method,
			'link'          => $link_payment_method,
		];
	}
}
