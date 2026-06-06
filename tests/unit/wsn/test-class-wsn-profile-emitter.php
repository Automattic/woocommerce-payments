<?php
/**
 * Class WSN_Profile_Emitter_Test
 *
 * @package WooCommerce\Payments\WSN
 */

/**
 * Tests for the Profile sync emitter.
 *
 * Coverage focus (in order of business risk):
 *
 *  1. **Skip-emit guard** — when the composed payload's version matches
 *     the last-synced version, NO send fires. This is what makes the
 *     6h backstop cheap: ticks that find no change are free.
 *
 *  2. **Send → state update ordering** — on success, the transport is
 *     called AND last_synced + last_synced_version are updated AND the
 *     last_error transient is cleared. On failure, the transport is
 *     called AND state is NOT advanced AND last_error IS set. Ordering
 *     matters because the next push's skip-emit guard reads what this
 *     push wrote.
 *
 *  3. **Failure containment** — when the transport throws, execute_push
 *     never throws upward. AS handlers that throw cause AS to mark the
 *     action failed and retry on its own schedule, which would collide
 *     with our debounce+backstop retry model.
 *
 *  4. **Debounce shape** — schedule_debounced_push delegates to the
 *     centralized scheduler service so dedup (re-debounce-on-rapid-change)
 *     happens via the schedule_job mechanism that already handles it.
 */
