<?php
/**
 * Class FraudPreventionServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WC_Payments_Account;
use WCPay\Internal\Service\FraudPreventionService;
use WCPay\Internal\Service\SessionService;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * FraudPreventionService tests
 */
class FraudPreventionServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var FraudPreventionService
	 */
	private $sut;

	/**
	 * SUT with some methods mocked.
	 *
	 * @var FraudPreventionService|MockObject
	 */
	private $mock_sut;

	/**
	 * @var SessionService|MockObject
	 */
	private $mock_session_service;

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account_service;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_session_service = $this->createMock( SessionService::class );
		$this->mock_account_service = $this->createMock( WC_Payments_Account::class );

		$this->sut = new FraudPreventionService(
			$this->mock_session_service,
			$this->mock_account_service
		);
	}

	public function provider_enabled_options(): array {
		return [
			[ true, true ],
			[ false, false ],
		];
	}

	/**
	 * @dataProvider provider_enabled_options
	 */
	public function test_is_enabled( $account_flag, $return_value ) {
		$this->mock_account_service
			->expects( $this->once() )
			->method( 'is_card_testing_protection_eligible' )
			->willReturn( $account_flag );

		$this->assertSame( $return_value, $this->sut->is_enabled() );
	}

	public function test_get_token_from_session() {
		$token_stub = 'test-token';
		$this->mock_session_service
			->expects( $this->once() )
			->method( 'get' )
			->with( FraudPreventionService::TOKEN_NAME )
			->willReturn( $token_stub );

		$this->assertSame( $token_stub, $this->sut->get_token() );
	}

	public function test_get_token_on_first_page_load() {
		$new_token_stub = 'new-token';
		$this->mock_sut = $this->getMockBuilder( FraudPreventionService::class )
			->setConstructorArgs( [ $this->mock_session_service, $this->mock_account_service ] )
			->onlyMethods( [ 'regenerate_token' ] )
			->getMock();

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'get' )
			->with( FraudPreventionService::TOKEN_NAME )
			->willReturn( null );

		$this->mock_sut
			->expects( $this->once() )
			->method( 'regenerate_token' )
			->willReturn( $new_token_stub );

		$this->assertSame( $new_token_stub, $this->mock_sut->get_token() );
	}

	public function test_regenerate_token() {
		$this->mock_session_service
			->expects( $this->once() )
			->method( 'set' )
			->with( FraudPreventionService::TOKEN_NAME, $this->isType( 'string' ) );

		$token_value = $this->sut->regenerate_token();

		$this->assertIsString( $token_value );
	}

	public function test_verify_token_returns_true_when_is_disabled() {
		// Prepare mock for method `is_enabled`.
		$this->mock_account_service
			->expects( $this->once() )
			->method( 'is_card_testing_protection_eligible' )
			->willReturn( false );

		$is_valid = $this->sut->verify_token( 'any-token' );

		$this->assertTrue( $is_valid );
	}

	public function provider_verify_token_when_is_enabled(): array {
		return [
			'Both tokens null'         => [ null, null, false ],
			'Only provided token null' => [ 'session-token', null, false ],
			'Only session token null ' => [ null, 'provided-token', false ],
			'Two tokens mismatched'    => [ 'session-token', 'provided-token', false ],
			'Valid tokens'             => [ 'valid-token', 'valid-token', true ],
		];
	}

	/**
	 * @dataProvider provider_verify_token_when_is_enabled
	 */
	public function test_verify_token_when_is_enabled(
		?string $session_token,
		?string $provided_token,
		bool $expected
	) {
		// Prepare mock for method `is_enabled`.
		$this->mock_account_service
			->expects( $this->once() )
			->method( 'is_card_testing_protection_eligible' )
			->willReturn( true );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'get' )
			->with( FraudPreventionService::TOKEN_NAME )
			->willReturn( $session_token );

		$result = $this->sut->verify_token( $provided_token );

		$this->assertSame( $expected, $result );
	}
}
