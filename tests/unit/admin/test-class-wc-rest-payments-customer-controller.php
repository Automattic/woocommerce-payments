<?php
/**
 * Class WC_REST_Payments_Customer_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;

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

	/**
	 * Enabled payment methods.
	 *
	 * @var array
	 */
	private $enabled_payment_methods; // Instead of mocking gateway, we will get enabled payment methods and after tests, return the existing ones.

	public function set_up() {
		parent::set_up();
		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->controller            = new WC_REST_Payments_Customer_Controller( $this->mock_api_client, $this->mock_customer_service );
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
			'country'              => Country_Code::UNITED_STATES,
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

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_payment_methods_for_customer' )
			->with( $this->anything(), 'card' )
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
					'country'     => Country_Code::UNITED_STATES,
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
