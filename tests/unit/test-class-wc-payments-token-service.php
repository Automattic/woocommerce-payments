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
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

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

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );

		$this->token_service = new WC_Payments_Token_Service( $this->mock_api_client, $this->mock_customer_service );
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
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'     => 'visa',
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => $expiry_year,
			],
		];

		$this->mock_customer_service
			->expects( $this->atLeastOnce() )
			->method( 'get_customer_id_by_user_id' )
			->with( 1 )
			->willReturn( 'cus_12345' );

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( 'visa', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( $expiry_year, $token->get_expiry_year() );
		$this->assertEquals( 'cus_12345', $token->get_meta( '_wcpay_customer_id' ) );
	}

	public function test_add_payment_method_to_user() {
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'     => 'visa',
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => $expiry_year,
			],
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( 'pm_mock' )
			->willReturn( $mock_payment_method );

		$token = $this->token_service->add_payment_method_to_user( $mock_payment_method['id'], wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( 'visa', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( $expiry_year, $token->get_expiry_year() );
	}

	public function test_woocommerce_payment_token_deleted() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'detach_payment_method' )
			->with( 'pm_mock' )
			->will( $this->returnValue( [] ) );

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock' );

		$this->token_service->woocommerce_payment_token_deleted( 'pm_mock', $token );
	}

	public function test_woocommerce_payment_token_deleted_other_gateway() {
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'detach_payment_method' );

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'another_gateway' );
		$token->set_token( 'pm_mock' );

		$this->token_service->woocommerce_payment_token_deleted( 'pm_mock', $token );
	}

	public function test_woocommerce_payment_token_set_default() {
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( 1 )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'set_default_payment_method_for_customer' )
			->with( 'cus_12345', 'pm_mock' );

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock' );
		$token->set_user_id( 1 );

		$this->token_service->woocommerce_payment_token_set_default( 'pm_mock', $token );
	}

	public function test_woocommerce_payment_token_set_default_other_gateway() {
		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'set_default_payment_method_for_customer' );

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'another_gateway' );
		$token->set_token( 'pm_mock' );

		$this->token_service->woocommerce_payment_token_set_default( 'pm_mock', $token );
	}

	public function test_woocommerce_payment_token_set_default_no_customer() {
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( 1 )
			->willReturn( null );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'set_default_payment_method_for_customer' );

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock' );
		$token->set_user_id( 1 );

		$this->token_service->woocommerce_payment_token_set_default( 'pm_mock', $token );
	}

	public function test_woocommerce_get_customer_payment_tokens() {
		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock0' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( 1 );
		$token->set_expiry_year( 2023 );
		$token->set_user_id( 1 );
		$token->set_default( true );
		$token->save();

		$tokens = [ $token ];

		$mock_payment_methods = [
			[
				'id'   => 'pm_mock1',
				'type' => 'card',
				'card' => [
					'brand'     => 'visa',
					'last4'     => '4242',
					'exp_month' => 6,
					'exp_year'  => 2026,
				],
			],
			[
				'id'   => 'pm_mock2',
				'type' => 'card',
				'card' => [
					'brand'     => 'master',
					'last4'     => '5665',
					'exp_month' => 4,
					'exp_year'  => 2031,
				],
			],
		];

		$this->mock_customer_service
			->expects( $this->any() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( 'cus_12345' )
			->willReturn( $mock_payment_methods );

		$result        = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, 'woocommerce_payments' );
		$result_tokens = array_values( $result );
		$this->assertEquals( 'pm_mock0', $result_tokens[0]->get_token() );
		$this->assertEquals( 'pm_mock1', $result_tokens[1]->get_token() );
		$this->assertEquals( 'pm_mock2', $result_tokens[2]->get_token() );
	}

	public function test_woocommerce_get_customer_payment_tokens_not_logged() {
		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$user_id = get_current_user_id();
		wp_set_current_user( 0 );

		$result = $this->token_service->woocommerce_get_customer_payment_tokens( [ new WC_Payment_Token_CC() ], 1, 'woocommerce_payments' );
		$this->assertEquals( [ new WC_Payment_Token_CC() ], $result );

		wp_set_current_user( $user_id );
	}

	public function test_woocommerce_get_customer_payment_tokens_other_gateway() {
		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$result = $this->token_service->woocommerce_get_customer_payment_tokens( [ new WC_Payment_Token_CC() ], 1, 'other_gateway' );
		$this->assertEquals( [ new WC_Payment_Token_CC() ], $result );
	}

	public function test_woocommerce_get_customer_payment_tokens_no_customer() {
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( null );

		$result = $this->token_service->woocommerce_get_customer_payment_tokens( [ new WC_Payment_Token_CC() ], 1, 'woocommerce_payments' );
		$this->assertEquals( [ new WC_Payment_Token_CC() ], $result );
	}
}
