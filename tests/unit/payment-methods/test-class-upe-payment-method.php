<?php
/**
 * Class UPE_Payment_Method_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Payment_Methods;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;
use WCPAY_UnitTestCase;
use WC_Payments_Account;
use WC_Payments_Token_Service;
use WC_Payments;

/**
 * UPE_Payment_Method unit tests
 */
class UPE_Payment_Method_Test extends WCPAY_UnitTestCase {

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
	 * Array of mock UPE payment methods.
	 *
	 * @var array<UPE_Payment_Method|MockObject>
	 */
	private $mock_payment_methods;

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

		$payment_method_classes = [
			CC_Payment_Method::class,
			Giropay_Payment_Method::class,
			Sofort_Payment_Method::class,
			Bancontact_Payment_Method::class,
			EPS_Payment_Method::class,
			P24_Payment_Method::class,
			Ideal_Payment_Method::class,
			Sepa_Payment_Method::class,
			Becs_Payment_Method::class,
			Link_Payment_Method::class,
			Affirm_Payment_Method::class,
			Afterpay_Payment_Method::class,
		];

		foreach ( $payment_method_classes as $payment_method_class ) {
			/** @var UPE_Payment_Method|MockObject */
			$mock_payment_method = $this->getMockBuilder( $payment_method_class )
				->setConstructorArgs( [ $this->mock_token_service ] )
				->onlyMethods( [] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;
		}
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
	}

	/**
	 * @dataProvider provider_test_get_countries
	 */
	public function test_get_countries( string $payment_method_id, array $expected_result, ?string $account_country = null ) {
		$payment_method = $this->mock_payment_methods[ $payment_method_id ];

		if ( $account_country ) {
			$this->mock_wcpay_account->method( 'get_cached_account_data' )->willReturn(
				[
					'country' => $account_country,
				]
			);
		}

		$this->assertEquals( $expected_result, $payment_method->get_countries() );
	}

	public function provider_test_get_countries() {
		return [
			'Payment method without countries'             => [
				'payment_method_id' => 'card',
				'expected_result'   => [],
			],
			'Payment method supported in a single country' => [
				'payment_method_id' => 'bancontact',
				'expected_result'   => [ Country_Code::BELGIUM ],
			],
			'Payment method supported in multiple countries' => [
				'payment_method_id' => 'sofort',
				'expected_result'   => [
					Country_Code::AUSTRIA,
					Country_Code::BELGIUM,
					Country_Code::GERMANY,
					Country_Code::NETHERLANDS,
					Country_Code::SPAIN,
				],
			],
			'Payment method with domestic restrictions (US)' => [
				'payment_method_id' => 'affirm',
				'expected_result'   => [ Country_Code::UNITED_STATES ],
				'account_country'   => Country_Code::UNITED_STATES,
			],
			'Payment method with domestic restrictions (CA)' => [
				'payment_method_id' => 'affirm',
				'expected_result'   => [ Country_Code::CANADA ],
				'account_country'   => Country_Code::CANADA,
			],
		];
	}
}
