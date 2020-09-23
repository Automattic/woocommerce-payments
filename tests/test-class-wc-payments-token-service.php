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
		WC_Payments::get_gateway()->update_option( 'test_mode', 'no' );
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
		$this->assertEquals( false, $token->get_meta( '_wcpay_test_mode' ) );
	}

	/**
	 * Test add token to user.
	 */
	public function test_add_token_to_user_test_mode() {
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
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
		$this->assertEquals( true, $token->get_meta( '_wcpay_test_mode' ) );
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

	public function test_woocommerce_get_customer_payment_tokens_removes_unavailable_tokens() {
		$token1            = WC_Helper_Token::create_token( 'pm_mock0' );
		$unavailable_token = WC_Helper_Token::create_token( 'pm_mock1' );
		$token2            = WC_Helper_Token::create_token( 'pm_mock2' );

		$token1->add_meta_data( '_wcpay_customer_id', 'cus_12345' );
		$token2->add_meta_data( '_wcpay_customer_id', 'cus_12345' );
		$token1->add_meta_data( '_wcpay_test_mode', false );
		$token2->add_meta_data( '_wcpay_test_mode', false );
		$unavailable_token->add_meta_data( '_wcpay_customer_id', 'cus_67890' );
		$unavailable_token->add_meta_data( '_wcpay_test_mode', false );

		$tokens = [
			$token1,
			$unavailable_token,
			$token2,
		];

		$this->mock_customer_service
			->expects( $this->any() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( 'cus_12345' )
			->willReturn( [] );

		$result        = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, 'woocommerce_payments' );
		$result_tokens = array_values( $result );
		$this->assertCount( 2, $result_tokens );
		$this->assertEquals( 'pm_mock0', $result_tokens[0]->get_token() );
		$this->assertEquals( 'pm_mock2', $result_tokens[1]->get_token() );
	}

	public function test_woocommerce_get_customer_payment_tokens_imports_tokens() {
		$token = WC_Helper_Token::create_token( 'pm_mock0' );
		$token->add_meta_data( '_wcpay_customer_id', 'cus_12345' );
		$token->add_meta_data( '_wcpay_test_mode', false );

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
		$this->assertCount( 3, $result_tokens );
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
		$token = WC_Helper_Token::create_token( 'pm_mock0' );
		$token->add_meta_data( '_wcpay_customer_id', 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->exactly( 2 ) )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( null );

		$result = $this->token_service->woocommerce_get_customer_payment_tokens( [ $token ], 1, 'woocommerce_payments' );
		$this->assertCount( 0, $result );
	}

	public function test_woocommerce_get_customer_payment_tokens_migrates_old_tokens() {
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );

		$old_token1       = WC_Helper_Token::create_token( 'pm_mock0' );
		$old_token2       = WC_Helper_Token::create_token( 'pm_mock1' );
		$token1           = WC_Helper_Token::create_token( 'pm_mock2' );
		$token2           = WC_Helper_Token::create_token( 'pm_mock3' );
		$wrong_mode_token = WC_Helper_Token::create_token( 'pm_mock4' );

		$token1->add_meta_data( '_wcpay_customer_id', 'cus_12345' );
		$token1->add_meta_data( '_wcpay_test_mode', true );
		$token2->add_meta_data( '_wcpay_customer_id', 'cus_67890' );
		$token2->add_meta_data( '_wcpay_test_mode', false );
		$wrong_mode_token->add_meta_data( '_wcpay_customer_id', 'cus_12345' );
		$wrong_mode_token->add_meta_data( '_wcpay_test_mode', false );

		$tokens = [ $old_token1, $old_token2, $token1, $token2, $wrong_mode_token ];

		$this->mock_customer_service
			->expects( $this->any() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( 'cus_12345' )
			->willReturn( [] );

		$result        = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, 'woocommerce_payments' );
		$result_tokens = array_values( $result );
		$this->assertCount( 4, $result_tokens );
		$this->assertEquals( 'pm_mock0', $result_tokens[0]->get_token() );
		$this->assertEquals( 'pm_mock1', $result_tokens[1]->get_token() );
		$this->assertEquals( 'pm_mock2', $result_tokens[2]->get_token() );
		$this->assertEquals( 'pm_mock4', $result_tokens[3]->get_token() );
		$this->assertEquals( 'cus_12345', $result_tokens[0]->get_meta( '_wcpay_customer_id' ) );
		$this->assertEquals( 'cus_12345', $result_tokens[1]->get_meta( '_wcpay_customer_id' ) );
		$this->assertEquals( 'cus_12345', $result_tokens[2]->get_meta( '_wcpay_customer_id' ) );
		$this->assertEquals( 'cus_12345', $result_tokens[3]->get_meta( '_wcpay_customer_id' ) );
		$this->assertEquals( true, $result_tokens[0]->get_meta( '_wcpay_test_mode' ) );
		$this->assertEquals( true, $result_tokens[1]->get_meta( '_wcpay_test_mode' ) );
		$this->assertEquals( true, $result_tokens[2]->get_meta( '_wcpay_test_mode' ) );
		$this->assertEquals( true, $result_tokens[3]->get_meta( '_wcpay_test_mode' ) );
	}

	public function test_woocommerce_get_customer_payment_tokens_does_not_throw_resource_missing() {
		$this->mock_customer_service
			->expects( $this->any() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( 'cus_12345' )
			->willThrowException( new WC_Payments_API_Exception( 'No such customer', 'resource_missing', 400 ) );

		$existing_tokens = [];
		try {
			$tokens = $this->token_service->woocommerce_get_customer_payment_tokens( $existing_tokens, 1, 'woocommerce_payments' );
			// We return $existing_tokens as the exception was handled in the function and not bubbled up.
			$this->assertEquals( $tokens, $existing_tokens );
		} catch ( WC_Payments_API_Exception $e ) {
			$this->fail( 'token_service->woocommerce_get_customer_payment_tokens did not handle the resource_missing code of WC_Payments_API_Exception.' );
		}
	}

	/**
	 * @dataProvider gateway_test_mode_provider
	 */
	public function test_woocommerce_get_customer_payment_tokens_migrates_incorrect_customers( $current_test_mode ) {
		WC_Payments::get_gateway()->update_option( 'test_mode', $current_test_mode ? 'yes' : 'no' );

		$actual_customer_test_mode = ! $current_test_mode;

		$no_customer_token = WC_Helper_Token::create_token( 'pm_mock1' );
		$wrong_mode_token  = WC_Helper_Token::create_token( 'pm_mock2' );

		$wrong_mode_token->add_meta_data( '_wcpay_customer_id', 'cus_12345' );
		$wrong_mode_token->add_meta_data( '_wcpay_test_mode', $current_test_mode );

		$tokens = [ $no_customer_token, $wrong_mode_token ];

		$this->mock_customer_service
			->expects( $this->at( 0 ) )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->at( 1 ) )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_67890' );

		$message = 'No such customer: \'cus_12345\'; a similar object exists in test mode, but a live mode key was used to make this request.';
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( 'cus_12345' )
			->willThrowException(
				new WC_Payments_API_Exception( $message, 'resource_missing', 400 )
			);

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'change_customer_mode' )
			->with( 1, 'cus_12345', $actual_customer_test_mode );

		$result = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, 'woocommerce_payments' );
		$this->assertCount( 0, $result );

		$no_customer_token = WC_Payment_tokens::get( $no_customer_token->get_id() );
		$wrong_mode_token  = WC_Payment_tokens::get( $wrong_mode_token->get_id() );
		$this->assertEquals( 'cus_12345', $no_customer_token->get_meta( '_wcpay_customer_id' ) );
		$this->assertEquals( $actual_customer_test_mode, $no_customer_token->get_meta( '_wcpay_test_mode' ) );
		$this->assertEquals( 'cus_12345', $wrong_mode_token->get_meta( '_wcpay_customer_id' ) );
		$this->assertEquals( $actual_customer_test_mode, $wrong_mode_token->get_meta( '_wcpay_test_mode' ) );
	}

	public function gateway_test_mode_provider() {
		return [
			[ true ],
			[ false ],
		];
	}
}
