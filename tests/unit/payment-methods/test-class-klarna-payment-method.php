<?php
/**
 * Class Klarna_Payment_Method_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Payment_Methods;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPAY_UnitTestCase;
use WC_Payments_Account;
use WC_Payments_Token_Service;
use WC_Payments;

/**
 * Klarna_Payment_Method unit tests
 */
class Klarna_Payment_Method_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock site currency string
	 *
	 * @var string
	 */
	public static $mock_site_currency = '';

	/**
	 * Mock WC_Payments_Token_Service.
	 *
	 * @var WC_Payments_Token_Service|MockObject
	 */
	private $mock_token_service;

	/**
	 * Klarna Payment Method Mock
	 *
	 * @var UPE_Payment_Method|MockObject
	 */
	private $mock_payment_method;

	/**
	 * WC_Payments_Account mocked instance.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_wcpay_account;

	/**
	 * WC_Payments_Account original instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $original_account_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_wcpay_account       = $this->createMock( WC_Payments_Account::class );
		$this->original_account_service = WC_Payments::get_account_service();
		WC_Payments::set_account_service( $this->mock_wcpay_account );

		// Arrange: Mock WC_Payments_Token_Service so its methods aren't called directly.
		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'add_payment_method_to_user' ] )
			->getMock();

		$this->mock_payment_method = $this->getMockBuilder( Klarna_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service ] )
			->onlyMethods( [] )
			->getMock();
	}

	/**
	 * Cleanup after tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();
		wcpay_get_test_container()->reset_all_replacements();
		WC_Payments::set_account_service( $this->original_account_service );
		WC_Helper_Site_Currency::$mock_site_currency = '';
	}

	/**
	 * @dataProvider provider_test_get_countries
	 */
	public function test_get_countries(
		string $account_country,
		?string $site_currency,
		array $expected_result
	) {
		$this->mock_wcpay_account->method( 'get_cached_account_data' )->willReturn(
			[
				'country' => $account_country,
			]
		);

		if ( $site_currency ) {
			WC_Helper_Site_Currency::$mock_site_currency = $site_currency;
		}

		$this->assertEqualsCanonicalizing( $expected_result, $this->mock_payment_method->get_countries() );
	}

	public function provider_test_get_countries() {
		return [
			'US account'                         => [
				'account_country' => Country_Code::UNITED_STATES,
				'site_currency'   => null,
				'expected_result' => [ Country_Code::UNITED_STATES ],
			],
			'UK account with GBP store currency' => [
				'account_country' => Country_Code::UNITED_KINGDOM,
				'site_currency'   => Currency_Code::POUND_STERLING,
				'expected_result' => [ Country_Code::UNITED_KINGDOM ],
			],
			'UK account with EUR store currency' => [
				'account_country' => Country_Code::UNITED_KINGDOM,
				'site_currency'   => Currency_Code::EURO,
				'expected_result' => [
					Country_Code::AUSTRIA,
					Country_Code::BELGIUM,
					Country_Code::FINLAND,
					Country_Code::GERMANY,
					Country_Code::IRELAND,
					Country_Code::ITALY,
					Country_Code::NETHERLANDS,
					Country_Code::SPAIN,
					Country_Code::FRANCE,
				],
			],
			'BE account with EUR store currency' => [
				'account_country' => Country_Code::BELGIUM,
				'site_currency'   => Currency_Code::EURO,
				'expected_result' => [
					Country_Code::AUSTRIA,
					Country_Code::BELGIUM,
					Country_Code::FINLAND,
					Country_Code::GERMANY,
					Country_Code::IRELAND,
					Country_Code::ITALY,
					Country_Code::NETHERLANDS,
					Country_Code::SPAIN,
					Country_Code::FRANCE,
				],
			],
			'FR account with EUR store currency' => [
				'account_country' => Country_Code::FRANCE,
				'site_currency'   => Currency_Code::EURO,
				'expected_result' => [
					Country_Code::AUSTRIA,
					Country_Code::BELGIUM,
					Country_Code::FINLAND,
					Country_Code::GERMANY,
					Country_Code::IRELAND,
					Country_Code::ITALY,
					Country_Code::NETHERLANDS,
					Country_Code::SPAIN,
					Country_Code::FRANCE,
				],
			],
		];
	}
}
