<?php
/**
 * Class Session_Rate_Limiter_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Session_Rate_Limiter;

/**
 * Session_Rate_Limiter unit tests.
 */
class Session_Rate_Limiter_Test extends WCPAY_UnitTestCase {
	const THRESHOLD = 2;
	const DELAY     = 600;

	/**
	 * System under test.
	 *
	 * @var Session_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * @var string
	 */
	private $key;

	/**
	 * The session in place before a test swapped it.
	 *
	 * @var WC_Session|null
	 */
	private $original_session;

	/**
	 * The `query` filter that makes `wc_rate_limits` queries fail, while one is in place.
	 *
	 * @var callable|null
	 */
	private $query_breaker;

	/**
	 * Whether `$wpdb` suppressed errors before a test broke the queries.
	 *
	 * @var bool
	 */
	private $suppressed_errors;

	/**
	 * Messages written to the WooCommerce log, while they are captured.
	 *
	 * @var string[]
	 */
	private $logged = [];

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->key              = Session_Rate_Limiter::SESSION_KEY_DECLINED_CARD_REGISTRY;
		$this->rate_limiter     = new Session_Rate_Limiter( $this->key, self::THRESHOLD, self::DELAY );
		$this->original_session = WC()->session;
	}

	/**
	 * After-test cleanup
	 */
	public function tear_down() {
		global $wpdb;

		$this->restore_rate_limit_queries();
		remove_filter( 'woocommerce_logger_log_message', [ $this, 'capture_log_message' ] );
		WC()->session = $this->original_session;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_rate_limits WHERE rate_limit_key LIKE 'wcpay_card_declined_registry_%'" );
		delete_option( 'wcpay_session_rate_limiter_disabled_' . $this->key );

		parent::tear_down();
	}

	public function test_bump_enables_the_limiter_when_the_threshold_is_reached() {
		$this->rate_limiter->bump();
		$this->assertFalse( $this->rate_limiter->is_limited() );

		$this->rate_limiter->bump();
		$this->assertTrue( $this->rate_limiter->is_limited() );
		$this->assertTrue( $this->rate_limiter->is_limited_or_reserve() );
	}

	public function test_limiter_is_disabled_once_the_delay_has_passed() {
		$this->rate_limiter->bump();
		$this->rate_limiter->bump();

		$this->expire_rate_limit_row();

		$this->assertFalse( $this->rate_limiter->is_limited() );
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
		$this->assertSame( 1, $this->get_remaining_attempts() );
	}

	public function test_reserved_attempts_count_until_they_are_released() {
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );

		$this->assertTrue( $this->rate_limiter->is_limited_or_reserve() );

		$this->rate_limiter->release();
		$this->rate_limiter->release();

		$this->assertSame( 2, $this->get_remaining_attempts() );
		$this->assertFalse( $this->rate_limiter->is_limited() );
	}

	public function test_bump_keeps_the_reserved_attempt() {
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
		$this->rate_limiter->bump();
		$this->rate_limiter->release();

		$this->assertSame( 1, $this->get_remaining_attempts() );

		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
		$this->rate_limiter->bump();
		$this->rate_limiter->release();

		$this->assertTrue( $this->rate_limiter->is_limited() );
	}

	public function test_payments_that_are_not_declined_do_not_use_up_the_allowance() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
			$this->rate_limiter->release();
		}

		$this->assertSame( 2, $this->get_remaining_attempts() );
	}

	public function test_requests_of_one_session_running_at_the_same_time_cannot_exceed_the_threshold() {
		// Each request has its own limiter and checks it before any decline comes back.
		// The checks share one database connection, so this cannot show the update is atomic across connections.
		$requests = [];
		$allowed  = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$requests[ $i ] = new Session_Rate_Limiter( $this->key, self::THRESHOLD, self::DELAY );
			if ( ! $requests[ $i ]->is_limited_or_reserve() ) {
				$allowed[] = $i;
			}
		}

		$this->assertSame( [ 0, 1 ], $allowed );

		foreach ( $allowed as $i ) {
			$requests[ $i ]->bump();
			$requests[ $i ]->release();
		}

		$this->assertTrue( $this->rate_limiter->is_limited() );
		$this->assertSame( 0, $this->get_remaining_attempts() );
	}

	public function test_release_without_a_reservation_does_not_add_attempts() {
		$this->rate_limiter->bump();
		$this->rate_limiter->release();

		$this->assertSame( 1, $this->get_remaining_attempts() );
	}

	public function test_sessions_are_counted_separately() {
		$this->rate_limiter->bump();
		$this->rate_limiter->bump();

		$this->start_new_session();

		$this->assertFalse( $this->rate_limiter->is_limited() );
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
	}

	public function test_limiter_is_not_used_without_a_session() {
		WC()->session = null;

		$this->rate_limiter->bump();
		$this->rate_limiter->bump();

		$this->assertFalse( $this->rate_limiter->is_limited() );
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
	}

	public function test_bump_after_the_delay_has_passed_starts_a_new_allowance() {
		$this->rate_limiter->bump();
		$this->rate_limiter->bump();
		$this->expire_rate_limit_row();

		$this->rate_limiter->bump();

		$this->assertSame( 1, $this->get_remaining_attempts() );
		$this->assertFalse( $this->rate_limiter->is_limited() );
	}

	public function test_attempt_released_after_the_allowance_started_over_does_not_raise_it_above_the_threshold() {
		$slow_request = new Session_Rate_Limiter( $this->key, self::THRESHOLD, self::DELAY );
		$this->assertFalse( $slow_request->is_limited_or_reserve() );
		$this->expire_rate_limit_row();

		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
		$this->rate_limiter->release();
		$slow_request->release();

		$this->assertSame( 2, $this->get_remaining_attempts() );
	}

	public function test_attempt_is_let_through_and_logged_when_the_allowance_cannot_be_started() {
		$this->capture_log();
		$this->break_rate_limit_queries( 'INSERT' );

		$is_limited = $this->rate_limiter->is_limited_or_reserve();

		$this->restore_rate_limit_queries();
		$this->rate_limiter->release();

		$this->assertFalse( $is_limited );
		$this->assertNull( $this->get_remaining_attempts() );
		$this->assertCount( 1, $this->logged );
		$this->assertStringStartsWith( 'Session rate limiter wcpay_card_declined_registry could not start an allowance: ', $this->logged[0] );
	}

	public function test_attempt_is_let_through_and_logged_when_it_cannot_be_reserved() {
		$this->rate_limiter->bump();
		$this->rate_limiter->bump();
		$this->capture_log();
		$this->break_rate_limit_queries( 'UPDATE' );

		$is_limited = $this->rate_limiter->is_limited_or_reserve();

		$this->restore_rate_limit_queries();
		$this->rate_limiter->release();

		$this->assertFalse( $is_limited );
		$this->assertSame( 0, $this->get_remaining_attempts() );
		$this->assertCount( 1, $this->logged );
		$this->assertStringStartsWith( 'Session rate limiter wcpay_card_declined_registry could not reserve an attempt: ', $this->logged[0] );
	}

	public function test_failure_to_count_an_attempt_is_logged() {
		$this->capture_log();
		$this->break_rate_limit_queries( 'INSERT' );

		$this->rate_limiter->bump();

		$this->assertCount( 1, $this->logged );
		$this->assertStringStartsWith( 'Session rate limiter wcpay_card_declined_registry could not count an attempt: ', $this->logged[0] );
	}

	public function test_failure_to_hand_back_a_reserved_attempt_is_logged() {
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
		$this->capture_log();
		$this->break_rate_limit_queries( 'UPDATE' );

		$this->rate_limiter->release();

		$this->restore_rate_limit_queries();
		$this->assertSame( 1, $this->get_remaining_attempts() );
		$this->assertCount( 1, $this->logged );
		$this->assertStringStartsWith( 'Session rate limiter wcpay_card_declined_registry could not hand back a reserved attempt: ', $this->logged[0] );
	}

	public function test_limiter_turned_off_by_the_merchant_lets_attempts_through() {
		$this->rate_limiter->bump();
		$this->rate_limiter->bump();

		update_option( 'wcpay_session_rate_limiter_disabled_' . $this->key, 'yes' );

		$this->assertFalse( $this->rate_limiter->is_limited() );
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
	}

	public function test_bump_stores_the_decline_on_the_order() {
		$order = WC_Helper_Order::create_order();

		$this->rate_limiter->bump( $order );

		$this->assertCount( 1, wc_get_order( $order->get_id() )->get_meta( '_wcpay_card_declined_registry', false ) );
	}

	public function test_bump_keeps_declines_saved_by_a_parallel_attempt() {
		$order_id       = WC_Helper_Order::create_order()->get_id();
		$first_attempt  = wc_get_order( $order_id );
		$second_attempt = wc_get_order( $order_id );
		$this->rate_limiter->is_limited( $first_attempt );
		$this->rate_limiter->is_limited( $second_attempt );

		$this->rate_limiter->bump( $first_attempt );
		$this->rate_limiter->bump( $second_attempt );
		$this->start_new_session();

		$this->assertTrue( $this->rate_limiter->is_limited( wc_get_order( $order_id ) ) );
	}

	public function test_order_registry_limits_the_order_in_a_new_session() {
		$order = WC_Helper_Order::create_order();
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->bump( $order );
		$this->start_new_session();

		$this->assertTrue( $this->rate_limiter->is_limited( wc_get_order( $order->get_id() ) ) );
	}

	public function test_order_registry_limits_the_order_without_a_session() {
		$order = WC_Helper_Order::create_order();
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->bump( $order );
		WC()->session = null;

		$this->assertTrue( $this->rate_limiter->is_limited( wc_get_order( $order->get_id() ) ) );
	}

	public function test_order_registry_does_not_limit_another_order() {
		$order = WC_Helper_Order::create_order();
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->bump( $order );
		$this->start_new_session();

		$this->assertFalse( $this->rate_limiter->is_limited( WC_Helper_Order::create_order() ) );
	}

	public function test_order_registry_is_cleared_after_the_delay() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( '_wcpay_card_declined_registry', time() - 5000 );
		$order->add_meta_data( '_wcpay_card_declined_registry', time() - 5000 );
		$order->save();

		$this->assertFalse( $this->rate_limiter->is_limited( $order ) );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( '_wcpay_card_declined_registry' ) );
	}

	public function test_order_registry_drops_declines_older_than_a_day() {
		$order     = WC_Helper_Order::create_order();
		$a_day_ago = time() - DAY_IN_SECONDS - MINUTE_IN_SECONDS;
		$order->add_meta_data( '_wcpay_card_declined_registry', $a_day_ago );
		$order->add_meta_data( '_wcpay_card_declined_registry', $a_day_ago );
		$order->save();

		$this->rate_limiter->bump( $order );

		$this->assertFalse( $this->rate_limiter->is_limited( $order ) );
	}

	public function test_order_registry_removes_expired_rows_below_the_threshold() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( '_wcpay_card_declined_registry', time() - DAY_IN_SECONDS - MINUTE_IN_SECONDS );
		$order->add_meta_data( '_wcpay_card_declined_registry', time() - DAY_IN_SECONDS - MINUTE_IN_SECONDS );
		$order->add_meta_data( '_wcpay_card_declined_registry', time() );
		$order->save();

		$this->assertFalse( $this->rate_limiter->is_limited( $order ) );
		$this->assertCount( 1, wc_get_order( $order->get_id() )->get_meta( '_wcpay_card_declined_registry', false ) );
	}

	public function test_order_check_refreshes_meta_loaded_before_the_previous_attempt() {
		$order_id       = WC_Helper_Order::create_order()->get_id();
		$first_attempt  = wc_get_order( $order_id );
		$second_attempt = wc_get_order( $order_id );
		$second_attempt->get_meta( '_wcpay_card_declined_registry', false );

		$this->rate_limiter->bump( $first_attempt );
		$this->rate_limiter->bump( $first_attempt );
		$this->start_new_session();

		$this->assertTrue( $this->rate_limiter->is_limited( $second_attempt ) );
	}

	public function test_disabled_option_turns_off_the_order_registry() {
		update_option( 'wcpay_session_rate_limiter_disabled_' . $this->key, 'yes' );
		$order = WC_Helper_Order::create_order();
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->bump( $order );

		$this->assertFalse( $this->rate_limiter->is_limited( $order ) );
	}

	public function test_order_registry_limits_reservations_in_a_new_session() {
		$order = WC_Helper_Order::create_order();
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->bump( $order );
		$this->start_new_session();

		$this->assertTrue( $this->rate_limiter->is_limited_or_reserve( wc_get_order( $order->get_id() ) ) );
		$this->assertNull( $this->get_remaining_attempts() );
	}

	public function test_bump_with_an_order_keeps_the_reserved_session_attempt() {
		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve( $order ) );
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->release();

		$this->assertSame( 1, $this->get_remaining_attempts() );
		$this->assertCount( 1, wc_get_order( $order->get_id() )->get_meta( '_wcpay_card_declined_registry', false ) );
	}

	/**
	 * Attempts left for the current session.
	 *
	 * @return int|null
	 */
	private function get_remaining_attempts(): ?int {
		global $wpdb;

		$remaining = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rate_limit_remaining FROM {$wpdb->prefix}wc_rate_limits WHERE rate_limit_key = %s",
				$this->key . '_' . WC()->session->get_customer_id()
			)
		);

		return null === $remaining ? null : (int) $remaining;
	}

	/**
	 * Makes `wc_rate_limits` queries that start with the given statement fail, until `restore_rate_limit_queries()`.
	 *
	 * @param string $statement The SQL statement to break, such as `INSERT` or `UPDATE`.
	 */
	private function break_rate_limit_queries( string $statement ) {
		global $wpdb;

		$this->suppressed_errors = $wpdb->suppress_errors( true );
		$this->query_breaker     = function ( $query ) use ( $statement ) {
			if ( false !== strpos( $query, 'wc_rate_limits' ) && 0 === strpos( ltrim( $query ), $statement ) ) {
				return 'SELECT * FROM wcpay_missing_rate_limits_table';
			}
			return $query;
		};
		add_filter( 'query', $this->query_breaker );
	}

	/**
	 * Lets `wc_rate_limits` queries run again after `break_rate_limit_queries()`.
	 */
	private function restore_rate_limit_queries() {
		global $wpdb;

		if ( null === $this->query_breaker ) {
			return;
		}

		remove_filter( 'query', $this->query_breaker );
		$wpdb->suppress_errors( $this->suppressed_errors );
		$this->query_breaker = null;
	}

	/**
	 * Starts collecting the messages written to the WooCommerce log in `$this->logged`.
	 */
	private function capture_log() {
		add_filter( 'woocommerce_logger_log_message', [ $this, 'capture_log_message' ] );
	}

	/**
	 * Records a WooCommerce log message once, since the logger runs this filter for each of its handlers.
	 *
	 * @param string $message The message.
	 * @return string
	 */
	public function capture_log_message( $message ) {
		if ( ! in_array( $message, $this->logged, true ) ) {
			$this->logged[] = $message;
		}
		return $message;
	}

	/**
	 * Moves the current session's rate limit expiry into the past.
	 */
	private function expire_rate_limit_row() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wc_rate_limits SET rate_limit_expiry = %d WHERE rate_limit_key = %s",
				time() - 1,
				$this->key . '_' . WC()->session->get_customer_id()
			)
		);
	}

	/**
	 * Replaces the current session with a new one, as a shopper who drops the session cookie gets.
	 */
	private function start_new_session() {
		$other_session = new WC_Session_Handler();
		$other_session->init();
		WC()->session = $other_session;
	}
}
