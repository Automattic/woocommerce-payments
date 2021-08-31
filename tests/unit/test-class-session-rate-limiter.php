<?php
/**
 * Class Session_Rate_Limiter_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

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
		$this->rate_limiter = new Session_Rate_Limiter( $this->key, 2, 600 );
	}

	/**
	 * After-test cleanup
	 */
	public function tearDown() {
		parent::tearDown();
		WC()->session->set( $this->key, [] );
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
}
