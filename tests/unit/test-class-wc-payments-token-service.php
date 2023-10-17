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
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

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

		$this->assertEquals( 'woocommerce_payments', $token->get_gateway_id() );
		$this->assertEquals( 1, $token->get_user_id() );
		$this->assertEquals( 'pm_mock', $token->get_token() );
		$this->assertEquals( '3000', $token->get_last4() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_SEPA::class, $token );
	}

	/**
	 * Test add SEPA token to user with deferred intent creation UPE.
	 */
	public function test_add_token_to_user_for_sepa_deferred_intent_creation_upe() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'is_deferred_intent_creation_upe_enabled' => true ] );
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
		update_option( WC_Payments_Features::UPE_SPLIT_FLAG_NAME, '0' );

	}

	/**
	 * Test add SEPA token to user with deferred intent UPE.
	 */
	public function test_add_token_to_user_for_sepa_deferred_upe() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'is_deferred_intent_creation_upe_enabled' => true ] );
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
		update_option( WC_Payments_Features::UPE_DEFERRED_INTENT_FLAG_NAME, '0' );

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
		update_option( WC_Payments_Features::UPE_SPLIT_FLAG_NAME, '1' );
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
		update_option( WC_Payments_Features::UPE_SPLIT_FLAG_NAME, '0' );
	}

	/**
	 * Test add Link token to user with deferred intent UPE.
	 */
	public function test_add_token_to_user_for_link_deferred_upe() {
		update_option( WC_Payments_Features::UPE_DEFERRED_INTENT_FLAG_NAME, '1' );
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
		update_option( WC_Payments_Features::UPE_DEFERRED_INTENT_FLAG_NAME, '0' );
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
		$tokens = [
			1 => $this->generate_card_token( 'pm_111', 1 ),
			2 => $this->generate_card_token( 'pm_222', 2 ),
			3 => $this->generate_sepa_token( 'pm_333', 3 ),
			4 => $this->generate_sepa_token( 'pm_444', 4 ),
			5 => $this->generate_link_token( 'pm_555', 5 ),
			6 => $this->generate_link_token( 'pm_666', 6 ),
		];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( $customer_id );

		// Expect a call for each payment method, and return an array with consecutive keys.
		$this->mock_customer_service
			->expects( $this->exactly( 3 ) )
			->method( 'get_payment_methods_for_customer' )
			->withConsecutive(
				[ $customer_id, Payment_Method::CARD ],
				[ $customer_id, Payment_Method::SEPA ],
				[ $customer_id, Payment_Method::LINK ]
			)
			->willReturnOnConsecutiveCalls(
				[
					$this->generate_card_pm_response( 'pm_111' ),
					$this->generate_card_pm_response( 'pm_222' ),
				],
				[
					$this->generate_sepa_pm_response( 'pm_333' ),
					$this->generate_sepa_pm_response( 'pm_444' ),
				],
				[
					$this->generate_link_pm_response( 'pm_555' ),
					$this->generate_link_pm_response( 'pm_666' ),
				]
			);

		$result = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, 'woocommerce_payments' );
		$this->assertSame(
			array_keys( $tokens ),
			array_keys( $result )
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

	public function test_woocommerce_get_customer_payment_tokens_not_added_from_different_gateway() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'is_deferred_intent_creation_upe_enabled' => true ] );
		$gateway_id      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$tokens          = [];
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
					$this->generate_sepa_pm_response( 'other_gateway_pm_111' ),
					$this->generate_sepa_pm_response( 'other_gateway_pm_222' ),
					$this->generate_sepa_pm_response( 'other_gateway_pm_333' ),
					$this->generate_sepa_pm_response( 'other_gateway_pm_444' ),
					$this->generate_sepa_pm_response( 'other_gateway_pm_555' ),
				]
			);

		$result        = $this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, $gateway_id );
		$result_tokens = array_values( $result );

		$this->assertEquals( 2, count( $result_tokens ) );
		$this->assertEquals( $gateway_id, $result_tokens[0]->get_gateway_id() );
		$this->assertEquals( $gateway_id, $result_tokens[1]->get_gateway_id() );
		$this->assertEquals( 'pm_mock0', $result_tokens[0]->get_token() );
		$this->assertEquals( 'pm_222', $result_tokens[1]->get_token() );

		update_option( '_wcpay_feature_upe_split', '0' );
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
		$gateway_id  = 'woocommerce_payments';
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
				[ $customer_id, Payment_Method::SEPA ],
				[ $customer_id, Payment_Method::LINK ]
			)
			->willReturnOnConsecutiveCalls(
				[
					$this->generate_card_pm_response( 'pm_mock0' ),
				],
				[
					$this->generate_sepa_pm_response( 'pm_mock_2' ),
				],
				[
					$this->generate_link_pm_response( 'pm_mock_3' ),
				]
			);

		$this->token_service->woocommerce_get_customer_payment_tokens( $tokens, 1, $gateway_id );
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
}
