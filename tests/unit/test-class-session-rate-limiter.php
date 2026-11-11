<?php
/**
 * Class Session_Rate_Limiter_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Rate_Limiter_Enabled_Exception;
use WCPay\Session_Rate_Limiter;

/**
 * WC_Payments_Fraud_Service unit tests.
 */
class Session_Rate_Limiter_Test extends WCPAY_UnitTestCase {
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
	 * Session in place before each test, restored by tear_down.
	 *
	 * @var WC_Session|null
	 */
	private $session;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->key          = Session_Rate_Limiter::SESSION_KEY_DECLINED_CARD_REGISTRY;
		$this->rate_limiter = new Session_Rate_Limiter( $this->key, 2, 600 );
		$this->session      = WC()->session;
	}

	/**
	 * After-test cleanup
	 */
	public function tear_down() {
		parent::tear_down();
		WC()->session = $this->session;
		WC()->session->set( $this->key, [] );
		delete_option( 'wcpay_session_rate_limiter_disabled_' . $this->key );
	}

	public function test_rate_limiter_stores_element_in_registry() {
		$this->rate_limiter->bump();
		$registry = WC()->session->get( $this->key );
		$this->assertEquals( count( $registry ), 1 );
	}

	public function test_rate_limiter_is_enabled_when_threshold_is_reached() {
		$this->rate_limiter->bump();

		// It will give false as the threshold of 2 is not reached.
		$this->assertFalse( $this->rate_limiter->is_limited() );

		// Saving another event in the registry will reach the threshold.
		$this->rate_limiter->bump();
		$this->assertTrue( $this->rate_limiter->is_limited() );

		// Modify the last element of the registry to an earlier time to see the rate limiter disables.
		$registry    = WC()->session->get( $this->key );
		$registry[1] = $registry[1] - 5000;
		WC()->session->set( $this->key, $registry );

		$this->assertFalse( $this->rate_limiter->is_limited() );
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
		WC()->session->set( $this->key, [] );

		$this->assertTrue( $this->rate_limiter->is_limited( wc_get_order( $order_id ) ) );
	}

	public function test_order_registry_limits_the_order_in_a_new_session() {
		$order = WC_Helper_Order::create_order();
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->bump( $order );
		WC()->session->set( $this->key, [] );

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
		WC()->session->set( $this->key, [] );

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

	public function test_order_lock_blocks_a_second_attempt_until_released() {
		$order          = WC_Helper_Order::create_order();
		$second_attempt = new Session_Rate_Limiter( $this->key, 2, 600 );
		$this->rate_limiter->acquire_order_lock( $order );

		try {
			$second_attempt->acquire_order_lock( $order );
			$this->fail( 'A second attempt acquired the same order lock.' );
		} catch ( Rate_Limiter_Enabled_Exception $e ) {
			$this->assertSame( 'rate_limiter_order_busy', $e->get_error_code() );
		} finally {
			$this->rate_limiter->release_order_lock( $order );
		}

		$second_attempt->acquire_order_lock( $order );
		$second_attempt->release_order_lock( $order );
	}

	public function test_order_check_refreshes_meta_loaded_before_the_previous_attempt() {
		$order_id       = WC_Helper_Order::create_order()->get_id();
		$first_attempt  = wc_get_order( $order_id );
		$second_attempt = wc_get_order( $order_id );
		$second_attempt->get_meta( '_wcpay_card_declined_registry', false );

		$this->rate_limiter->acquire_order_lock( $first_attempt );
		$this->rate_limiter->bump( $first_attempt );
		$this->rate_limiter->bump( $first_attempt );
		$this->rate_limiter->release_order_lock( $first_attempt );
		WC()->session->set( $this->key, [] );

		$this->rate_limiter->acquire_order_lock( $second_attempt );
		try {
			$this->assertTrue( $this->rate_limiter->is_limited( $second_attempt ) );
		} finally {
			$this->rate_limiter->release_order_lock( $second_attempt );
		}
	}

	public function test_expired_order_lock_can_be_replaced_without_releasing_the_new_owner() {
		global $wpdb;

		$order          = WC_Helper_Order::create_order();
		$option_name    = 'wcpay_rate_limiter_lock_' . md5( $this->key ) . '_' . $order->get_id();
		$second_attempt = new Session_Rate_Limiter( $this->key, 2, 600 );
		$third_attempt  = new Session_Rate_Limiter( $this->key, 2, 600 );
		$this->rate_limiter->acquire_order_lock( $order );
		$wpdb->update( $wpdb->options, [ 'option_value' => '0:expired' ], [ 'option_name' => $option_name ] );

		try {
			$second_attempt->acquire_order_lock( $order );
			$this->rate_limiter->release_order_lock( $order );
			try {
				$third_attempt->acquire_order_lock( $order );
				$this->fail( 'The old owner released a replacement lock.' );
			} catch ( Rate_Limiter_Enabled_Exception $e ) {
				$this->assertSame( 'rate_limiter_order_busy', $e->get_error_code() );
			}
		} finally {
			$second_attempt->release_order_lock( $order );
		}
	}

	public function test_disabled_option_turns_off_the_order_registry() {
		update_option( 'wcpay_session_rate_limiter_disabled_' . $this->key, 'yes' );
		$order = WC_Helper_Order::create_order();
		$this->rate_limiter->bump( $order );
		$this->rate_limiter->bump( $order );

		$this->assertFalse( $this->rate_limiter->is_limited( $order ) );
	}
}
