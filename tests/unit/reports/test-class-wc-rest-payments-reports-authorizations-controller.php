<?php
/**
 * Class WC_REST_Payments_Reports_Authorizations_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Core\Server\Request\List_Authorizations;

/**
 * WC_REST_Payments_Reports_Authorizations_Controller unit tests.
 */
class WC_REST_Payments_Reports_Authorizations_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Reports_Authorizations_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function set_up() {
		parent::set_up();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Reports_Authorizations_Controller( $this->mock_api_client );
	}


	public function test_get_authorizations_success() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'per_page', 3 );

		$mock_request = $this->mock_wcpay_request( List_Authorizations::class );
		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $this->get_authorizations_list_from_server() );

		// check that in the end, page size is set correctly.
		$mock_request->expects( $this->any() )
			->method( 'set_page_size' )
			->withConsecutive(
				[ $this->anything() ],
				[ '3' ]
			);

		$response = $this->controller->get_authorizations( $request );
		$this->assertEquals( $this->get_authorizations_list(), $response->get_data() );
	}

	public function test_get_authorizations_response_error() {
		$request = new WP_REST_Request( 'POST' );

		$mock_request = $this->mock_wcpay_request( List_Authorizations::class );
		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new Connection_Exception(
						'Test error.',
						'wcpay_http_request_failed',
						400
					)
				)
			);

		$response = $this->controller->get_authorizations( $request );
		$expected = new WP_Error( 'wcpay_http_request_failed', 'Test error.' );
		$this->assertEquals( $expected, $response );
	}

	public function test_get_authorizations_filters() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'match', 'any' );
		$request->set_param( 'order_id', 123 );
		$request->set_param( 'customer_email', 'test@woocommerce.com' );
		$request->set_param( 'payment_method_type', 'visa' );

		$mock_request = $this->mock_wcpay_request( List_Authorizations::class );
		$mock_request->expects( $this->once() )
			->method( 'set_filters' )
			->with(
				[
					'match'             => 'any',
					'order_id_is'       => 123,
					'customer_email_is' => 'test@woocommerce.com',
					'source_is'         => 'visa',
				],
			);

		$response = $this->controller->get_authorizations( $request );
	}


	public function test_get_authorizations_date_filters() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'date_after', '2023-08-20 00:00:00' );
		$request->set_param( 'date_before', '2023-08-21 23:59:59' );
		$request->set_param( 'user_timezone', '-2:30' );

		// the date minus 2:30 hrs in timestamp.
		$translated_timestamp_date_after  = strtotime( '2023-08-20 00:00:00' ) - 9000;
		$translated_timestamp_date_before = strtotime( '2023-08-21 23:59:59' ) - 9000;

		$mock_request = $this->mock_wcpay_request( List_Authorizations::class );
		$mock_request->expects( $this->once() )
			->method( 'set_filters' )
			->with(
				$this->callback(
					function ( $filters ) use ( $translated_timestamp_date_after, $translated_timestamp_date_before ): bool {
						$this->assertSame( $translated_timestamp_date_after, $filters['to_date'] );
						$this->assertSame( $translated_timestamp_date_before, $filters['from_date'] );
						return true;
					}
				)
			);
		$response = $this->controller->get_authorizations( $request );
	}

	public function test_get_authorization_success() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'id', 'ch_123' );

		$mock_request = $this->mock_wcpay_request( List_Authorizations::class );
		// test the params are set correctly.
		$mock_request->expects( $this->once() )
			->method( 'set_filters' )
			->with(
				[ 'charge_id_is' => 'ch_123' ]
			);
		$mock_request->expects( $this->once() )
			->method( 'set_page_size' )
			->with( 1 );

		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'data' => [ $this->get_authorizations_list_from_server()['data'][0] ] ] );

		$response = $this->controller->get_authorization( $request );
		$this->assertEquals( $this->get_authorizations_list()[0], $response->get_data() );
	}

	public function test_get_authorization_response_error() {
		$request = new WP_REST_Request( 'POST' );

		$mock_request = $this->mock_wcpay_request( List_Authorizations::class );
		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new Connection_Exception(
						'Test error.',
						'wcpay_http_request_failed',
						400
					)
				)
			);

		$response = $this->controller->get_authorization( $request );
		$expected = new WP_Error( 'wcpay_http_request_failed', 'Test error.' );
		$this->assertEquals( $expected, $response );
	}

	public function test_get_authorization_empty_result() {
		$request = new WP_REST_Request( 'POST' );

		$mock_request = $this->mock_wcpay_request( List_Authorizations::class );
		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'data' => [] ] );

		$response = $this->controller->get_authorization( $request );
		$this->assertEquals( [], $response->get_data() );
	}

	private function get_authorizations_list_from_server() {
		return [
			'data' => [
				[
					'charge_id'         => 'ch_123',
					'transaction_id'    => null,
					'amount'            => 7300,
					'net'               => 6988,
					'amount_captured'   => 0,
					'amount_refunded'   => 0,
					'is_capture¯d'      => false,
					'created'           => '2023-08-26 00:51:42',
					'modified'          => '2023-08-28 13:09:19',
					'channel'           => 'online',
					'source'            => 'visa',
					'source_identifier' => '4242',
					'customer_name'     => 'Test One',
					'customer_email'    => 'test1@woocommerce.com',
					'customer_country'  => 'US',
					'fees'              => 312,
					'currency'          => 'eur',
					'risk_level'        => 0,
					'payment_intent_id' => 'pi_321',
					'refunded'          => false,
					'order_id'          => 123,
					'outcome_type'      => 'authorized',
					'status'            => 'succeeded',
				],
				[
					'charge_id'         => 'ch_345',
					'transaction_id'    => null,
					'amount'            => 1800,
					'net'               => 1702,
					'amount_captured'   => 0,
					'amount_refunded'   => 0,
					'is_captured'       => false,
					'created'           => '2023-08-27 00:48:44',
					'modified'          => '2023-08-28 13:09:05',
					'channel'           => 'online',
					'source'            => 'visa',
					'source_identifier' => '4242',
					'customer_name'     => 'Test Two',
					'customer_email'    => 'test2@woocommerce.com',
					'customer_country'  => 'US',
					'fees'              => 98,
					'currency'          => 'eur',
					'risk_level'        => 0,
					'payment_intent_id' => 'pi_345',
					'refunded'          => false,
					'order_id'          => 456,
					'outcome_type'      => 'authorized',
					'status'            => 'succeeded',
				],
				[
					'charge_id'         => 'ch_567',
					'transaction_id'    => null,
					'amount'            => 7300,
					'net'               => 6988,
					'amount_captured'   => 0,
					'amount_refunded'   => 0,
					'is_captured'       => false,
					'created'           => '2023-08-27 00:51:42',
					'modified'          => '2023-08-28 13:09:11',
					'channel'           => 'online',
					'source'            => 'mastercard',
					'source_identifier' => '4242',
					'customer_name'     => 'Test One',
					'customer_email'    => 'test1@woocommerce.com',
					'customer_country'  => 'US',
					'fees'              => 312,
					'currency'          => 'eur',
					'risk_level'        => 0,
					'payment_intent_id' => 'pi_567',
					'refunded'          => false,
					'order_id'          => 789,
					'outcome_type'      => 'authorized',
					'status'            => 'succeeded',
				],
			],
		];
	}

	private function get_authorizations_list() {
		return [
			[
				'authorization_id' => 'ch_123',
				'date'             => '2023-08-26 00:51:42',
				'payment_id'       => 'pi_321',
				'channel'          => 'online',
				'payment_method'   => [
					'type' => 'visa',
				],
				'currency'         => 'eur',
				'amount'           => 7300,
				'amount_captured'  => 0,
				'fees'             => 312,
				'customer'         => [
					'name'    => 'Test One',
					'email'   => 'test1@woocommerce.com',
					'country' => 'US',
				],
				'net_amount'       => 6988,
				'order_id'         => 123,
				'risk_level'       => 0,
			],
			[
				'authorization_id' => 'ch_345',
				'date'             => '2023-08-27 00:48:44',
				'payment_id'       => 'pi_345',
				'channel'          => 'online',
				'payment_method'   => [
					'type' => 'visa',
				],
				'currency'         => 'eur',
				'amount'           => 1800,
				'amount_captured'  => 0,
				'fees'             => 98,
				'customer'         => [
					'name'    => 'Test Two',
					'email'   => 'test2@woocommerce.com',
					'country' => 'US',
				],
				'net_amount'       => 1702,
				'order_id'         => 456,
				'risk_level'       => 0,
			],
			[
				'authorization_id' => 'ch_567',
				'date'             => '2023-08-27 00:51:42',
				'payment_id'       => 'pi_567',
				'channel'          => 'online',
				'payment_method'   => [
					'type' => 'mastercard',
				],
				'currency'         => 'eur',
				'amount'           => 7300,
				'amount_captured'  => 0,
				'fees'             => 312,
				'customer'         => [
					'name'    => 'Test One',
					'email'   => 'test1@woocommerce.com',
					'country' => 'US',
				],
				'net_amount'       => 6988,
				'order_id'         => 789,
				'risk_level'       => 0,
			],
		];
	}

}
