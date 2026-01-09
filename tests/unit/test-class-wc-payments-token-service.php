<?php
/**
 * Class WC_Payments_Token_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Payment_Method;

/**
 * WC_Payments_Token_Service unit tests.
 */
class WC_Payments_Token_Service_Test extends WCPAY_UnitTestCase {

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
	 * @var Database_Cache|MockObject
	 */
	protected $mock_cache;

	/**
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $original_gateway;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->original_gateway = WC_Payments::get_gateway();

		$this->user_id = get_current_user_id();
		wp_set_current_user( 1 );

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		$this->token_service = new WC_Payments_Token_Service( $this->mock_api_client, $this->mock_customer_service );
	}

	/**
	 * Post-test teardown
	 */
	public function tear_down() {
		wp_set_current_user( $this->user_id );
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );
		WC_Payments::set_gateway( $this->original_gateway );
		parent::tear_down();
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
			'type' => Payment_Method::CARD,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( 'visa', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( $expiry_year, $token->get_expiry_year() );
	}

	public function test_add_cobranded_token_to_user_with_preferred_network() {
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'     => 'visa',
				'networks'  => [ 'preferred' => 'cartes_bancaires' ],
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => $expiry_year,
			],
			'type' => Payment_Method::CARD,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( 'cartes_bancaires', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( $expiry_year, $token->get_expiry_year() );
	}

	public function test_add_cobranded_token_to_user_with_display_brand() {
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'         => 'visa',
				'display_brand' => 'cartes_bancaires',
				'last4'         => '4242',
				'exp_month'     => 6,
				'exp_year'      => $expiry_year,
			],
			'type' => Payment_Method::CARD,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( 'cartes_bancaires', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( $expiry_year, $token->get_expiry_year() );
	}

