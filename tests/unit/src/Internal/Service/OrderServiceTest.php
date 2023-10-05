<?php
/**
 * Class OrderServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Payments_Order_Service;
use WCPAY_UnitTestCase;
use WCPay\Internal\Service\OrderService;

/**
 * Order service unit tests.
 */
class OrderServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var OrderService|MockObject
	 */
	private $sut;

	/**
	 * @var WC_Payments_Order_Service|MockObject
	 */
	private $mock_legacy_service;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_legacy_service = $this->createMock( WC_Payments_Order_Service::class );

		$this->sut = new OrderService( $this->mock_legacy_service );
	}

	public function test_set_payment_method_id() {
		$order_id = 123;
		$pm_id    = 'pm_XYZ';

		$this->mock_legacy_service->expects( $this->once() )
			->method( 'set_payment_method_id_for_order' )
			->with( $order_id, $pm_id );

		$this->sut->set_payment_method_id( $order_id, $pm_id );
	}
}
