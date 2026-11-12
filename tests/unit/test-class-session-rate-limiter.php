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
		// Each request builds its own limiter, and every request checks the limiter before any decline comes back.
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

		$other_session = new WC_Session_Handler();
		$other_session->init();
		WC()->session = $other_session;

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

	public function test_limiter_turned_off_by_the_merchant_lets_attempts_through() {
		$this->rate_limiter->bump();
		$this->rate_limiter->bump();

		update_option( 'wcpay_session_rate_limiter_disabled_' . $this->key, 'yes' );

		$this->assertFalse( $this->rate_limiter->is_limited() );
		$this->assertFalse( $this->rate_limiter->is_limited_or_reserve() );
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
}
