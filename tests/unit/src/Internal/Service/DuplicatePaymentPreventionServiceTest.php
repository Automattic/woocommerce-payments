<?php
/**
 * Class DuplicatePaymentPreventionServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WC_Helper_Intention;
use WCPay\Constants\Intent_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Internal\Logger;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\SessionService;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

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
	 * @var Logger|MockObject
	 */
	private $mock_logger;

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
	 * @var MockObject[]
	 */
	private $deps;

	/**
	 * ID of the order being processed in the gateway.
	 *
	 * @var int
	 */
	private $processing_order_id = 123;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_order_service   = $this->createMock( OrderService::class );
		$this->mock_session_service = $this->createMock( SessionService::class );
		$this->mock_logger          = $this->createMock( Logger::class );
		$this->mock_hooks_proxy     = $this->createMock( HooksProxy::class );
		$this->mock_legacy_proxy    = $this->createMock( LegacyProxy::class );

		$this->deps = [
			$this->mock_order_service,
			$this->mock_session_service,
			$this->mock_logger,
			$this->mock_hooks_proxy,
			$this->mock_legacy_proxy,
		];

		$this->sut = new DuplicatePaymentPreventionService( ...$this->deps );
	}

	public function test_init_hooks() {
		$this->mock_hooks_proxy->expects( $this->once() )
			->method( 'add_action' )
			->with( 'template_redirect', [ $this->sut, 'clear_session_processing_order_after_landing_order_received_page' ], 21 );

		$this->sut->init_hooks();
	}

	public function provider_get_authorized_payment_intent_attached_to_order_with_invalid_intent_id_returns_null(): array {
		return [
			'No intent_id is attached'   => [ null ],
			'A setup intent is attached' => [ 'seti_possible_for_a_subscription_id' ],
		];
	}

	/**
	 * @dataProvider provider_get_authorized_payment_intent_attached_to_order_with_invalid_intent_id_returns_null
	 *
	 * @param ?string $invalid_intent_id An invalid payment intent ID. If no intent id is set, this can be null.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_returns_null( ?string $invalid_intent_id ) {
		// Arrange intent ID.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id' )
			->with( $this->processing_order_id )
			->willReturn( $invalid_intent_id );

		// Assert: get_intent is not called.
		$this->mock_wcpay_request( Get_Intention::class, 0 );

		// Act: process the order.
		$result = $this->sut->get_authorized_payment_intent_attached_to_order( $this->processing_order_id );

		// Assert.
		$this->assertNull( $result );
	}

	public function test_get_authorized_payment_intent_attached_to_order_with_get_intent_error_returns_null() {
		$attached_intent_id = 'pi_valid_intent_id';
		$exception_msg      = 'exception_message';

		// Arrange intent ID.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id' )
			->with( $this->processing_order_id )
			->willReturn( $attached_intent_id );

		// Arrange get intent request, throw exception.
		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new \Exception( $exception_msg ) );

		$this->mock_logger
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( $exception_msg ) );

		// Act then assert.
		$result = $this->sut->get_authorized_payment_intent_attached_to_order( $this->processing_order_id );
		$this->assertNull( $result );
	}

	public function provider_get_authorized_payment_intent_attached_to_order_with_invalid_intent_data_returns_null(): array {
		return [
			'Attached PaymentIntent - non-success status - same order_id' => [ 'pi_attached_intent_id', Intent_Status::REQUIRES_ACTION, true ],
			'Attached PaymentIntent - non-success status - different order_id' => [ 'pi_attached_intent_id', Intent_Status::REQUIRES_PAYMENT_METHOD, false ],
			'Attached PaymentIntent - success status - different order_id' => [ 'pi_attached_intent_id', Intent_Status::SUCCEEDED, false ],
		];
	}

	/**
	 * The attached PaymentIntent has invalid info (status or order_id) with the order, so returns null.
	 *
	 * @dataProvider provider_get_authorized_payment_intent_attached_to_order_with_invalid_intent_data_returns_null
	 *
	 * @param  string  $attached_intent_id Attached intent ID to the order.
	 * @param  string  $attached_intent_status Attached intent status.
	 * @param  bool  $same_order_id True when the intent meta order_id is exactly the current processing order_id. False otherwise.
	 */
	public function test_get_authorized_payment_intent_attached_to_order_with_invalid_intent_data_returns_null(
		string $attached_intent_id,
		string $attached_intent_status,
		bool $same_order_id
	) {
		// Arrange intent ID.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id' )
			->with( $this->processing_order_id )
			->willReturn( $attached_intent_id );

		// Arrange mock get_intent.
		$meta_order_id   = $same_order_id ? $this->processing_order_id : $this->processing_order_id - 1;
		$attached_intent = WC_Helper_Intention::create_intention(
			[
				'id'       => $attached_intent_id,
				'status'   => $attached_intent_status,
				'metadata' => [ 'order_id' => $meta_order_id ],
			]
		);
		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $attached_intent );

		// Act then assert.
		$result = $this->sut->get_authorized_payment_intent_attached_to_order( $this->processing_order_id );
		$this->assertNull( $result );
	}

	public function provider_get_authorized_payment_intent_attached_to_order_returns_intent_object(): array {
		$ret = [];
		foreach ( Intent_Status::AUTHORIZED_STATUSES as $status ) {
			$ret[ 'Intent status ' . $status ] = [ $status ];
		}

		return $ret;
	}

	/**
	 * @dataProvider provider_get_authorized_payment_intent_attached_to_order_returns_intent_object
	 */
	public function test_get_authorized_payment_intent_attached_to_order_returns_intent_object( string $intent_authorized_status ) {
		$attached_intent_id = 'pi_attached_intent_id';

		// Arrange intent ID.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id' )
			->with( $this->processing_order_id )
			->willReturn( $attached_intent_id );

		// Arrange mock get_intention.
		$attached_intent = WC_Helper_Intention::create_intention(
			[
				'id'       => $attached_intent_id,
				'status'   => $intent_authorized_status,
				'metadata' => [ 'order_id' => $this->processing_order_id ],
			]
		);

		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $attached_intent );

		// Act then assert.
		$result = $this->sut->get_authorized_payment_intent_attached_to_order( $this->processing_order_id );
		$this->assertSame( $attached_intent, $result );
	}

	public function provider_get_previous_paid_duplicate_order_id() {
		// phpcs:disable
		return [
			'Expected null due to session ID not existed'   => [
				null, 'Any session cart Hash', 888, true, // Session Order Data.
				111, 'Any current cart Hash', 888, true,  // Current Order Data.
				null,                                     // Expected value.
			],
			'Expected null due to same order_id'   => [
				111, 'Any session cart Hash', 888, true,
				111, 'Any current cart Hash', 888, true,
				null,
			],
			'Expected null due to different cart hash'   => [
				111, 'Any session cart Hash', 888, true,
				222, 'Any current cart Hash', 888, true,
				null,
			],
			'Expected null due to different customer ID'   => [
				111, 'SAME_CART_HASH',  888, true,
				222, 'SAME_CART_HASH',  999, true,
				null,
			],
			'Expected null due to current order is NOT pending'   => [
				111, 'SAME_CART_HASH', 999, true,
				222, 'SAME_CART_HASH', 999, false,
				null,
			],
			'Expected null due to session order is NOT paid'   => [
				111, 'SAME_CART_HASH', 999, false,
				222, 'SAME_CART_HASH', 999, true,
				null,
			],
			'Expected session_order_id due to all conditions are matched'   => [
				111, 'SAME_CART_HASH', 999, true,
				222, 'SAME_CART_HASH', 999, true,
				111
			],
		];
		// phpcs:enable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	/**
	 * Note: Separate variable groups in the function signature to match with the provider data, for ease of understanding how they are grouped.
	 *
	 * @dataProvider provider_get_previous_paid_duplicate_order_id
	 */
	public function test_get_previous_paid_duplicate_order_id(
		?int $session_order_id,
		string $session_order_cart_hash,
		int $session_order_customer_id,
		bool $is_session_order_paid,

		int $current_order_id,
		string $current_order_cart_hash,
		int $current_order_customer_id,
		bool $is_current_order_pending,

		?int $expected
	) {
		/** @var $mock_sut DuplicatePaymentPreventionService|MockObject  */
		$mock_sut = $this->getMockBuilder( DuplicatePaymentPreventionService::class )
			->setConstructorArgs( $this->deps )
			->onlyMethods( [ 'get_session_processing_order' ] )
			->getMock();

		// Arrange.
		$mock_sut->expects( $this->once() )
			->method( 'get_session_processing_order' )
			->willReturn( $session_order_id );

		$this->mock_order_service
			->expects( $this->any() )
			->method( 'get_cart_hash' )
			->withConsecutive( [ $current_order_id ], [ $session_order_id ] )
			->willReturnOnConsecutiveCalls( $current_order_cart_hash, $session_order_cart_hash );

		$this->mock_order_service
			->expects( $this->any() )
			->method( 'get_customer_id' )
			->withConsecutive( [ $current_order_id ], [ $session_order_id ] )
			->willReturnOnConsecutiveCalls( $current_order_customer_id, $session_order_customer_id );

		$this->mock_order_service
			->expects( $this->any() )
			->method( 'is_pending' )
			->with( $current_order_id )
			->willReturn( $is_current_order_pending );

		$this->mock_order_service
			->expects( $this->any() )
			->method( 'is_paid' )
			->with( $session_order_id )
			->willReturn( $is_session_order_paid );

		// Act.
		$result = $mock_sut->get_previous_paid_duplicate_order_id( $current_order_id );

		// Assert.
		$this->assertSame( $expected, $result );
	}

	public function test_clean_up_when_detecting_duplicate_order() {
		$duplicate_order_id = 111;
		$current_order_id   = 222;
		/** @var $mock_sut DuplicatePaymentPreventionService|MockObject  */
		$mock_sut   = $this->getMockBuilder( DuplicatePaymentPreventionService::class )
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

		$mock_sut->expects( $this->once() )
			->method( 'remove_session_processing_order' )
			->with( $duplicate_order_id );

		// Act.
		$mock_sut->clean_up_when_detecting_duplicate_order( $duplicate_order_id, $current_order_id );
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
		/** @var $mock_sut DuplicatePaymentPreventionService|MockObject  */
		$mock_sut = $this->getMockBuilder( DuplicatePaymentPreventionService::class )
			->setConstructorArgs( $this->deps )
			->onlyMethods( [ 'get_session_processing_order' ] )
			->getMock();

		$mock_sut->expects( $this->once() )
			->method( 'get_session_processing_order' )
			->willReturn( $session_order_id );
		$this->mock_session_service
			->expects( $this->exactly( $session_invoked_times ) )
			->method( 'set' )
			->with( $this->sut::SESSION_KEY_PROCESSING_ORDER, null );

		// Act.
		$mock_sut->remove_session_processing_order( $to_be_removed_id );
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
		/** @var $mock_sut DuplicatePaymentPreventionService|MockObject  */
		$mock_sut = $this->getMockBuilder( DuplicatePaymentPreventionService::class )
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

		$mock_sut->expects( $this->exactly( $call_remove_session_processing_order ) )
			->method( 'remove_session_processing_order' )
			->with( $order_received_var );

		$mock_sut->clear_session_processing_order_after_landing_order_received_page();
	}
}
