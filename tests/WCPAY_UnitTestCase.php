<?php
/**
 * Class WP_UnitTestCase
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * This stub assists IDE in recognizing PHPUnit tests.
 *
 * Class WP_UnitTestCase
 */
class WCPAY_UnitTestCase extends \Yoast\PHPUnitPolyfills\TestCases\TestCase {
	protected function is_wpcom() {
		return defined( 'IS_WPCOM' ) && IS_WPCOM;
	}
}
