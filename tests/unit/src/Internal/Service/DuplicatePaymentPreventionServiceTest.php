<?php
/**
 * Class DuplicatePaymentPreventionServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\SessionService;
use WCPAY_UnitTestCase;
use \PHPUnit\Framework\MockObject\MockObject;

/**
 * Level3 data service unit tests.
 */
class DuplicatePaymentPreventionServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var DuplicatePaymentPreventionService
	 */
	private $sut;

	/**
	 * @var OrderService|MockObject
	 */
	private $mock_order_service;

	/**
	 * @var SessionService|MockObject
	 */
	private $mock_session_service;

	/**
	 * @var HooksProxy|MockObject
	 */
	private $mock_hooks_proxy;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * Dependencies for the service under test.
	 *
	 * @var array
	 */
	private $deps;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_order_service   = $this->createMock( OrderService::class );
		$this->mock_session_service = $this->createMock( SessionService::class );
		$this->mock_hooks_proxy     = $this->createMock( HooksProxy::class );
		$this->mock_legacy_proxy    = $this->createMock( LegacyProxy::class );

		$this->deps = [
			$this->mock_order_service,
			$this->mock_session_service,
			$this->mock_hooks_proxy,
			$this->mock_legacy_proxy,
		];

		$this->sut = new DuplicatePaymentPreventionService( ... $this->deps );
	}

	public function test_init_hooks() {
		$this->mock_hooks_proxy->expects( $this->once() )
			->method( 'add_action' )
			->with( 'template_redirect', [ $this->sut, 'clear_session_processing_order_after_landing_order_received_page' ], 21 );

		$this->sut->init_hooks();
	}

	public function test_clean_up_when_detecting_duplicate_order() {
		$duplicate_order_id = 111;
		$current_order_id   = 222;
		$this->sut          = $this->getMockBuilder( DuplicatePaymentPreventionService::class )
			->setConstructorArgs( $this->deps )
			->onlyMethods( [ 'remove_session_processing_order' ] )
			->getMock();

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'add_note' )
			->with( $duplicate_order_id, $this->stringContains( 'detected and deleted order ID ' . $current_order_id ) );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'delete' )
			->with( $current_order_id );

		$this->sut->expects( $this->once() )
			->method( 'remove_session_processing_order' )
			->with( $duplicate_order_id );

		// Act.
		$this->sut->clean_up_when_detecting_duplicate_order( $duplicate_order_id, $current_order_id );
	}

	public function test_update_session_processing_order() {
		$this->mock_session_service->expects( $this->once() )
			->method( 'set' )
			->with( $this->sut::SESSION_KEY_PROCESSING_ORDER, 123 );

		// Act.
		$this->sut->update_session_processing_order( 123 );
	}

	public function provider_remove_session_processing_order() {
		return [
			'no session ID'                             => [ null, 123, 0 ],
			'session ID and to-be-removed ID different' => [ 123, 456, 0 ],
			'session ID and to-be-removed ID are same'  => [ 123, 123, 1 ],
		];
	}

	/**
	 * @dataProvider provider_remove_session_processing_order
	 */
	public function test_remove_session_processing_order( ?int $session_order_id, int $to_be_removed_id, int $session_invoked_times ) {
		$this->sut = $this->getMockBuilder( DuplicatePaymentPreventionService::class )
			->setConstructorArgs( $this->deps )
			->onlyMethods( [ 'get_session_processing_order' ] )
			->getMock();

		$this->sut->expects( $this->once() )
			->method( 'get_session_processing_order' )
			->willReturn( $session_order_id );
		$this->mock_session_service
			->expects( $this->exactly( $session_invoked_times ) )
			->method( 'set' )
			->with( $this->sut::SESSION_KEY_PROCESSING_ORDER, null );

		// Act.
		$this->sut->remove_session_processing_order( $to_be_removed_id );
	}

	public function provider_get_session_processing_order() {
		return [
			'no session order ID'                  => [ null, null ],
			'has session order ID but not integer' => [ '111', 111 ],
			'has session order ID'                 => [ 555, 555 ],
		];
	}

	/**
	 * @dataProvider provider_get_session_processing_order
	 */
	public function test_get_session_processing_order( $session_order_id, $expected ) {
		$this->mock_session_service->expects( $this->once() )
			->method( 'get' )
			->with( $this->sut::SESSION_KEY_PROCESSING_ORDER )
			->willReturn( $session_order_id );

		// Act.
		$result = $this->sut->get_session_processing_order();

		$this->assertSame( $expected, $result );
	}


	public function provider_clear_session_processing_order_after_landing_order_received_page() {
		return [
			'not order received page'                 => [ false, null, 0 ],
			'is order received page without order ID' => [ true, null, 0 ],
			'is order received page with order ID'    => [ true, 999, 1 ],
		];
	}

	/**
	 * @dataProvider provider_clear_session_processing_order_after_landing_order_received_page
	 */
	public function test_clear_session_processing_order_after_landing_order_received_page(
		bool $is_order_received_page,
		$order_received_var,
		int $call_remove_session_processing_order ) {
		$this->sut = $this->getMockBuilder( DuplicatePaymentPreventionService::class )
			->setConstructorArgs( $this->deps )
			->onlyMethods( [ 'remove_session_processing_order' ] )
			->getMock();

		$this->mock_legacy_proxy
			->expects( $this->once() )
			->method( 'get_global' )
			->with( 'wp' )
			->willReturn(
				(object) [
					'query_vars' => [ 'order-received' => $order_received_var ],
				]
			);

		$this->mock_legacy_proxy
			->expects( $this->once() )
			->method( 'call_function' )
			->with( 'is_order_received_page' )
			->willReturn( $is_order_received_page );

		$this->sut->expects( $this->exactly( $call_remove_session_processing_order ) )
			->method( 'remove_session_processing_order' )
			->with( $order_received_var );

		$this->sut->clear_session_processing_order_after_landing_order_received_page();
	}
}
