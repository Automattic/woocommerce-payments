<?php
/**
 * Class WC_Payments_Webhook_Reliability_Service
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;


/**
 * WC_Payments_Webhook_Reliability_Service unit tests.
 */
class WC_Payments_Webhook_Reliability_Service_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Webhook_Reliability_Service
	 */
	private $webhook_reliability_service;

	/**
	 * @var MockObject|WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * @var MockObject|WC_Payments_Action_Scheduler_Service
	 */
	private $mock_action_scheduler_service;

	/**
	 * @var MockObject|WC_Payments_Webhook_Processing_Service
	 */
	private $mock_webhook_processing_service;

	/**
	 * @var array Sample event data for mocking.
	 */
	private $sample_event = [
		'id'      => 'evt_111',
		'object'  => 'event',
		'account' => 'acct_12345',
		'data'    => [],
		'type'    => 'charge.dispute.closed',
	];

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
								->disableOriginalConstructor()
								->setMethods( [ 'get_failed_webhook_events' ] )
								->getMock();

		$this->mock_action_scheduler_service = $this->getMockBuilder( WC_Payments_Action_Scheduler_Service::class )
												->disableOriginalConstructor()
												->setMethods( [ 'schedule_job' ] )
												->getMock();

		$this->mock_webhook_processing_service = $this->createMock( WC_Payments_Webhook_Processing_Service::class );

		$this->webhook_reliability_service = new WC_Payments_Webhook_Reliability_Service(
			$this->mock_api_client,
			$this->mock_action_scheduler_service,
			$this->mock_webhook_processing_service
		);

	}
	/**
	 * Test that necessary filters are added when the WC_Payments_Webhook_Reliability_Service instance is created.
	 *
	 * @return void
	 */
	public function test_filters_registered_properly() {
		$this->assertNotFalse( has_filter( 'woocommerce_payments_account_refreshed', [ $this->webhook_reliability_service, 'maybe_schedule_fetch_events' ] ) );
		$this->assertNotFalse(
			has_filter(
				WC_Payments_Webhook_Reliability_Service::WEBHOOK_FETCH_EVENTS_ACTION,
				[
					$this->webhook_reliability_service,
					'fetch_events_and_schedule_processing_jobs',
				]
			)
		);
		$this->assertNotFalse( has_filter( WC_Payments_Webhook_Reliability_Service::WEBHOOK_PROCESS_EVENT_ACTION, [ $this->webhook_reliability_service, 'process_event' ] ) );
	}

	/**
	 * Test properly scheduling fetch_events job.
	 *
	 * @param  mixed|array $account_data  Account data retrieved from WooCommerce Payments server.
	 * @param  bool        $will_schedule Whether schedule fetch_events.
	 *
	 * @dataProvider provider_maybe_schedule_events
	 * @return void
	 */
	public function test_maybe_schedule_events( $account_data, $will_schedule ) {
		// Set up.
		$this->mock_action_scheduler_service
			->expects( $this->exactly( $will_schedule ? 1 : 0 ) )
			->method( 'schedule_job' )
			->with(
				$this->lessThanOrEqual( time() ),
				WC_Payments_Webhook_Reliability_Service::WEBHOOK_FETCH_EVENTS_ACTION
			);

		// Act.
		$this->webhook_reliability_service->maybe_schedule_fetch_events( $account_data );
	}

	public function provider_maybe_schedule_events(): array {
		return [
			'Account data has nothing'               => [ null, false ],
			'Account data is empty'                  => [ [], false ],
			'Account data has schedule flag - false' => [
				[ WC_Payments_Webhook_Reliability_Service::CONTINUOUS_FETCH_FLAG_ACCOUNT_DATA => false ],
				false,
			],
			'Account data has schedule flag - true'  => [
				[ WC_Payments_Webhook_Reliability_Service::CONTINUOUS_FETCH_FLAG_ACCOUNT_DATA => true ],
				true,
			],
		];
	}

	public function test_fetch_events_gets_api_error() {
		// Prepare.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_failed_webhook_events' )
			->willThrowException( $this->createMock( API_Exception::class ) );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		// Act.
		$this->webhook_reliability_service->fetch_events_and_schedule_processing_jobs();
	}

	/**
	 * Test ensuring to schedule another fetch_events
	 *
	 * @param  array $payload       Payload from the API response.
	 * @param  bool  $will_schedule Whether continue scheduling the next fetch_events.
	 *
	 * @dataProvider provider_fetch_events_schedule_next_fetch_events
	 * @return void
	 */
	public function test_fetch_events_schedule_next_fetch_events( $payload, $will_schedule ) {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_failed_webhook_events' )
			->willReturn( $payload );

		$this->mock_action_scheduler_service
			->expects( $this->exactly( $will_schedule ? 1 : 0 ) )
			->method( 'schedule_job' )
			->with(
				$this->lessThanOrEqual( time() ),
				WC_Payments_Webhook_Reliability_Service::WEBHOOK_FETCH_EVENTS_ACTION
			);

		$this->webhook_reliability_service->fetch_events_and_schedule_processing_jobs();
	}

	public function provider_fetch_events_schedule_next_fetch_events(): array {
		return [
			'has_more flag does not exist' => [ [ '' ], false ],
			'has_more flag is false'       => [ [ 'has_more' => false ], false ],
			'has_more flag is true'        => [ [ 'has_more' => true ], true ],
		];
	}

	/**
	 * Test each data is scheduled
	 *
	 * @param  array    $payload                     Payload from the API response.
	 * @param  string[] $expected_schedule_event_ids Event IDs will be scheduled.
	 *
	 * @dataProvider provider_fetch_events_save_data_and_schedule_jobs
	 * @return void
	 */
	public function test_fetch_events_save_data_and_schedule_process_jobs( array $payload, array $expected_schedule_event_ids ) {
		// Prepare.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_failed_webhook_events' )
			->willReturn( $payload );

		$this->mock_action_scheduler_service
			->expects( $this->exactly( count( $expected_schedule_event_ids ) ) )
			->method( 'schedule_job' )
			->withConsecutive(
				...array_map(
					function ( $event_id ) {
						return [
							$this->greaterThanOrEqual( time() ),
							WC_Payments_Webhook_Reliability_Service::WEBHOOK_PROCESS_EVENT_ACTION,
							$this->callback(
								function( $args ) use ( $event_id ) {
									$this->assertSame( [ 'event_id' => $event_id ], $args );
									return true;
								}
							),
						];
					},
					$expected_schedule_event_ids
				)
			);

		// Act.
		$this->webhook_reliability_service->fetch_events_and_schedule_processing_jobs();

		// Assert save_event_data() is executed by checking the existence with get_event_data().
		foreach ( $expected_schedule_event_ids as $event_id ) {
			$this->assertNotNull( $this->webhook_reliability_service->get_event_data( $event_id ) );
		}
	}

	public function provider_fetch_events_save_data_and_schedule_jobs(): array {
		$event_1 = $this->sample_event;

		$event_2 = [
			'id'      => 'evt_222',
			'object'  => 'event',
			'account' => 'acct_12345',
			'data'    => [],
			'type'    => 'payment_intent.succeeded',
		];

		$event_without_id = [
			'object'  => 'event',
			'account' => 'acct_12345',
			'data'    => [],
			'type'    => 'payment_intent.payment_failed',
		];

		return [
			'Payload has no data'          => [
				[ 'no_data_property' => [] ],
				[],
			],
			'Payload has empty data'       => [
				[ 'data' => [] ],
				[],
			],
			'Payload has two valid events' => [
				[ 'data' => [ $event_1, $event_2 ] ],
				[ 'evt_111', 'evt_222' ],
			],
			'Payload has two valid events and one event without ID' => [
				[ 'data' => [ $event_without_id, $event_1, $event_2 ] ],
				[ 'evt_111', 'evt_222' ],
			],
		];

	}

	/**
	 * Tess processing event data.
	 *
	 * @param array  $event_data   Event data exists in the Db.
	 * @param string $event_id     Event ID will be processed.
	 * @param bool   $will_process Whether to dispatch to Webhook Processing Service.
	 *
	 * @dataProvider provider_process_event
	 * @return void
	 */
	public function test_process_event( $event_data, $event_id, $will_process ) {
		// Prepare.
		$this->webhook_reliability_service->set_event_data( $event_data );

		$this->mock_webhook_processing_service
			->expects( $this->exactly( $will_process ? 1 : 0 ) )
			->method( 'process' )
			->with( $event_data );

		// Act.
		$this->webhook_reliability_service->process_event( $event_id );

		// Assert that the deletion action is always executed.
		$this->assertNull( $this->webhook_reliability_service->get_event_data( $event_id ) );
	}

	public function provider_process_event() {
		return [
			'Provided event ID does not have data' => [ $this->sample_event, 'evt_not_exist', false ],
			'Process event successfully'           => [ $this->sample_event, 'evt_111', true ],
		];
	}
}
