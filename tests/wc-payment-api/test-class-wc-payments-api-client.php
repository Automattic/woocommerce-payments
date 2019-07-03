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
	 * Mock HTTP client
	 *
	 * @var WP_Http|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_http_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_http_client = $this->getMockBuilder( 'WP_Http' )
			->disableOriginalConstructor()
			->setMethods( array( 'request' ) )
			->getMock();

		$this->payments_api_client = new WC_Payments_API_Client(
			$this->mock_http_client,
			'Unit Test Agent/0.1.0'
		);

		$this->payments_api_client->set_account_id( 'test_acc_id_12345' );
	}

	/**
	 * Test a successful call to create_charge.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_charge_success() {
		$this->markTestSkipped( 'Revisit once Jetpack Client dependency has been abstracted out of API client' );

		// Mock up a test response from WP_Http.
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'request' )
			->will(
				$this->returnValue(
					array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'id'      => 'test_transaction_id',
								'amount'  => '123',
								'created' => 1557224304,
								'status'  => 'success',
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
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'id'      => 'test_transaction_id',
								'amount'  => 123,
								'created' => 1557224304,
								'status'  => 'succeeded',
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => null,
					)
				)
			);

		$result = $this->payments_api_client->create_intention( 123, 'usd', 'cash', 'pm_123456789' );
		$this->assertEquals( 'succeeded', $result->get_status() );
		$this->assertEquals( '123', $result->get_amount() );
	}
}
