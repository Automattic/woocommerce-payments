<?php
/**
 * Class WC_Payments_Action_Scheduler_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Compatibility_Service;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Action_Scheduler_Service unit tests.
 */
class WC_Payments_Action_Scheduler_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_order_service;

	/**
	 * Mock Compatibility_Service.
	 *
	 * @var Compatibility_Service|MockObject
	 */
	private $mock_compatibility_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client            = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_order_service         = $this->createMock( WC_Payments_Order_Service::class );
		$this->mock_compatibility_service = $this->createMock( Compatibility_Service::class );

		$this->action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client, $this->mock_order_service, $this->mock_compatibility_service );
	}

	public function test_update_compatibility_data_hook_registered() {
		$this->action_scheduler_service->init_hooks();

		$this->assertEquals( 10, has_action( Compatibility_Service::UPDATE_COMPATIBILITY_DATA, [ $this->mock_compatibility_service, 'update_compatibility_data_hook' ] ) );
	}

	public function test_track_new_order_action() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( '_payment_method_id', 'pm_131535132531', true );
		$order->add_meta_data( '_stripe_customer_id', 'cu_123', true );
		$order->add_meta_data( '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod', true );
		$order->save_meta_data();

		$this->mock_order_service
			->method( 'get_payment_method_id_for_order' )
			->willReturn( 'pm_131535132531' );
		$this->mock_order_service
			->method( 'get_customer_id_for_order' )
			->willReturn( 'cu_123' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'track_order' )
			->with( $this->get_order_data_mock( $order->get_id() ), false )
			->willReturn( [ 'result' => 'success' ] );

		$this->assertTrue( $this->action_scheduler_service->track_new_order_action( $order->get_id() ) );
	}

	public function test_track_new_order_action_with_no_payment_method() {
		$order = WC_Helper_Order::create_order();
		$order->delete_meta_data( '_payment_method_id' );
		$order->save_meta_data();
		$this->mock_order_service
			->method( 'get_payment_method_id_for_order' )
			->willReturn( '' );

		$this->assertFalse( $this->action_scheduler_service->track_new_order_action( $order ) );
	}

	public function test_track_new_order_action_with_invalid_order_id() {
		$this->assertFalse( $this->action_scheduler_service->track_new_order_action( -4 ) );
	}

	public function test_track_new_order_action_with_invalid_input() {
		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->action_scheduler_service->track_new_order_action( $order->get_data() ) );
	}

	public function test_track_update_order_action() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( '_payment_method_id', 'pm_131535132531', true );
		$order->add_meta_data( '_stripe_customer_id', 'cu_123', true );
		$order->add_meta_data( '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod', true );
		$order->save_meta_data();

		$this->mock_order_service
			->method( 'get_payment_method_id_for_order' )
			->willReturn( 'pm_131535132531' );
		$this->mock_order_service
			->method( 'get_customer_id_for_order' )
			->willReturn( 'cu_123' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'track_order' )
			->with( $this->get_order_data_mock( $order->get_id() ), true )
			->willReturn( [ 'result' => 'success' ] );

		$this->assertTrue( $this->action_scheduler_service->track_update_order_action( $order->get_id() ) );
	}

	public function test_track_update_order_action_will_not_track_order_if_env_is_changed() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( '_payment_method_id', 'pm_13153513253', true );
		$order->add_meta_data( '_stripe_customer_id', 'cu_123', true );
		$order->add_meta_data( '_wcpay_mode', 'foo', true ); // Random value so we are sure that env will be changed.
		$order->save_meta_data();

		$this->mock_api_client->expects( $this->never() )
			->method( 'track_order' );

		$this->action_scheduler_service->track_update_order_action( $order->get_id() );
	}

	public function test_track_update_order_action_with_no_payment_method() {
		$order = WC_Helper_Order::create_order();
		$order->delete_meta_data( '_payment_method_id' );
		$order->save_meta_data();

		$this->assertFalse( $this->action_scheduler_service->track_update_order_action( $order ) );
	}

	public function test_track_update_order_action_with_invalid_order_id() {
		$this->assertFalse( $this->action_scheduler_service->track_update_order_action( -4 ) );
	}

	public function test_track_update_order_action_with_invalid_input() {
		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->action_scheduler_service->track_update_order_action( $order->get_data() ) );
	}

	public function test_schedule_job_dedupes_deferred_callbacks_for_same_key() {
		$this->skip_if_deferred_schedule_path_unavailable();

		$hook  = 'wcpay_test_dedupe_' . uniqid();
		$args  = [ 42 ];
		$group = WC_Payments_Action_Scheduler_Service::GROUP_ID;

		$this->with_uninitialized_action_scheduler(
			function () use ( $hook, $args, $group ) {
				$this->action_scheduler_service->schedule_job( 100, $hook, $args, $group );
				$this->action_scheduler_service->schedule_job( 200, $hook, $args, $group );
				$this->action_scheduler_service->schedule_job( 300, $hook, $args, $group );

				$this->assertSame(
					1,
					$this->count_action_scheduler_init_callbacks(),
					'Duplicate pre-init schedule_job() calls should register exactly one action_scheduler_init callback.'
				);
			}
		);
	}

	public function test_schedule_job_uses_latest_timestamp_when_action_scheduler_initializes() {
		$this->skip_if_deferred_schedule_path_unavailable();

		$hook      = 'wcpay_test_latest_ts_' . uniqid();
		$args      = [ 7 ];
		$group     = WC_Payments_Action_Scheduler_Service::GROUP_ID;
		$latest_ts = time() + HOUR_IN_SECONDS;

		try {
			$this->with_uninitialized_action_scheduler(
				function () use ( $hook, $args, $group, $latest_ts ) {
					$this->action_scheduler_service->schedule_job( time() + 10, $hook, $args, $group );
					$this->action_scheduler_service->schedule_job( time() + 20, $hook, $args, $group );
					$this->action_scheduler_service->schedule_job( $latest_ts, $hook, $args, $group );

					$this->invoke_isolated_action_scheduler_init_callbacks();
				}
			);

			$this->assertSame(
				$latest_ts,
				as_next_scheduled_action( $hook, $args, $group ),
				'The scheduled action should use the latest timestamp from the sequence of deferred calls.'
			);
		} finally {
			as_unschedule_all_actions( $hook, $args, $group );
		}
	}

	public function test_schedule_job_registers_separate_callbacks_for_distinct_keys() {
		$this->skip_if_deferred_schedule_path_unavailable();

		$this->with_uninitialized_action_scheduler(
			function () {
				$this->action_scheduler_service->schedule_job( 100, 'wcpay_test_hook_a_' . uniqid(), [ 1 ] );
				$this->action_scheduler_service->schedule_job( 100, 'wcpay_test_hook_b_' . uniqid(), [ 1 ] );

				$shared_hook = 'wcpay_test_hook_shared_' . uniqid();
				$this->action_scheduler_service->schedule_job( 100, $shared_hook, [ 1 ] );
				$this->action_scheduler_service->schedule_job( 100, $shared_hook, [ 2 ] );

				$this->assertSame(
					4,
					$this->count_action_scheduler_init_callbacks(),
					'Distinct hook+args combinations should each register their own callback.'
				);
			}
		);
	}

	public function test_schedule_job_schedules_directly_when_action_scheduler_already_initialized() {
		$this->skip_if_deferred_schedule_path_unavailable();

		$hook  = 'wcpay_test_direct_' . uniqid();
		$args  = [];
		$group = WC_Payments_Action_Scheduler_Service::GROUP_ID;
		$ts    = time() + HOUR_IN_SECONDS;

		global $wp_actions, $wp_filter;

		$original_action = $wp_actions['action_scheduler_init'] ?? null;
		$original_filter = $wp_filter['action_scheduler_init'] ?? null;

		// Simulate that action_scheduler_init has already fired, and isolate the hook so we can
		// count any callbacks registered by the code under test. This makes the test deterministic
		// regardless of whether the surrounding test environment has bootstrapped ActionScheduler.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulating a fired hook for the duration of this test.
		$wp_actions['action_scheduler_init'] = 1;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolating the hook for the duration of this test.
		$wp_filter['action_scheduler_init'] = new WP_Hook();

		try {
			$this->action_scheduler_service->schedule_job( $ts, $hook, $args, $group );

			$this->assertSame(
				0,
				$this->count_action_scheduler_init_callbacks(),
				'When action_scheduler_init has already fired, schedule_job() should not register any deferred callback.'
			);
			$this->assertSame(
				$ts,
				as_next_scheduled_action( $hook, $args, $group ),
				'Post-init schedule_job() should schedule the action directly.'
			);
		} finally {
			as_unschedule_all_actions( $hook, $args, $group );
			if ( null !== $original_action ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the $wp_actions snapshot taken above.
				$wp_actions['action_scheduler_init'] = $original_action;
			} else {
				unset( $wp_actions['action_scheduler_init'] );
			}
			if ( null !== $original_filter ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the hook snapshot taken above.
				$wp_filter['action_scheduler_init'] = $original_filter;
			} else {
				unset( $wp_filter['action_scheduler_init'] );
			}
		}
	}

	/**
	 * Get a mock of the order data expected to be passed into the `track_order` function.
	 *
	 * @return array
	 */
	private function get_order_data_mock( $order_id ) {
		$order = wc_get_order( $order_id );

		return array_merge(
			$order->get_data(),
			[
				'_payment_method_id'  => $order->get_meta( '_payment_method_id' ),
				'_stripe_customer_id' => $order->get_meta( '_stripe_customer_id' ),
				'_wcpay_mode'         => WC_Payments::mode()->is_test() ? 'test' : 'prod',
			]
		);
	}

	/**
	 * Skip the current test when the WC/ActionScheduler version in use does not expose the
	 * `action_scheduler_init` hook. The deferred-scheduling branch being exercised was introduced
	 * in ActionScheduler 3.5.5 (WC 7.9.0); the CI matrix pins a lower WC_MIN_SUPPORTED_VERSION.
	 */
	private function skip_if_deferred_schedule_path_unavailable() {
		if ( version_compare( WC()->version, '7.9.0', '<' ) ) {
			$this->markTestSkipped( 'schedule_job() deferred-callback path requires WC 7.9.0+ (ActionScheduler 3.5.5+).' );
		}
	}

	/**
	 * Run the given callback while pretending ActionScheduler has not been initialized yet.
	 *
	 * Clears `$wp_actions['action_scheduler_init']` so `did_action()` returns 0, and replaces
	 * `$wp_filter['action_scheduler_init']` with an empty hook so the callbacks registered by
	 * the code under test can be counted and inspected in isolation. State is restored after
	 * the callback returns, even if it throws.
	 */
	private function with_uninitialized_action_scheduler( callable $callback ) {
		global $wp_actions, $wp_filter;

		$original_action = $wp_actions['action_scheduler_init'] ?? null;
		$original_filter = $wp_filter['action_scheduler_init'] ?? null;

		unset( $wp_actions['action_scheduler_init'] );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolating the hook for the duration of this test.
		$wp_filter['action_scheduler_init'] = new WP_Hook();

		try {
			$callback();
		} finally {
			if ( null !== $original_action ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the $wp_actions snapshot taken above.
				$wp_actions['action_scheduler_init'] = $original_action;
			}
			if ( null !== $original_filter ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the $wp_filter snapshot taken above.
				$wp_filter['action_scheduler_init'] = $original_filter;
			} else {
				unset( $wp_filter['action_scheduler_init'] );
			}
		}
	}

	/**
	 * Count the callbacks currently attached to `action_scheduler_init` across all priorities.
	 */
	private function count_action_scheduler_init_callbacks(): int {
		global $wp_filter;

		if ( ! isset( $wp_filter['action_scheduler_init'] ) ) {
			return 0;
		}

		return array_sum( array_map( 'count', $wp_filter['action_scheduler_init']->callbacks ) );
	}

	/**
	 * Directly invoke callbacks currently attached to `action_scheduler_init`.
	 *
	 * Used instead of `do_action()` so we don't also fire the real ActionScheduler
	 * bootstrap (and other unrelated listeners) during the test.
	 */
	private function invoke_isolated_action_scheduler_init_callbacks() {
		global $wp_filter;

		if ( ! isset( $wp_filter['action_scheduler_init'] ) ) {
			return;
		}

		foreach ( $wp_filter['action_scheduler_init']->callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $cb ) {
				call_user_func( $cb['function'] );
			}
		}
	}
}
