<?php
/**
 * Class WC_Payments_Token_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Token_Service unit tests.
 */
class WC_Payments_Token_Service_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Token_Service
	 */
	private $token_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var int
	 */
	private $user_id = 0;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->user_id = get_current_user_id();
		wp_set_current_user( 1 );

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->token_service = new WC_Payments_Token_Service( $this->mock_api_client );
	}

	/**
	 * Post-test teardown
	 */
	public function tearDown() {
		wp_set_current_user( $this->user_id );
		parent::tearDown();
	}

	/**
	 * Test add token to user.
	 */
	public function test_add_token_to_user() {
		$mock_payment_method_id = 'pm_mock';
		$mock_payment_method    = [
			'id'   => $mock_payment_method_id,
			'card' => [
				'brand'     => 'visa',
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => 2026,
			],
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $mock_payment_method_id )
			->will( $this->returnValue( $mock_payment_method ) );

		$token = $this->token_service->add_token_to_user( $mock_payment_method_id, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( $mock_payment_method_id, $token->get_token() );
		$this->assertEquals( 'visa', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( '2026', $token->get_expiry_year() );
	}
}
