<?php
/**
 * Class FeatureTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Payments_Account;
use WCPay\Internal\Payment\Factor;
use WCPay\Internal\Payment\Feature;

/**
 * New payment process as a feature class test.
 */
class FeatureTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var Feature
	 */
	private $sut;

	/**
	 * Cached account mock.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * Tests set-up.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->mock_account = $this->createMock( WC_Payments_Account::class );
		$this->sut          = new Feature( $this->mock_account );
	}

	/**
	 * Makes sure that the `NEW_PAYMENT_PROCESS` factor is accounted for.
	 *
	 * The factor is used as a base for the new payment process. Even when all other factors,
	 * provided to `should_use_new_payment_process_adds_factor` are false, this factor
	 * will still be checked.
	 *
	 * @dataProvider new_payment_process_factor_provider
	 */
	public function test_should_use_new_payment_process_adds_factor( $factor, $expected ) {
		$this->mock_account_factors( [ Factor::NEW_PAYMENT_PROCESS => $factor ] );

		$result = $this->sut->should_use_new_payment_process( [] );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Provider for `test_should_use_new_payment_process_adds_factor`.
	 *
	 * @return array
	 */
	public function new_payment_process_factor_provider() {
		return [
			[ null, false ],
			[ false, false ],
			[ true, true ],
		];
	}

	/**
	 * Tests that the feature returns false if a factor is **not present** in the account cache.
	 */
	public function test_should_use_new_payment_process_returns_false_with_missing_factor() {
		$this->mock_account_factors( [] );

		$result = $this->sut->should_use_new_payment_process( [ Factor::USE_SAVED_PM => true ] );
		$this->assertFalse( $result );
	}

	/**
	 * Tests that the feature returns false if a factor is **false** in the account cache.
	 */
	public function test_should_use_new_payment_process_returns_false_with_unavailable_factor() {
		$this->mock_account_factors( [ Factor::USE_SAVED_PM => false ] );

		$result = $this->sut->should_use_new_payment_process( [ Factor::USE_SAVED_PM => true ] );
		$this->assertFalse( $result );
	}

	/**
	 * Tests that the feature returns true when a factor is both present, and true in the account cache.
	 */
	public function test_should_use_new_payment_process_returns_true_with_available_factor() {
		$this->mock_account_factors( [ Factor::USE_SAVED_PM => true ] );

		$result = $this->sut->should_use_new_payment_process( [ Factor::USE_SAVED_PM => true ] );
		$this->assertTrue( $result );
	}

	/**
	 * Tests that the feature handles multiple flags properly,
	 * and returns false in case any of them is not available.
	 */
	public function test_should_use_new_payment_process_with_multiple_factors_returns_false() {
		$this->mock_account_factors(
			[
				Factor::USE_SAVED_PM        => true,
				Factor::SUBSCRIPTION_SIGNUP => false,
				Factor::WOOPAY_ENABLED      => true,
				Factor::PAYMENT_REQUEST     => false,
			]
		);

		$result = $this->sut->should_use_new_payment_process(
			[
				Factor::NO_PAYMENT          => false,
				Factor::USE_SAVED_PM        => true,
				Factor::SUBSCRIPTION_SIGNUP => true,
				Factor::WOOPAY_ENABLED      => true,
			]
		);
		$this->assertFalse( $result );
	}

	/**
	 * Tests that the feature handles multiple flags properly,
	 * and returns true when all factors are present.
	 */
	public function test_should_use_new_payment_process_with_multiple_factors_returns_true() {
		$this->mock_account_factors(
			[
				Factor::USE_SAVED_PM        => true,
				Factor::SUBSCRIPTION_SIGNUP => true,
				Factor::WOOPAY_ENABLED      => true,
				Factor::PAYMENT_REQUEST     => false,
			]
		);

		$result = $this->sut->should_use_new_payment_process(
			[
				Factor::NO_PAYMENT          => false,
				Factor::USE_SAVED_PM        => true,
				Factor::SUBSCRIPTION_SIGNUP => true,
				Factor::WOOPAY_ENABLED      => true,
			]
		);
		$this->assertTrue( $result );
	}

	/**
	 * Simulates specific factors, being returned by `WC_Payments_Account`.
	 * Unless provided, `NEW_PAYMENT_PROCESS` is always set to true.
	 * If `NEW_PAYMENT_PROCESS` is null, it will be unset from the array.
	 *
	 * @param array $factors The factors to simulate.
	 */
	private function mock_account_factors( $factors = [] ) {
		if ( ! array_key_exists( Factor::NEW_PAYMENT_PROCESS, $factors ) ) {
			$factors[ Factor::NEW_PAYMENT_PROCESS ] = true;
		} elseif ( is_null( $factors[ Factor::NEW_PAYMENT_PROCESS ] ) ) {
			unset( $factors[ Factor::NEW_PAYMENT_PROCESS ] );
		}

		$this->mock_account->expects( $this->once() )
			->method( 'get_new_payment_process_enabled_factors' )
			->willreturn( $factors );
	}
}
