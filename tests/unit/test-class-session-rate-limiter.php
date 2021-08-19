<?php
/**
 * Class Session_Rate_Limiter_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;
use WCPay\Session_Rate_Limiter;

/**
 * WC_Payments_Fraud_Service unit tests.
 */
class Session_Rate_Limiter_Test extends WP_UnitTestCase {
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
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->key          = Session_Rate_Limiter::SESSION_KEY_DECLINED_CARD_REGISTRY;
		$this->rate_limiter = new Session_Rate_Limiter();
	}

	/**
	 * After-test cleanup
	 */
	public function tearDown() {
		parent::tearDown();
		WC()->session->set( $this->key, [] );
		WC()->session->set( $this->rate_limiter->get_action_id( ( $this->key ) ), null );
	}

	public function test_rate_limiter_stores_element_in_registry() {
		$this->rate_limiter->save_datetime_in_key( $this->key, 5, 1000 );
		$registry = WC()->session->get( $this->key );
		$this->assertEquals( count( $registry ), 1 );
	}

	public function test_rate_limiter_is_enabled_when_threshold_is_reached() {
		$this->rate_limiter->save_datetime_in_key( $this->key, 2, 10 * 60 );

		// It will give false as the threshold of 2 is not reached.
		$this->assertFalse( $this->rate_limiter->is_rate_limiter_enabled( $this->key ) );

		// Saving another event in the registry will reach the threshold.
		$this->rate_limiter->save_datetime_in_key( $this->key, 2, 10 * 60 );
		$this->assertTrue( $this->rate_limiter->is_rate_limiter_enabled( $this->key ) );

		// Modify the stored rate limiter to the past to see the rate limiter disabled.
		WC()->session->set( $this->rate_limiter->get_action_id( ( $this->key ) ), time() - 60 );
		$this->assertFalse( $this->rate_limiter->is_rate_limiter_enabled( $this->key ) );
	}
}
