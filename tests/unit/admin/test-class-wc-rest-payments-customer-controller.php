<?php
/**
 * Class WC_REST_Payments_Customer_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_REST_Payments_Customer_Controller_Test unit tests.
 */
class WC_REST_Payments_Customer_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Customer_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	public function set_up() {
		parent::set_up();
		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->controller            = new WC_REST_Payments_Customer_Controller( $this->mock_api_client, $this->mock_customer_service );
	}

	public function test_get_customer_payment_methods_endpoint_will_return_empty_response_if_customer_not_exist() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'customer_id', 0 );

		$this->mock_customer_service->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( null );
		$this->mock_customer_service->expects( $this->never() )
			->method( 'retrieve_usable_customer_payment_methods' );

		$response = $this->controller->get_customer_payment_methods( $request );
		$this->assertEmpty( $response->get_data() );
	}
	public function test_get_customer_payment_methods_endpoint_will_return_correct_response_for_card() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'customer_id', 1 );
		$payment_method         = $this->get_base_payment_method_data();
		$payment_method['type'] = 'card';
		$payment_method['card'] = [
			'brand'                => 'mastercard',
			'checks'               => [
				'address_line1_check'       => 'fail',
				'address_postal_code_check' => 'unchecked',
				'cvc_check'                 => 'pass',
			],
			'country'              => 'US',
			'exp_month'            => 11,
			'exp_year'             => 2030,
			'fingerprint'          => 'RSTUvWXZa1b2c3Y4',
			'funding'              => 'debit',
			'generated_from'       => null,
			'last4'                => '5678',
			'networks'             => [
				'available' => [ 'mastercard' ],
				'preferred' => null,
			],
			'three_d_secure_usage' => [
				'supported' => false,
			],
			'wallet'               => null,
		];
		$this->mock_customer_service->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );
		$this->mock_customer_service->expects( $this->once() )
			->method( 'retrieve_usable_customer_payment_methods' )
			->willReturn( [ $payment_method ] ); // We will test each payment method type in different test.
		$response = $this->controller->get_customer_payment_methods( $request );
		$data     = $response->get_data()[0];
		$this->assertIsArray( $data );
		$this->assertSame( $data['id'], $payment_method['id'] );
		$this->assertSame( $data['type'], $payment_method['type'] );
		$this->assertSame( $data['billing_details'], $payment_method['billing_details'] );
		$this->assertSame( $data['billing_details'], $payment_method['billing_details'] );
		$this->assertSame( $data['card']['brand'], $payment_method['card']['brand'] );
		$this->assertSame( $data['card']['last4'], $payment_method['card']['last4'] );
		$this->assertSame( $data['card']['exp_month'], $payment_method['card']['exp_month'] );
		$this->assertArrayNotHasKey( 'sepa_debit', $data );
		$this->assertArrayNotHasKey( 'link', $data );
	}
	public function test_get_customer_payment_methods_endpoint_will_return_correct_response_for_sepa() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'customer_id', 1 );
		$payment_method               = $this->get_base_payment_method_data();
		$payment_method['type']       = 'sepa_debit';
		$payment_method['sepa_debit'] = [
			'last4' => '1337',
		];
		$this->mock_customer_service->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );
		$this->mock_customer_service->expects( $this->once() )
			->method( 'retrieve_usable_customer_payment_methods' )
			->willReturn( [ $payment_method ] ); // We will test each payment method type in different test.
		$response = $this->controller->get_customer_payment_methods( $request );
		$data     = $response->get_data()[0];
		$this->assertIsArray( $data );
		$this->assertSame( $data['id'], $payment_method['id'] );
		$this->assertSame( $data['type'], $payment_method['type'] );
		$this->assertSame( $data['billing_details'], $payment_method['billing_details'] );
		$this->assertSame( $data['billing_details'], $payment_method['billing_details'] );
		$this->assertSame( $data['sepa_debit']['last4'], $payment_method['sepa_debit']['last4'] );
		$this->assertArrayNotHasKey( 'card', $data );
		$this->assertArrayNotHasKey( 'link', $data );
	}
	public function test_get_customer_payment_methods_endpoint_will_return_correct_response_for_link() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'customer_id', 1 );
		$payment_method         = $this->get_base_payment_method_data();
		$payment_method['type'] = 'link';
		$payment_method['link'] = [
			'email' => 'mail@example.com',
		];
		$this->mock_customer_service->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );
		$this->mock_customer_service->expects( $this->once() )
			->method( 'retrieve_usable_customer_payment_methods' )
			->willReturn( [ $payment_method ] ); // We will test each payment method type in different test.
		$response = $this->controller->get_customer_payment_methods( $request );
		$data     = $response->get_data()[0];
		$this->assertIsArray( $data );
		$this->assertSame( $data['id'], $payment_method['id'] );
		$this->assertSame( $data['type'], $payment_method['type'] );
		$this->assertSame( $data['billing_details'], $payment_method['billing_details'] );
		$this->assertSame( $data['billing_details'], $payment_method['billing_details'] );
		$this->assertSame( $data['link']['email'], $payment_method['link']['email'] );
		$this->assertArrayNotHasKey( 'card', $data );
		$this->assertArrayNotHasKey( 'sepa_debit', $data );
	}

	/**
	 * Get base payment method data.
	 *
	 * @return array[]
	 */
	private function get_base_payment_method_data() {
		return [

			'id'              => 'pm_mock',
			'object'          => 'payment_method',
			'billing_details' => [
				'address' => [
					'city'        => 'Los Angeles',
					'country'     => 'US',
					'line1'       => '123 Sunset Blvd',
					'line2'       => 'Apt 456',
					'postal_code' => '90028',
					'state'       => 'CA',
				],
				'email'   => 'john.doe@example.com',
				'name'    => 'John Doe',
				'phone'   => '1234567890',
			],
			'created'         => 1692367890,
			'customer'        => 'cus_mock',
			'livemode'        => true,
			'metadata'        => [],
		];
	}
}
