<?php
/**
 * Class Experimental_Abtest_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Experimental ABTest Test class
 */
class Experimental_Abtest_Test extends WCPAY_UnitTestCase {
	public function test_get_variation_returns_control_if_consent_is_false() {
		$abtest = new \WCPay\Experimental_Abtest( '', 'woocommerce', false );

		$result = $abtest->get_variation( 'test_name' );

		$this->assertSame( $result, 'control' );
	}

	public function test_get_variation_returns_control_if_test_name_is_empty() {
		$abtest = new \WCPay\Experimental_Abtest( '', 'woocommerce', false );

		$result = $abtest->get_variation( '' );

		$this->assertSame( $result, 'control' );
	}

	public function test_get_variation_returns_control_if_test_name_is_invalid() {
		$abtest = new \WCPay\Experimental_Abtest( '', 'woocommerce', false );

		$result = $abtest->get_variation( 'invalid_test_name' );

		$this->assertSame( $result, 'control' );
	}

	public function test_cache_key_is_scoped_to_the_anon_id() {
		$first  = $this->get_cache_key( new \WCPay\Experimental_Abtest( 'woo:cJ8kL2mN', 'woocommerce', true ), 'some_test' );
		$second = $this->get_cache_key( new \WCPay\Experimental_Abtest( 'woo:pQ4rS6tU', 'woocommerce', true ), 'some_test' );

		$this->assertNotSame(
			$first,
			$second,
			'The transient is site-wide, so two admins on one store must not share a cached variation.'
		);
		$this->assertStringStartsWith( 'abtest_variation_some_test_', $first );
	}

	/**
	 * Invoke the protected get_cache_key() on an abtest instance.
	 *
	 * @param \WCPay\Experimental_Abtest $abtest    The abtest instance.
	 * @param string                     $test_name Name of the A/B test.
	 * @return string
	 */
	private function get_cache_key( \WCPay\Experimental_Abtest $abtest, string $test_name ): string {
		$method = new ReflectionMethod( \WCPay\Experimental_Abtest::class, 'get_cache_key' );
		$method->setAccessible( true );

		return $method->invoke( $abtest, $test_name );
	}
}