	/**
	 * Test add SEPA token to user.
	 */
	public function test_add_token_to_user_for_sepa() {
		$mock_payment_method = [
			'id'         => 'pm_mock',
			'sepa_debit' => [
				'last4' => '3000',
			],
			'type'       => Payment_Method::SEPA,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments_sepa_debit', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( '3000', $token->get_last4() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_SEPA::class, $token );
	}

	/**
	 * Test add SEPA token to user with deferred intent creation UPE.
	 */
	public function test_add_token_to_user_for_sepa_deferred_intent_creation_upe() {
		$mock_payment_method = [
			'id'         => 'pm_mock',
			'sepa_debit' => [
				'last4' => '3000',
			],
			'type'       => Payment_Method::SEPA,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments_sepa_debit', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( '3000', $token->get_last4() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_SEPA::class, $token );
	}

	/**
	 * Test add SEPA token to user with deferred intent UPE.
	 */
	public function test_add_token_to_user_for_sepa_deferred_upe() {
		$mock_payment_method = [
			'id'         => 'pm_mock',
			'sepa_debit' => [
				'last4' => '3000',
			],
			'type'       => Payment_Method::SEPA,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments_sepa_debit', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( '3000', $token->get_last4() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_SEPA::class, $token );
	}

	/**
	 * Test add Link token to user.
	 */
	public function test_add_token_to_user_for_link() {
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'link' => [
				'email' => 'test@test.com',
			],
			'type' => Payment_Method::LINK,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertSame( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertSame( 1, $token->get_user_id() );
		$this->assertSame( 'pm_mock', $token->get_token() );
		$this->assertSame( 'test@test.com', $token->get_email() );
		$this->assertSame( '***test@test.com', $token->get_redacted_email() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_Link::class, $token );
	}

	/**
	 * Test add Link token to user with split UPE.
	 */
	public function test_add_token_to_user_for_link_split_upe() {
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'link' => [
				'email' => 'test@test.com',
			],
			'type' => Payment_Method::LINK,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertSame( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertSame( 1, $token->get_user_id() );
		$this->assertSame( 'pm_mock', $token->get_token() );
		$this->assertSame( 'test@test.com', $token->get_email() );
		$this->assertSame( '***test@test.com', $token->get_redacted_email() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_Link::class, $token );
	}

	/**
	 * Test add Link token to user with deferred intent UPE.
	 */
	public function test_add_token_to_user_for_link_deferred_upe() {
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'link' => [
				'email' => 'test@test.com',
			],
			'type' => Payment_Method::LINK,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertSame( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertSame( 1, $token->get_user_id() );
		$this->assertSame( 'pm_mock', $token->get_token() );
		$this->assertSame( 'test@test.com', $token->get_email() );
		$this->assertSame( '***test@test.com', $token->get_redacted_email() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_Link::class, $token );
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
			'type' => Payment_Method::CARD,
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

	public function test_add_cobranded_payment_method_to_user_with_preferred_network() {
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'     => 'visa',
				'networks'  => [ 'preferred' => 'cartes_bancaires' ],
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => $expiry_year,
			],
			'type' => Payment_Method::CARD,
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
		$this->assertEquals( 'cartes_bancaires', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( $expiry_year, $token->get_expiry_year() );
	}

	public function test_add_cobranded_payment_method_to_user_with_display_brand() {
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'         => 'visa',
				'display_brand' => 'cartes_bancaires',
				'last4'         => '4242',
				'exp_month'     => 6,
				'exp_year'      => $expiry_year,
			],
			'type' => Payment_Method::CARD,
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
		$this->assertEquals( 'cartes_bancaires', $token->get_card_type() );
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
		$token  = $this->generate_sepa_token( 'pm_mock0' );
		$tokens = [ $token ];

		$mock_payment_methods = [
			$this->generate_card_pm_response( 'pm_mock1' ),
			$this->generate_card_pm_response( 'pm_mock2' ),
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

	public function test_woocommerce_get_customer_payment_tokens_multiple_tokens_multiple_types() {
		$customer_id     = 'cus_12345';
		$payment_methods = [ Payment_Method::CARD, Payment_Method::SEPA, Payment_Method::LINK ];

		$gateway = WC_Payments::get_gateway();
		$gateway->settings['upe_enabled_payment_method_ids'] = $payment_methods;

		// Array keys should match the database ID of the token.
		$card_tokens        = [
			1 => $this->generate_card_token( 'pm_111', 1 ),
			2 => $this->generate_card_token( 'pm_222', 2 ),
		];
		$sepa_tokens        = [
			3 => $this->generate_sepa_token( 'pm_333', 3 ),
			4 => $this->generate_sepa_token( 'pm_444', 4 ),
		];
		$stripe_link_tokens = [
			5 => $this->generate_link_token( 'pm_555', 5 ),
			6 => $this->generate_link_token( 'pm_666', 6 ),
		];

		$all_saved_tokens = $card_tokens + $sepa_tokens + $stripe_link_tokens;

		$this->mock_customer_service
			->expects( $this->exactly( 2 ) )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( $customer_id );

		// Expect a call for each payment method, and return an array with consecutive keys.
		$this->mock_customer_service
			->expects( $this->exactly( 3 ) )
			->method( 'get_payment_methods_for_customer' )
			->withConsecutive(
				[ $customer_id, Payment_Method::CARD ],
				[ $customer_id, Payment_Method::LINK ]
			)
			->willReturnOnConsecutiveCalls(
				[
					$this->generate_card_pm_response( 'pm_111' ),
					$this->generate_card_pm_response( 'pm_222' ),
				],
				[
					$this->generate_link_pm_response( 'pm_555' ),
					$this->generate_link_pm_response( 'pm_666' ),
				],
				[
					$this->generate_sepa_pm_response( 'pm_333' ),
					$this->generate_sepa_pm_response( 'pm_444' ),
				]
			);

		$card_and_link_result = $this->token_service->woocommerce_get_customer_payment_tokens( $all_saved_tokens, 1, WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$sepa_result          = $this->token_service->woocommerce_get_customer_payment_tokens( $all_saved_tokens, 1, WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . Payment_Method::SEPA );

		$this->assertSame(
			array_keys( $card_tokens + $stripe_link_tokens ),
			array_keys( $card_and_link_result )
		);

		$this->assertSame(
			array_keys( $sepa_tokens ),
			array_keys( $sepa_result )
		);
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

	public function test_woocommerce_get_customer_payment_tokens_failed_to_load_payment_methods_for_customer() {
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->willThrowException( new Exception( 'Failed to get payment methods.' ) );

		$result = $this->token_service->woocommerce_get_customer_payment_tokens( [ new WC_Payment_Token_CC() ], 1, 'woocommerce_payments' );
		$this->assertEquals( [ new WC_Payment_Token_CC() ], $result );
	}

	public function test_woocommerce_get_customer_payment_tokens_card_token_not_added_twice_for_non_gateway_specific_request() {
		$gateway_id      = '';
		$token           = $this->generate_card_token( 'pm_mock0' );
		$tokens          = [ $token ];
		$payment_methods = [ Payment_Method::CARD ];

		$gateway = WC_Payments::get_gateway();
		$gateway->settings['upe_enabled_payment_method_ids'] = $payment_methods;

		$mock_payment_methods = [
			$this->generate_card_pm_response( 'pm_mock0' ),
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

		$result        = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, $gateway_id );
		$result_tokens = array_values( $result );

		$this->assertEquals( 1, count( $result_tokens ) );
		$this->assertEquals( 'pm_mock0', $result_tokens[0]->get_token() );
	}

	public function test_woocommerce_get_customer_payment_tokens_not_added_twice_for_non_gateway_specific_request() {
		$gateway_id      = '';
		$card_token      = $this->generate_card_token( 'pm_mock0' );
		$sepa_token      = $this->generate_sepa_token( 'pm_mock1' );
		$tokens          = [ $card_token, $sepa_token ];
		$payment_methods = [ Payment_Method::CARD, Payment_Method::SEPA ];

		$gateway = WC_Payments::get_gateway();
		$gateway->settings['upe_enabled_payment_method_ids'] = $payment_methods;

		$this->mock_customer_service
			->expects( $this->any() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_12345' );

		$this->mock_customer_service
			->expects( $this->exactly( 2 ) )
			->method( 'get_payment_methods_for_customer' )
			->withConsecutive(
				[ 'cus_12345', Payment_Method::CARD ],
				[ 'cus_12345', Payment_Method::SEPA ]
			)
			->willReturnOnConsecutiveCalls(
				[
					$this->generate_card_pm_response( 'pm_mock0' ),
					$this->generate_card_pm_response( 'pm_222' ),
				],
				[
					$this->generate_sepa_pm_response( 'pm_mock1' ),
					$this->generate_sepa_pm_response( 'pm_444' ),
				]
			);

		$result        = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, $gateway_id );
		$result_tokens = array_values( $result );

		$this->assertEquals( 4, count( $result_tokens ) );
		$this->assertEquals( 'pm_mock0', $result_tokens[0]->get_token() );
		$this->assertEquals( 'pm_mock1', $result_tokens[1]->get_token() );
		$this->assertEquals( 'pm_222', $result_tokens[2]->get_token() );
		$this->assertEquals( 'pm_444', $result_tokens[3]->get_token() );
	}

	public function test_woocommerce_get_customer_payment_tokens_payment_methods_only_for_retrievable_types() {
		$enabled_upe_payment_methods = [
			Payment_Method::CARD,
			Payment_Method::SEPA,
			Payment_Method::LINK,
			Payment_Method::BECS,
			Payment_Method::EPS,
			Payment_Method::GIROPAY,
			Payment_Method::IDEAL,
			Payment_Method::P24,
			Payment_Method::SOFORT,
		];
		$gateway                     = WC_Payments::get_gateway();
		$gateway->settings['upe_enabled_payment_method_ids'] = $enabled_upe_payment_methods;
		$tokens      = [];
		$customer_id = 'cus_12345';

		$this->mock_customer_service
			->expects( $this->any() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( $customer_id );

		$this->mock_customer_service
			->expects( $this->exactly( 3 ) )
			->method( 'get_payment_methods_for_customer' )
			->withConsecutive(
				[ $customer_id, Payment_Method::CARD ],
				[ $customer_id, Payment_Method::LINK ],
				[ $customer_id, Payment_Method::SEPA ],
			)
			->willReturnOnConsecutiveCalls(
				[
					$this->generate_card_pm_response( 'pm_mock0' ),
				],
				[
					$this->generate_link_pm_response( 'pm_mock_3' ),
				],
				[
					$this->generate_sepa_pm_response( 'pm_mock_2' ),
				],
			);

		$this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . Payment_Method::SEPA );
	}

	/**
	 * @dataProvider valid_and_invalid_payment_methods_for_comparison_provider
	 */
	public function test_is_valid_payment_method_type_for_gateway( $payment_method_type, $gateway_id, $expected_result ) {
		$this->assertEquals(
			$expected_result,
			$this->token_service->is_valid_payment_method_type_for_gateway( $payment_method_type, $gateway_id )
		);
	}

	public function valid_and_invalid_payment_methods_for_comparison_provider() {
		return [
			[ 'card', 'woocommerce_payments', true ],
			[ 'sepa_debit', 'woocommerce_payments_sepa_debit', true ],
			[ 'link', 'woocommerce_payments', true ],
			[ 'card', 'card', false ],
			[ 'card', 'woocommerce_payments_bancontact', false ],
		];
	}

	private function generate_card_pm_response( $stripe_id ) {
		return [
			'type' => Payment_Method::CARD,
			'id'   => $stripe_id,
			'card' => [
				'brand'     => 'visa',
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => '2111',
			],
		];
	}

	private function generate_sepa_pm_response( $stripe_id ) {
		return [
			'type'       => Payment_Method::SEPA,
			'id'         => $stripe_id,
			'sepa_debit' => [
				'last4' => '1234',
			],
		];
	}

	private function generate_link_pm_response( $stripe_id ) {
		return [
			'type' => Payment_Method::LINK,
			'id'   => $stripe_id,
			'link' => [
				'email' => 'test@test.com',
			],
		];
	}

	private function generate_card_token( $stripe_id, $wp_id = 0 ) {
		$token = new WC_Payment_Token_CC();
		$token->set_id( $wp_id );
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( $stripe_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( 1 );
		$token->set_expiry_year( 2023 );
		$token->set_user_id( 1 );
		$token->set_default( true );
		$token->save();
		return $token;
	}

	private function generate_sepa_token( $stripe_id, $wp_id = 0 ) {
		$token = new WC_Payment_Token_WCPay_SEPA();
		$token->set_id( $wp_id );
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( $stripe_id );
		$token->set_last4( '3000' );
		$token->save();
		return $token;
	}

	private function generate_link_token( $stripe_id, $wp_id = 0 ) {
		$token = new WC_Payment_Token_WCPay_Link();
		$token->set_id( $wp_id );
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( $stripe_id );
		$token->set_email( 'test@test.com' );
		$token->save();
		return $token;
	}

	/**
	 * Test clear_cached_payment_methods_for_user method.
	 */
	public function test_clear_cached_payment_methods_for_user() {
		$user_id     = 1;
		$cache_key   = '_wcpay_payment_methods';
		$cached_data = [
			'customer_id'         => 'cus_12345',
			'payment_method_card' => [
				$this->generate_card_pm_response( 'pm_test1' ),
				$this->generate_card_pm_response( 'pm_test2' ),
			],
		];

		// Add cached data to user meta.
		update_user_meta( $user_id, $cache_key, $cached_data );

		// Clear cached payment methods for user.
		$this->token_service->clear_cached_payment_methods_for_user( $user_id );

		// Verify cached data is cleared.
		$this->assertEmpty( get_user_meta( $user_id, $cache_key, true ) );
	}

	public function provider_clearing_with_network_saved_cards_enabled(): array {
		return [
			'clear_cached_payment_methods_for_user' => [
				'user_id' => 1, // specific user.
			],
			'clear_all_cached_payment_methods'      => [
				'user_id' => null, // all users.
			],
		];
	}

	/**
	 * Test `clear_cached_payment_methods_for_user` and `clear_all_cached_payment_methods` with network saved cards enabled.
	 *
	 * @dataProvider provider_clearing_with_network_saved_cards_enabled
	 * @param int|null $user_id The user ID.
	 */
	public function test_clearing_with_network_saved_cards_enabled( ?int $user_id = null ) {
		$user_id     = 1;
		$cached_data = [
			'customer_id'         => 'cus_12345',
			'payment_method_card' => [
				$this->generate_card_pm_response( 'pm_test1' ),
			],
		];

		// Add cached data to user meta.
		update_user_meta( $user_id, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, $cached_data );

		// Mock network saved cards enabled using the filter.
		add_filter( 'wcpay_force_network_saved_cards', '__return_true' );

		if ( $user_id > 0 ) {
			// Clear cached payment methods for user.
			$this->token_service->clear_cached_payment_methods_for_user( $user_id );
		} else {
			// Clear all cached payment methods for all users.
			$this->token_service->clear_all_cached_payment_methods();
		}

		// Verify cached data still exists (should not be cleared when network saved cards is enabled).
		$this->assertEquals( $cached_data, get_user_meta( $user_id, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, true ) );

		// Clean up the filter.
		remove_filter( 'wcpay_force_network_saved_cards', '__return_true' );
	}

	/**
	 * Test clear_all_cached_payment_methods method.
	 */
	public function test_clear_all_cached_payment_methods() {
		$user_id_1     = 1;
		$user_id_2     = 2;
		$cached_data_1 = [
			'customer_id'         => 'cus_12345',
			'payment_method_card' => [
				$this->generate_card_pm_response( 'pm_test1' ),
			],
		];
		$cached_data_2 = [
			'customer_id'         => 'cus_67890',
			'payment_method_card' => [
				$this->generate_card_pm_response( 'pm_test2' ),
			],
		];

		// Add cached data to multiple users.
		update_user_meta( $user_id_1, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, $cached_data_1 );
		update_user_meta( $user_id_2, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, $cached_data_2 );

		// Add some legacy cached data in options table.
		update_option( 'wcpay_pm_legacy_1', 'legacy_data_1' );
		update_option( 'wcpay_pm_legacy_2', 'legacy_data_2' );
		update_option( 'wcpay_other_option', 'should_not_be_deleted' );

		// Clear all cached payment methods.
		$this->token_service->clear_all_cached_payment_methods();

		// Clear WordPress object cache to ensure we get fresh data from database.
		wp_cache_flush();

		// Verify all cached data is cleared.
		$this->assertEmpty( get_user_meta( $user_id_1, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, true ) );
		$this->assertEmpty( get_user_meta( $user_id_2, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, true ) );
		$this->assertFalse( get_option( 'wcpay_pm_legacy_1' ) );
		$this->assertFalse( get_option( 'wcpay_pm_legacy_2' ) );
		// Verify non-payment method options are not deleted.
		$this->assertEquals( 'should_not_be_deleted', get_option( 'wcpay_other_option' ) );
	}

	/**
	 * Test get_payment_methods_from_stripe method with cached data.
	 */
	public function test_get_payment_methods_from_stripe_with_cached_data() {
		$user_id                = 1;
		$customer_id            = 'cus_12345';
		$gateway_id             = 'woocommerce_payments';
		$cached_payment_methods = [
			$this->generate_card_pm_response( 'pm_cached1' ),
			$this->generate_card_pm_response( 'pm_cached2' ),
		];
		$cached_data            = [
			'customer_id'         => $customer_id,
			'payment_method_card' => $cached_payment_methods,
		];

		// Add cached data to user meta.
		update_user_meta( $user_id, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, $cached_data );

		// Mock gateway to return only card payment methods.
		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ Payment_Method::CARD ] );
		WC_Payments::set_gateway( $mock_gateway );

		// Verify customer service is not called (since we're using cached data).
		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_payment_methods_for_customer' );

		$result = $this->call_sut_method( 'get_payment_methods_from_stripe', $user_id, $customer_id, $gateway_id );

		// Verify cached data is returned.
		$this->assertEquals( $cached_payment_methods, $result );
	}

	/**
	 * Test get_payment_methods_from_stripe method with different customer ID (cache miss).
	 */
	public function test_get_payment_methods_from_stripe_with_different_customer_id() {
		$user_id                = 1;
		$customer_id            = 'cus_12345';
		$new_customer_id        = 'cus_67890';
		$gateway_id             = 'woocommerce_payments';
		$cached_payment_methods = [
			$this->generate_card_pm_response( 'pm_cached1' ),
		];
		$cached_data            = [
			'customer_id'         => $customer_id,
			'payment_method_card' => $cached_payment_methods,
		];

		// Add cached data with different customer ID.
		update_user_meta( $user_id, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, $cached_data );

		$new_payment_methods = [
			$this->generate_card_pm_response( 'pm_new1' ),
			$this->generate_card_pm_response( 'pm_new2' ),
		];

		// Mock gateway to return enabled payment methods.
		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ Payment_Method::CARD ] );
		WC_Payments::set_gateway( $mock_gateway );

		// Mock customer service to return new payment methods.
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( $new_customer_id, Payment_Method::CARD )
			->willReturn( $new_payment_methods );

		$result = $this->call_sut_method( 'get_payment_methods_from_stripe', $user_id, $new_customer_id, $gateway_id );

		// Verify new payment methods are returned.
		$this->assertEquals( $new_payment_methods, $result );

		// Verify cache is updated with new customer ID and payment methods.
		$updated_cache = get_user_meta( $user_id, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, true );
		$this->assertEquals( $new_customer_id, $updated_cache['customer_id'] );
		$this->assertEquals( $new_payment_methods, $updated_cache['payment_method_card'] );
	}

	/**
	 * Test get_payment_methods_from_stripe method with no cached data.
	 */
	public function test_get_payment_methods_from_stripe_with_no_cached_data() {
		$user_id      = 1;
		$customer_id  = 'cus_12345';
		$gateway_id   = 'woocommerce_payments';
		$card_methods = [ $this->generate_card_pm_response( 'pm_new1' ) ];
		$link_methods = [ $this->generate_link_pm_response( 'pm_new2' ) ];

		// Mock gateway to return enabled payment methods.
		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ Payment_Method::CARD, Payment_Method::LINK ] );
		WC_Payments::set_gateway( $mock_gateway );

		// Mock customer service to return payment methods.
		$this->mock_customer_service
			->expects( $this->exactly( 2 ) )
			->method( 'get_payment_methods_for_customer' )
			->withConsecutive(
				[ $customer_id, Payment_Method::CARD ],
				[ $customer_id, Payment_Method::LINK ]
			)
			->willReturnOnConsecutiveCalls(
				$card_methods,
				$link_methods
			);

		$result = $this->call_sut_method( 'get_payment_methods_from_stripe', $user_id, $customer_id, $gateway_id );

		// Verify payment methods are returned.
		$this->assertEquals( array_merge( $card_methods, $link_methods ), $result );

		// Verify cache is created with payment methods by type.
		$cached_data = get_user_meta( $user_id, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, true );
		$this->assertEquals( $customer_id, $cached_data['customer_id'] );
		$this->assertEquals( $card_methods, $cached_data['payment_method_card'] );
		$this->assertEquals( $link_methods, $cached_data['payment_method_link'] );
	}

	/**
	 * Test get_payment_methods_from_stripe method with SEPA gateway.
	 */
	public function test_get_payment_methods_from_stripe_with_sepa_gateway() {
		$user_id         = 1;
		$customer_id     = 'cus_12345';
		$gateway_id      = 'woocommerce_payments_sepa_debit';
		$payment_methods = [
			$this->generate_sepa_pm_response( 'pm_sepa1' ),
		];

		// Mock gateway to return enabled payment methods.
		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ Payment_Method::SEPA ] );
		WC_Payments::set_gateway( $mock_gateway );

		// Mock customer service to return SEPA payment methods.
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( $customer_id, Payment_Method::SEPA )
			->willReturn( $payment_methods );

		$result = $this->call_sut_method( 'get_payment_methods_from_stripe', $user_id, $customer_id, $gateway_id );

		// Verify SEPA payment methods are returned.
		$this->assertEquals( $payment_methods, $result );

		// Verify cache is created with correct payment method type key.
		$cached_data = get_user_meta( $user_id, WC_Payments_Token_Service::CACHED_PAYMENT_METHODS_META_KEY, true );
		$this->assertEquals( $customer_id, $cached_data['customer_id'] );
		$this->assertEquals( $payment_methods, $cached_data['payment_method_sepa_debit'] );
	}

	private function call_sut_method( $method_name, $user_id, $customer_id, $gateway_id ) {
		// Use reflection to access private method.
		$reflection = new ReflectionClass( $this->token_service );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		// Call the private method.
		return $method->invoke( $this->token_service, $user_id, $customer_id, $gateway_id );
	}

	/**
	 * Ensures token added with Google Pay wallet metadata.
	 */
	public function test_add_token_to_user_with_google_pay_wallet() {
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'     => 'visa',
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => $expiry_year,
				'wallet'    => [
					'type' => 'google_pay',
				],
			],
			'type' => Payment_Method::CARD,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( 'visa', $token->get_card_type() );
		$this->assertEquals( '4242', $token->get_last4() );
		$this->assertEquals( '06', $token->get_expiry_month() );
		$this->assertEquals( $expiry_year, $token->get_expiry_year() );
		$this->assertEquals( 'google_pay', $token->get_meta( '_wcpay_wallet_type', true ) );
	}

	/**
	 * Ensures token added without wallet metadata.
	 */
	public function test_add_token_to_user_without_wallet() {
		$expiry_year         = intval( gmdate( 'Y' ) ) + 1;
		$mock_payment_method = [
			'id'   => 'pm_mock',
			'card' => [
				'brand'     => 'visa',
				'last4'     => '4242',
				'exp_month' => 6,
				'exp_year'  => $expiry_year,
			],
			'type' => Payment_Method::CARD,
		];

		$token = $this->token_service->add_token_to_user( $mock_payment_method, wp_get_current_user() );

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( '', $token->get_meta( '_wcpay_wallet_type', true ) );
	}

	/**
	 * Ensures get_account_saved_payment_methods_list_item_wallet returns wallet display name.
	 */
	public function test_get_account_saved_payment_methods_list_item_wallet_google_pay() {
		// Create a mock payment method that returns the title.
		$mock_payment_method = $this->createMock( WCPay\Payment_Methods\UPE_Payment_Method::class );
		$mock_payment_method->method( 'get_title' )->willReturn( 'Google Pay' );

		// Use Reflection to inject the mock into WC_Payments' payment method map.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_method_map' );
		$property->setAccessible( true );
		$original_map          = $property->getValue();
		$new_map               = $original_map;
		$new_map['google_pay'] = $mock_payment_method;
		$property->setValue( null, $new_map );

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_user_id( 1 );
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2030' );
		$token->add_meta_data( '_wcpay_wallet_type', 'google_pay', true );
		$token->save();

		$item = [
			'method' => [
				'brand' => 'Visa',
				'last4' => '4242',
			],
		];

		$result = $this->token_service->get_account_saved_payment_methods_list_item_wallet( $item, $token );

		$this->assertEquals( 'Google Pay Visa', $result['method']['brand'] );

		// Restore original payment method map.
		$property->setValue( null, $original_map );
	}

	/**
	 * Ensures get_account_saved_payment_methods_list_item_wallet returns wallet display name for Apple Pay.
	 */
	public function test_get_account_saved_payment_methods_list_item_wallet_apple_pay() {
		// Create a mock payment method that returns the title.
		$mock_payment_method = $this->createMock( WCPay\Payment_Methods\UPE_Payment_Method::class );
		$mock_payment_method->method( 'get_title' )->willReturn( 'Apple Pay' );

		// Use Reflection to inject the mock into WC_Payments' payment method map.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_method_map' );
		$property->setAccessible( true );
		$original_map         = $property->getValue();
		$new_map              = $original_map;
		$new_map['apple_pay'] = $mock_payment_method;
		$property->setValue( null, $new_map );

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock' );
		$token->set_card_type( 'mastercard' );
		$token->set_last4( '5555' );
		$token->set_user_id( 1 );
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2030' );
		$token->add_meta_data( '_wcpay_wallet_type', 'apple_pay', true );
		$token->save();

		$item = [
			'method' => [
				'brand' => 'Mastercard',
				'last4' => '5555',
			],
		];

		$result = $this->token_service->get_account_saved_payment_methods_list_item_wallet( $item, $token );

		$this->assertEquals( 'Apple Pay Mastercard', $result['method']['brand'] );

		// Restore original payment method map.
		$property->setValue( null, $original_map );
	}

	/**
	 * Ensures get_account_saved_payment_methods_list_item_wallet doesn't modify non-wallet tokens.
	 */
	public function test_get_account_saved_payment_methods_list_item_wallet_without_wallet() {
		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_user_id( 1 );
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2030' );
		$token->save();

		$item = [
			'method' => [
				'brand' => 'Visa',
				'last4' => '4242',
			],
		];

		$result = $this->token_service->get_account_saved_payment_methods_list_item_wallet( $item, $token );

		$this->assertEquals( 'Visa', $result['method']['brand'] );
	}

	/**
	 * Ensures get_account_saved_payment_methods_list_item_wallet doesn't modify SEPA tokens.
	 */
	public function test_get_account_saved_payment_methods_list_item_wallet_ignores_sepa() {
		$token = new WC_Payment_Token_WCPay_SEPA();
		$token->set_gateway_id( 'woocommerce_payments_sepa_debit' );
		$token->set_token( 'pm_mock' );
		$token->set_last4( '3000' );
		$token->save();

		$item = [
			'method' => [
				'brand' => 'SEPA IBAN',
				'last4' => '3000',
			],
		];

		$result = $this->token_service->get_account_saved_payment_methods_list_item_wallet( $item, $token );

		$this->assertEquals( 'SEPA IBAN', $result['method']['brand'] );
	}

	/**
	 * Ensures get_account_saved_payment_methods_list_item_wallet handles missing payment method gracefully.
	 */
	public function test_get_account_saved_payment_methods_list_item_wallet_missing_payment_method() {
		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'woocommerce_payments' );
		$token->set_token( 'pm_mock' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_user_id( 1 );
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2030' );
		$token->add_meta_data( '_wcpay_wallet_type', 'unknown_wallet', true );
		$token->save();

		$item = [
			'method' => [
				'brand' => 'Visa',
				'last4' => '4242',
			],
		];

		$result = $this->token_service->get_account_saved_payment_methods_list_item_wallet( $item, $token );

		// Should return unchanged when wallet type not found.
		$this->assertEquals( 'Visa', $result['method']['brand'] );
	}
}
