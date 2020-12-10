<?php
/**
 * Class WC_Payments_API_Client_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_API_Client unit tests.
 */
class WC_Payments_Internal_REST_Request_Test extends WP_UnitTestCase {

	/**
	 * HTTP client.
	 *
	 * @var WC_Payments_Http_Interface
	 */
	private $http_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->http_client = new WC_Payments_Internal_REST_Request();
	}

	public function test_is_connected() {
		$this->assertTrue( $this->http_client->is_connected() );
	}
}
