<?php
/**
 * Class WC_Payments_API_Client_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_API_Client unit tests.
 */
class WC_Payments_API_Client_Test extends WP_UnitTestCase {

	/**
	 * System under test
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Mock HTTP client.
	 *
	 * @var WC_Payments_Http|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_http_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_http_client = $this->getMockBuilder( 'WC_Payments_Http' )
			->disableOriginalConstructor()
			->setMethods( array( 'remote_request' ) )
			->getMock();

		$this->payments_api_client = new WC_Payments_API_Client(
			'Unit Test Agent/0.1.0',
			$this->mock_http_client
		);
	}

	/**
	 * Test a successful call to create_charge.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_charge_success() {
		// Mock up a test response from WP_Http.
		$this->set_http_mock_response(
			200,
			array(
				'id'      => 'test_charge_id',
				'amount'  => 123,
				'created' => 1557224304,
				'status'  => 'success',
			)
		);

		// Attempt to create a charge.
		$result = $this->payments_api_client->create_charge( 123, 'test_source' );

		// Assert amount returned is correct (ignoring other properties for now since this is a stub implementation).
		$this->assertEquals( 123, $result->get_amount() );
	}

	/**
	 * Test a successful call to create_intention.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_intention_success() {
		$expected_amount = 123;
		$expected_status = 'succeeded';

		$this->set_http_mock_response(
			200,
			array(
				'id'      => 'test_intention_id',
				'amount'  => $expected_amount,
				'created' => 1557224304,
				'status'  => $expected_status,
				'charges' => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => $expected_amount,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
			)
		);

		$result = $this->payments_api_client->create_and_confirm_intention( $expected_amount, 'usd', 'pm_123456789' );
		$this->assertEquals( $expected_amount, $result->get_amount() );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful call to refund_charge.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_refund_success() {
		$expected_amount = 123;

		// Mock up a test response from WP_Http.
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'id'     => 'test_refund_id',
								'amount' => $expected_amount,
								'status' => 'succeeded',
							)
						),
						'response' => array(
							'code' => 200,
						),
						'cookies'  => array(),
						'filename' => null,
					)
				)
			);

		// Attempt to create a refund.
		$refund = $this->payments_api_client->refund_charge( 'test_charge_id', $expected_amount );

		// Assert amount returned is correct (ignoring other properties for now since this is a stub implementation).
		$this->assertEquals( $expected_amount, $refund['amount'] );
	}

	/**
	 * Test a successful call to create_intention with manual capture.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_intention_authorization_success() {
		$expected_amount = 123;
		$expected_status = 'requires_capture';

		$this->set_http_mock_response(
			200,
			array(
				'id'      => 'test_intention_id',
				'amount'  => $expected_amount,
				'created' => 1557224304,
				'status'  => $expected_status,
				'charges' => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => $expected_amount,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
			)
		);

		$result = $this->payments_api_client->create_and_confirm_intention( $expected_amount, 'usd', 'pm_123456789', true );
		$this->assertEquals( $expected_amount, $result->get_amount() );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful call to capture intention.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_capture_intention_success() {
		$expected_amount = 103;
		$expected_status = 'succeeded';

		$this->set_http_mock_response(
			200,
			array(
				'id'              => 'test_intention_id',
				'amount'          => 123,
				'amount_captured' => $expected_amount,
				'created'         => 1557224304,
				'status'          => $expected_status,
				'charges'         => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => $expected_amount,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
			)
		);

		$result = $this->payments_api_client->capture_intention( 'test_intention_id', $expected_amount );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful call to cancel intention.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_cancel_intention_success() {
		$expected_status = 'canceled';

		$this->set_http_mock_response(
			200,
			array(
				'id'      => 'test_intention_id',
				'amount'  => 123,
				'created' => 1557224304,
				'status'  => $expected_status,
				'charges' => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => 123,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
			)
		);

		$result = $this->payments_api_client->cancel_intention( 'test_intention_id' );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful fetch of a single transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_success() {
		$transaction_id = 'txn_231mdaism';

		$this->set_http_mock_response(
			200,
			array(
				'id'     => $transaction_id,
				'type'   => 'charge',
				'source' => array( 'id' => 'ch_ji3djhabvh23' ),
			)
		);

		$transaction = $this->payments_api_client->get_transaction( $transaction_id );
		$this->assertEquals( $transaction_id, $transaction['id'] );
	}

	/**
	 * Test fetching of non existing transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_not_found() {
		$transaction_id = 'txn_231mdaism';
		$error_code     = 'resource_missing';
		$error_message  = 'No such balance transaction';

		$this->set_http_mock_response(
			404,
			array(
				'error' => array(
					'code'    => $error_code,
					'message' => $error_message,
				),
			)
		);
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "Error: $error_code $error_message" );

		$this->payments_api_client->get_transaction( $transaction_id );
	}

	/**
	 * Set up http mock response.
	 *
	 * @param int   $status_code status code for the mocked response.
	 * @param array $body body for the mocked response.
	 * @param array $headers headers for the mocked response.
	 * @param array $cookies cookies to be used in the mocked response.
	 */
	private function set_http_mock_response( $status_code, $body = array(), $headers = array(), $cookies = array() ) {
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					array(
						'headers'  => $headers,
						'body'     => wp_json_encode( $body ),
						'response' => array(
							'code'    => $status_code,
							'message' => 'OK',
						),
						'cookies'  => $cookies,
						'filename' => null,
					)
				)
			);
	}
}
