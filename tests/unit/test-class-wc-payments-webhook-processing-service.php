<?php
/**
 * Class WC_Payments_Webhook_Processing_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Payment_Method;

/**
 * WC_Payments_Webhook_Processing_Service unit tests.
 */
class WC_Payments_Webhook_Processing_Service_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Webhook_Processing_Service
	 */
	private $webhook_processing_service;

	/**
	 * @var WC_Payments_DB|MockObject
	 */
	private $mock_db_wrapper;

	/**
	 * @var WC_Payments_Remote_Note_Service|MockObject
	 */
	private $mock_remote_note_service;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		/** @var WC_Payments_API_Client|MockObject $mock_api_client */
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
								->disableOriginalConstructor()
								->getMock();

		$account = new WC_Payments_Account( $mock_api_client );

		$this->mock_db_wrapper = $this->getMockBuilder( WC_Payments_DB::class )
									->disableOriginalConstructor()
									->setMethods( [ 'order_from_charge_id', 'order_from_intent_id', 'order_from_order_id' ] )
									->getMock();

		$this->mock_remote_note_service = $this->createMock( WC_Payments_Remote_Note_Service::class );

		$this->webhook_processing_service = new WC_Payments_Webhook_Processing_Service( $this->mock_db_wrapper, $account, $this->mock_remote_note_service );
	}
}
