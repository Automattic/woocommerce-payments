<?php
/**
 * Class WC_Payments_Recurring_Item_Helper_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Subscriptions\RecurringItemHelper;

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
		$order       = WC_Helper_Order::create_order();
		$other_order = WC_Helper_Order::create_order();

		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $checked_order ) use ( $order ) {
				return $checked_order instanceof WC_Order && $checked_order->get_id() === $order->get_id();
			}
		);

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'order', $order->get_id() ) );
		$this->assertFalse( RecurringItemHelper::has_recurring_items( 'order', $other_order->get_id() ) );
	}

	public function test_cart_has_recurring_items_detects_subscription_products() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'cart' ) );
	}

	public function test_cart_has_recurring_items_detects_subscription_renewals() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () {
				return true;
			}
		);

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'cart' ) );
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

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'product', $product ) );
	}
}
