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
use WC_Subscriptions;

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
	 * Currency filter callback for removal in teardown.
	 *
	 * @var callable|null
	 */
	private $currency_filter_callback = null;

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

		$payment_method_definitions = [
			\WCPay\PaymentMethods\Configs\Definitions\AffirmDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\AfterpayDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\BancontactDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\BecsDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\EpsDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\GiropayDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\IdealDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\LinkDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\P24Definition::class,
			\WCPay\PaymentMethods\Configs\Definitions\SepaDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\SofortDefinition::class,
			\WCPay\PaymentMethods\Configs\Definitions\KlarnaDefinition::class,
		];

		$payment_method_classes = [
			CC_Payment_Method::class,
		];

		foreach ( $payment_method_definitions as $definition_class ) {
			/** @var UPE_Payment_Method|MockObject */
			$mock_payment_method = $this->getMockBuilder( UPE_Payment_Method::class )
				->setConstructorArgs( [ $this->mock_token_service, $definition_class ] )
				->onlyMethods( [] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;
		}

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
		if ( null !== $this->currency_filter_callback ) {
			remove_filter( 'woocommerce_currency', $this->currency_filter_callback, PHP_INT_MAX );
			$this->currency_filter_callback = null;
		}
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

	public function test_klarna_get_countries_with_eu_country_and_eu_currency() {
		$this->currency_filter_callback = function () {
			return 'EUR';
		};
		add_filter( 'woocommerce_currency', $this->currency_filter_callback, PHP_INT_MAX );

		$payment_method = $this->mock_payment_methods['klarna'];

		$this->mock_wcpay_account->method( 'get_cached_account_data' )->willReturn(
			[
				'country' => 'DE',
			]
		);

		$this->assertEquals(
			[
				'AT',
				'BE',
				'FI',
				'FR',
				'DE',
				'IE',
				'IT',
				'NL',
				'ES',
			],
			$payment_method->get_countries()
		);
	}

	public function test_klarna_get_countries_with_eu_country_and_non_eu_currency() {
		$this->currency_filter_callback = function () {
			return 'AUD';
		};
		add_filter( 'woocommerce_currency', $this->currency_filter_callback, PHP_INT_MAX );

		$payment_method = $this->mock_payment_methods['klarna'];

		$this->mock_wcpay_account->method( 'get_cached_account_data' )->willReturn(
			[
				'country' => 'IT',
			]
		);

		$this->assertEquals(
			[
				'NONE_SUPPORTED',
			],
			$payment_method->get_countries()
		);
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

	/**
	 * Test that non-reusable payment methods are enabled when manual renewals are accepted.
	 */
	public function test_is_enabled_at_checkout_allows_non_reusable_when_manual_renewals_accepted() {
		// Arrange: Mock a non-reusable payment method (iDEAL).
		$ideal_method = $this->getMockBuilder( UPE_Payment_Method::class )
			->onlyMethods( [ 'is_reusable', 'is_subscription_item_in_cart' ] )
			->disableOriginalConstructor()
			->getMock();
		$ideal_method->method( 'is_reusable' )->willReturn( false );
		$ideal_method->method( 'is_subscription_item_in_cart' )->willReturn( true );

		// Enable manual renewals.
		WC_Subscriptions::set_wcs_is_manual_renewal_enabled(
			function () {
				return true;
			}
		);

		// Act.
		$result = $ideal_method->is_enabled_at_checkout( Country_Code::NETHERLANDS, true );

		// Assert.
		$this->assertTrue( $result, 'Non-reusable payment methods should be enabled when manual renewals are accepted' );

		// Cleanup.
		WC_Subscriptions::set_wcs_is_manual_renewal_enabled( null );
	}

	/**
	 * Test that non-reusable payment methods are disabled for subscription checkout when manual renewals are not accepted.
	 */
	public function test_is_enabled_at_checkout_disables_non_reusable_without_manual_renewals() {
		// Arrange: Mock a non-reusable payment method.
		$ideal_method = $this->getMockBuilder( UPE_Payment_Method::class )
			->onlyMethods( [ 'is_reusable', 'is_subscription_item_in_cart' ] )
			->disableOriginalConstructor()
			->getMock();
		$ideal_method->method( 'is_reusable' )->willReturn( false );
		$ideal_method->method( 'is_subscription_item_in_cart' )->willReturn( true );

		// Disable manual renewals.
		WC_Subscriptions::set_wcs_is_manual_renewal_enabled(
			function () {
				return false;
			}
		);

		// Act.
		$result = $ideal_method->is_enabled_at_checkout( Country_Code::NETHERLANDS, true );

		// Assert.
		$this->assertFalse( $result, 'Non-reusable payment methods should be disabled for subscriptions when manual renewals are not accepted' );

		// Cleanup.
		WC_Subscriptions::set_wcs_is_manual_renewal_enabled( null );
	}
}
