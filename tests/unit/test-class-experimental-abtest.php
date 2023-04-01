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
};
