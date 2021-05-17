<?php
/**
 * Class WC_Payments_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Test unit tests.
 */
class WC_Payments_Test extends WP_UnitTestCase {

	public function test_it_does_not_modify_gateway_order_if_wcpay_gateway_is_absent() {
		$ordering = [
			'foo' => 0,
			'bar' => 1,
		];

		$this->assertEquals(
			$ordering,
			WC_Payments::replace_wcpay_gateway_with_payment_methods( $ordering )
		);
	}

	public function test_it_does_not_modify_gateway_order_if_payment_method_order_is_empty() {
		WC_Payments::get_gateway()->update_option( 'payment_method_order', [] );

		$ordering = [
			'foo'                  => 0,
			'woocommerce_payments' => 1,
			'bar'                  => 2,
		];

		$this->assertEquals(
			$ordering,
			WC_Payments::replace_wcpay_gateway_with_payment_methods( $ordering )
		);
	}

	public function test_it_replaces_main_wcpay_gateway_with_payment_methods() {
		WC_Payments::get_gateway()->update_option(
			'payment_method_order',
			[
				'woocommerce_payments_foo',
				'woocommerce_payments',
				'woocommerce_payments_bar',
			]
		);

		$ordering = [
			'baz'                  => 0,
			'woocommerce_payments' => 1,
			'quux'                 => 2,
		];

		$expected_ordering = [
			'baz'                      => 0,
			'woocommerce_payments_foo' => 1,
			'woocommerce_payments'     => 2,
			'woocommerce_payments_bar' => 3,
			'quux'                     => 4,
		];

		$this->assertEquals(
			$expected_ordering,
			WC_Payments::replace_wcpay_gateway_with_payment_methods( $ordering )
		);
	}
}
