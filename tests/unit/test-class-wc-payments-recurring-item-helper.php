<?php
/**
 * Class WC_Payments_Recurring_Item_Helper_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Unit tests for recurring-item detection helper.
 */
class WC_Payments_Recurring_Item_Helper_Test extends WCPAY_UnitTestCase {
	public function tear_down() {
		WC_Subscriptions::set_wcs_order_contains_subscription( null );
		WC_Subscriptions::wcs_cart_contains_renewal( null );
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions_Product::$is_subscription = true;
		remove_all_filters( 'wcpay_checkout_has_recurring_items' );

		parent::tear_down();
	}

	public function test_order_has_recurring_items_uses_subscriptions_by_default() {
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $order ) {
				return 123 === $order;
			}
		);

		$this->assertTrue( WC_Payments_Recurring_Item_Helper::has_recurring_items( 'order', 123 ) );
		$this->assertFalse( WC_Payments_Recurring_Item_Helper::has_recurring_items( 'order', 456 ) );
	}

	public function test_cart_has_recurring_items_detects_subscription_renewals() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () {
				return true;
			}
		);

		$this->assertTrue( WC_Payments_Recurring_Item_Helper::has_recurring_items( 'cart' ) );
	}

	public function test_product_has_recurring_items_can_be_extended_by_filter() {
		$product = WC_Helper_Product::create_simple_product();

		WC_Subscriptions_Product::$is_subscription = false;

		add_filter(
			'wcpay_checkout_has_recurring_items',
			function ( $has_recurring_items, $context, $subject ) use ( $product ) {
				if ( 'product' === $context && $subject instanceof WC_Product && $subject->get_id() === $product->get_id() ) {
					return true;
				}

				return $has_recurring_items;
			},
			10,
			3
		);

		$this->assertTrue( WC_Payments_Recurring_Item_Helper::has_recurring_items( 'product', $product ) );
	}
}