class WSN_Profile_Emitter_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WSN_Profile_Transport|PHPUnit\Framework\MockObject\MockObject
	 */
	private $transport;

	/**
	 * @var WC_Payments_Action_Scheduler_Service|PHPUnit\Framework\MockObject\MockObject
	 */
	private $scheduler;

	/**
	 * @var WSN_Profile_Emitter
	 */
	private $emitter;

	public function set_up() {
		parent::set_up();

		$this->transport = $this->createMock( WSN_Profile_Transport::class );
		$this->scheduler = $this->createMock( WC_Payments_Action_Scheduler_Service::class );

		$this->emitter = new WSN_Profile_Emitter( $this->transport, $this->scheduler );

		// Clean state between tests so prior runs don't poison the
		// skip-emit guard.
		delete_option( WSN_Profile_Emitter::OPTION_LAST_SYNCED );
		delete_option( WSN_Profile_Emitter::OPTION_LAST_SYNCED_VERSION );
		delete_transient( WSN_Profile_Emitter::TRANSIENT_LAST_ERROR );
		delete_transient( WSN_Profile_Emitter::TRANSIENT_BACKSTOP_SCHEDULED );
	}

	public function tear_down() {
		delete_option( WSN_Profile_Emitter::OPTION_LAST_SYNCED );
		delete_option( WSN_Profile_Emitter::OPTION_LAST_SYNCED_VERSION );
		delete_transient( WSN_Profile_Emitter::TRANSIENT_LAST_ERROR );
		delete_transient( WSN_Profile_Emitter::TRANSIENT_BACKSTOP_SCHEDULED );

		parent::tear_down();
	}

	public function test_schedule_debounced_push_delegates_to_scheduler_with_short_delay() {
		$this->scheduler
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->callback(
					function ( $ts ) {
						// DEBOUNCE_SECONDS ± a tick — allow ±2s for clock skew.
						$expected = time() + WSN_Profile_Emitter::DEBOUNCE_SECONDS;
						return $ts >= $expected - 2 && $ts <= $expected + 2;
					}
				),
				WSN_Profile_Emitter::ACTION_PUSH
			);

		$this->emitter->schedule_debounced_push();
	}

	public function test_execute_push_sends_payload_on_first_run_when_no_last_synced_version() {
		$this->transport
			->expects( $this->once() )
			->method( 'send' )
			->with(
				$this->callback(
					function ( $payload ) {
						return is_array( $payload )
							&& ! empty( $payload['payload_version'] )
							&& ! empty( $payload['schema_version'] );
					}
				)
			);

		$this->emitter->execute_push();

		$this->assertNotNull(
			WSN_Profile_Emitter::get_last_synced_time(),
			'last_synced timestamp must be set after a successful push.'
		);
		$this->assertNotEmpty(
			WSN_Profile_Emitter::get_last_synced_version(),
			'last_synced_version must be set after a successful push.'
		);
		$this->assertNull(
			WSN_Profile_Emitter::get_last_error(),
			'last_error transient must be clear after a successful push.'
		);
	}

	public function test_execute_push_skips_when_payload_version_matches_last_synced_version() {
		// Compose once to learn the version this fixture produces.
		$first_payload = WSN_Profile_Payload_Composer::compose();
		update_option(
			WSN_Profile_Emitter::OPTION_LAST_SYNCED_VERSION,
			$first_payload['payload_version']
		);

		// Now the emitter should skip — same data = same version.
		$this->transport
			->expects( $this->never() )
			->method( 'send' );

		$this->emitter->execute_push();
	}

	public function test_execute_push_emits_when_payload_version_differs_from_last_synced_version() {
		// Seed a known-stale version that the composer won't reproduce.
		update_option(
			WSN_Profile_Emitter::OPTION_LAST_SYNCED_VERSION,
			'stale-' . str_repeat( 'f', 58 )
		);

		$this->transport
			->expects( $this->once() )
			->method( 'send' );

		$this->emitter->execute_push();
	}

	public function test_execute_push_records_error_transient_when_transport_throws() {
		$this->transport
			->method( 'send' )
			->willThrowException( new \Exception( 'Test failure: server returned 500.' ) );

		$this->emitter->execute_push();

		$error = WSN_Profile_Emitter::get_last_error();
		$this->assertIsArray( $error );
		$this->assertSame( 'Test failure: server returned 500.', $error['message'] );
		$this->assertIsInt( $error['timestamp'] );
	}

	public function test_execute_push_does_not_advance_state_when_send_fails() {
		// Seed a known prior-success version. After the failed push, that
		// version must still be the "last synced" — a failure must not bump
		// state forward, or the next push's skip-emit guard would silently
		// drop the change we failed to deliver.
		$known_prior_version = 'prior-' . str_repeat( 'a', 58 );
		update_option( WSN_Profile_Emitter::OPTION_LAST_SYNCED_VERSION, $known_prior_version );
		update_option( WSN_Profile_Emitter::OPTION_LAST_SYNCED, 1000 );

		$this->transport
			->method( 'send' )
			->willThrowException( new \Exception( 'Boom.' ) );

		$this->emitter->execute_push();

		$this->assertSame( $known_prior_version, WSN_Profile_Emitter::get_last_synced_version() );
		$this->assertSame( 1000, WSN_Profile_Emitter::get_last_synced_time() );
	}

	public function test_execute_push_never_throws_when_transport_throws() {
		$this->transport
			->method( 'send' )
			->willThrowException( new \Exception( 'Catastrophe.' ) );

		// If execute_push re-throws, this line is never reached and the
		// test fails with the uncaught exception. The assertion is
		// implicit: we made it past the call.
		$this->emitter->execute_push();
		$this->assertTrue( true );
	}

	public function test_execute_push_clears_last_error_on_subsequent_success() {
		// Seed a prior error.
		set_transient(
			WSN_Profile_Emitter::TRANSIENT_LAST_ERROR,
			[
				'message'   => 'Old error.',
				'timestamp' => 1000,
			],
			HOUR_IN_SECONDS
		);

		// `send` returns void — no willReturn needed. Default behavior is
		// to succeed silently, which is exactly the "successful push" case.
		$this->transport
			->expects( $this->once() )
			->method( 'send' );

		$this->emitter->execute_push();

		$this->assertNull(
			WSN_Profile_Emitter::get_last_error(),
			'A successful push must clear any prior last_error transient — otherwise the Hub UI would keep showing a stale "sync failed" banner.'
		);
	}

	public function test_force_immediate_push_schedules_at_current_time() {
		$this->scheduler
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->callback(
					function ( $ts ) {
						// "Immediate" = within 2 seconds of now.
						return $ts >= time() - 2 && $ts <= time() + 2;
					}
				),
				WSN_Profile_Emitter::ACTION_PUSH
			);

		$this->emitter->force_immediate_push();
	}

	public function test_state_accessors_return_null_when_unset() {
		$this->assertNull( WSN_Profile_Emitter::get_last_synced_time() );
		$this->assertSame( '', WSN_Profile_Emitter::get_last_synced_version() );
		$this->assertNull( WSN_Profile_Emitter::get_last_error() );
	}

	public function test_ensure_backstop_scheduled_short_circuits_when_transient_is_set() {
		// Cache flag is the "we know it's scheduled" hint that lets us
		// skip the AS DB query. With the transient set, the method must
		// return false (already scheduled, nothing to do) without
		// touching as_has_scheduled_action.
		set_transient(
			WSN_Profile_Emitter::TRANSIENT_BACKSTOP_SCHEDULED,
			1,
			WSN_Profile_Emitter::BACKSTOP_INTERVAL_SECONDS
		);

		$result = $this->emitter->ensure_backstop_scheduled();

		$this->assertFalse(
			$result,
			'With the cached-scheduled transient set, ensure_backstop_scheduled must short-circuit. ' .
				'A regression here means an AS DB query fires on every WP request when the sub-flag is ON.'
		);
	}

	public function test_init_hooks_registers_listeners() {
		$this->emitter->init_hooks();

		// Profile-tab Save fires force_immediate_push (no debounce) — a
		// merchant Save click is a single, deliberate event with no burst
		// to collapse. Regression guard: if this re-attaches to
		// schedule_debounced_push, merchants will wait DEBOUNCE_SECONDS before seeing
		// their WSN storefront reflect Profile-tab changes.
		$this->assertNotFalse(
			has_action( 'wcpay_wsn_profile_changed', [ $this->emitter, 'force_immediate_push' ] ),
			'wcpay_wsn_profile_changed must route to force_immediate_push so Profile-tab saves push immediately, not after the DEBOUNCE_SECONDS debounce window.'
		);
		// Appearance-change path stays debounced — these events can fire
		// multiple times in one request via theme/customizer/plugin-update
		// hooks; the debounce collapses bursts.
		$this->assertNotFalse(
			has_action( 'wcpay_woopay_appearance_changed', [ $this->emitter, 'schedule_debounced_push' ] )
		);
		$this->assertNotFalse(
			has_action( WSN_Profile_Emitter::ACTION_PUSH, [ $this->emitter, 'execute_push' ] )
		);
		$this->assertNotFalse(
			has_action( WSN_Profile_Emitter::ACTION_BACKSTOP, [ $this->emitter, 'schedule_debounced_push' ] )
		);
		$this->assertNotFalse(
			has_action( 'wcpay_wsn_profile_force_resync', [ $this->emitter, 'force_immediate_push' ] ),
			'init_hooks must register a force_immediate_push listener for the Retry-button-driven action — otherwise POST /profile-resync fires the action but nothing happens.'
		);

		// Cleanup so other tests aren't affected by these listener
		// registrations.
		remove_action( 'wcpay_wsn_profile_changed', [ $this->emitter, 'force_immediate_push' ] );
		remove_action( 'wcpay_woopay_appearance_changed', [ $this->emitter, 'schedule_debounced_push' ] );
		remove_action( WSN_Profile_Emitter::ACTION_PUSH, [ $this->emitter, 'execute_push' ] );
		remove_action( WSN_Profile_Emitter::ACTION_BACKSTOP, [ $this->emitter, 'schedule_debounced_push' ] );
		remove_action( 'wcpay_wsn_profile_force_resync', [ $this->emitter, 'force_immediate_push' ] );
	}
}
