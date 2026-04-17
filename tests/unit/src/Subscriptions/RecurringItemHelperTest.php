<?php
/**
 * Class RecurringItemHelperTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Subscriptions;

use WCPay\Subscriptions\RecurringItemHelper;
use WCPAY_UnitTestCase;
use WC_Cart;
use WC_Helper_Order;
use WC_Helper_Product;
use WC_Order;
use WC_Product;
use WC_Subscriptions;
use WC_Subscriptions_Cart;
use WC_Subscriptions_Product;

/**
 * Unit tests for recurring-item detection helper.
 *
 * @coversDefaultClass \WCPay\Subscriptions\RecurringItemHelper
 */
class RecurringItemHelperTest extends WCPAY_UnitTestCase {
	public function tear_down() {
		WC_Subscriptions::set_wcs_order_contains_subscription( null );
		WC_Subscriptions::wcs_cart_contains_renewal( null );
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions_Product::$is_subscription = true;
		remove_all_filters( 'wcpay_checkout_has_recurring_items' );
		RecurringItemHelper::reset_cache();

		parent::tear_down();
	}

	/**
	 * @covers ::has_recurring_items
	 * @covers ::order_has_recurring_items
	 */
	public function test_order_has_recurring_items_uses_subscriptions_by_default() {
		$order       = WC_Helper_Order::create_order();
		$other_order = WC_Helper_Order::create_order();

		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $checked_order ) use ( $order ) {
				$checked_id = $checked_order instanceof WC_Order ? $checked_order->get_id() : (int) $checked_order;
				return $checked_id === $order->get_id();
			}
		);

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'order', $order->get_id() ) );
		$this->assertFalse( RecurringItemHelper::has_recurring_items( 'order', $other_order->get_id() ) );
	}

	/**
	 * @covers ::order_has_recurring_items
	 */
	public function test_order_has_recurring_items_memoizes_per_order_id() {
		$order      = WC_Helper_Order::create_order();
		$call_count = 0;

		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () use ( &$call_count ) {
				$call_count++;
				return true;
			}
		);

		RecurringItemHelper::order_has_recurring_items( $order->get_id() );
		RecurringItemHelper::order_has_recurring_items( $order );
		RecurringItemHelper::order_has_recurring_items( $order->get_id() );

		$this->assertSame( 1, $call_count, 'wcs_order_contains_subscription should only run once per order ID within a request.' );
	}

	/**
	 * @covers ::order_has_recurring_items
	 */
	public function test_order_has_recurring_items_skips_cache_for_non_identifiable_subject() {
		$call_count = 0;
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () use ( &$call_count ) {
				$call_count++;
				return true;
			}
		);

		$this->assertTrue( RecurringItemHelper::order_has_recurring_items( 'pi_mock' ) );
		$this->assertTrue( RecurringItemHelper::order_has_recurring_items( 'pi_mock' ) );

		$this->assertSame( 2, $call_count, 'Non-identifiable subjects must not be memoized.' );
	}

	/**
	 * @covers ::has_recurring_items
	 * @covers ::cart_has_recurring_items
	 */
	public function test_cart_has_recurring_items_detects_subscription_products() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'cart' ) );
	}

	/**
	 * @covers ::cart_has_recurring_items
	 */
	public function test_cart_has_recurring_items_detects_subscription_renewals() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () {
				return true;
			}
		);

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'cart' ) );
	}

	/**
	 * @covers ::cart_has_recurring_items
	 */
	public function test_cart_has_recurring_items_short_circuits_when_subscription_present() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );
		$renewal_called = false;
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () use ( &$renewal_called ) {
				$renewal_called = true;
				return true;
			}
		);

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'cart' ) );
		$this->assertFalse( $renewal_called, 'Renewal check should be skipped once a subscription is detected.' );
	}

	/**
	 * @covers ::cart_has_recurring_items
	 */
	public function test_cart_has_recurring_items_inspects_non_global_cart_items() {
		$subscription_product                      = WC_Helper_Product::create_simple_product();
		WC_Subscriptions_Product::$is_subscription = true;

		$cart = $this->createMock( WC_Cart::class );
		$cart->method( 'get_cart' )->willReturn(
			[
				[ 'data' => $subscription_product ],
			]
		);

		$this->assertTrue( RecurringItemHelper::cart_has_recurring_items( $cart ) );
	}

	/**
	 * @covers ::cart_has_recurring_items
	 */
	public function test_cart_has_recurring_items_returns_false_for_non_cart_subject() {
		$this->assertFalse( RecurringItemHelper::cart_has_recurring_items( 'not-a-cart' ) );
	}

	/**
	 * @covers ::cart_has_recurring_items
	 */
	public function test_cart_has_recurring_items_returns_false_for_non_global_cart_without_subscription() {
		WC_Subscriptions_Product::$is_subscription = false;

		$cart = $this->createMock( WC_Cart::class );
		$cart->method( 'get_cart' )->willReturn(
			[
				[ 'data' => WC_Helper_Product::create_simple_product() ],
			]
		);

		$this->assertFalse( RecurringItemHelper::cart_has_recurring_items( $cart ) );
	}

	/**
	 * @covers ::has_recurring_items
	 */
	public function test_has_recurring_items_returns_false_for_unknown_context_without_firing_filter() {
		$filter_fired = false;
		add_filter(
			'wcpay_checkout_has_recurring_items',
			function ( $has_recurring ) use ( &$filter_fired ) {
				$filter_fired = true;
				return $has_recurring;
			}
		);

		$this->assertFalse( RecurringItemHelper::has_recurring_items( 'unknown', null ) );
		$this->assertFalse( $filter_fired, 'Filter must not fire for unsupported contexts.' );
	}

	/**
	 * @covers ::order_has_recurring_items
	 */
	public function test_order_has_recurring_items_returns_false_when_subscriptions_disabled() {
		RecurringItemHelper::reset_cache();
		$previous_version          = WC_Subscriptions::$version;
		WC_Subscriptions::$version = '2.1.0';

		try {
			$this->assertFalse( RecurringItemHelper::order_has_recurring_items( 42 ) );
		} finally {
			WC_Subscriptions::$version = $previous_version;
			RecurringItemHelper::reset_cache();
		}
	}

	/**
	 * @covers ::cart_has_recurring_items
	 */
	public function test_cart_has_recurring_items_returns_false_when_subscriptions_disabled() {
		RecurringItemHelper::reset_cache();
		$previous_version          = WC_Subscriptions::$version;
		WC_Subscriptions::$version = '2.1.0';
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		try {
			$this->assertFalse( RecurringItemHelper::cart_has_recurring_items() );
		} finally {
			WC_Subscriptions::$version = $previous_version;
			RecurringItemHelper::reset_cache();
		}
	}

	/**
	 * @covers ::product_has_recurring_items
	 */
	public function test_product_has_recurring_items_happy_path() {
		$product                                   = WC_Helper_Product::create_simple_product();
		WC_Subscriptions_Product::$is_subscription = true;

		$this->assertTrue( RecurringItemHelper::product_has_recurring_items( $product ) );
	}

	/**
	 * @covers ::product_has_recurring_items
	 */
	public function test_product_has_recurring_items_returns_false_for_null_product() {
		$this->assertFalse( RecurringItemHelper::product_has_recurring_items( null ) );
	}

	/**
	 * @covers ::has_recurring_items
	 * @covers ::product_has_recurring_items
	 */
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

	/**
	 * @covers ::has_recurring_items
	 */
	public function test_has_recurring_items_resolves_numeric_product_id_for_filter() {
		$product                                   = WC_Helper_Product::create_simple_product();
		WC_Subscriptions_Product::$is_subscription = false;

		$captured_subject = null;
		add_filter(
			'wcpay_checkout_has_recurring_items',
			function ( $has_recurring_items, $context, $subject ) use ( &$captured_subject ) {
				if ( 'product' === $context ) {
					$captured_subject = $subject;
				}
				return $has_recurring_items;
			},
			10,
			3
		);

		RecurringItemHelper::has_recurring_items( 'product', $product->get_id() );

		$this->assertInstanceOf( WC_Product::class, $captured_subject );
		$this->assertSame( $product->get_id(), $captured_subject->get_id() );
	}

	/**
	 * @covers ::has_recurring_items
	 */
	public function test_has_recurring_items_passes_unresolvable_product_subject_through_to_filter() {
		$captured_subject = 'initial';
		add_filter(
			'wcpay_checkout_has_recurring_items',
			function ( $has_recurring_items, $context, $subject ) use ( &$captured_subject ) {
				if ( 'product' === $context ) {
					$captured_subject = $subject;
				}
				return $has_recurring_items;
			},
			10,
			3
		);

		RecurringItemHelper::has_recurring_items( 'product', 'not-numeric' );

		$this->assertSame( 'not-numeric', $captured_subject );
	}

	/**
	 * @covers ::has_recurring_items
	 */
	public function test_has_recurring_items_resolves_cart_subject_for_filter() {
		$cart = $this->createMock( WC_Cart::class );
		$cart->method( 'get_cart' )->willReturn( [] );

		$captured_subject = null;
		add_filter(
			'wcpay_checkout_has_recurring_items',
			function ( $has_recurring_items, $context, $subject ) use ( &$captured_subject ) {
				if ( 'cart' === $context ) {
					$captured_subject = $subject;
				}
				return $has_recurring_items;
			},
			10,
			3
		);

		RecurringItemHelper::has_recurring_items( 'cart', $cart );

		$this->assertSame( $cart, $captured_subject );
	}

	/**
	 * @covers ::has_recurring_items
	 */
	public function test_has_recurring_items_falls_back_to_global_cart_for_filter() {
		$captured_subject = 'initial';
		add_filter(
			'wcpay_checkout_has_recurring_items',
			function ( $has_recurring_items, $context, $subject ) use ( &$captured_subject ) {
				if ( 'cart' === $context ) {
					$captured_subject = $subject;
				}
				return $has_recurring_items;
			},
			10,
			3
		);

		RecurringItemHelper::has_recurring_items( 'cart' );

		$this->assertInstanceOf( WC_Cart::class, $captured_subject );
		$this->assertSame( WC()->cart, $captured_subject );
	}

	/**
	 * @covers ::has_recurring_items
	 */
	public function test_has_recurring_items_resolves_order_subject_for_filter() {
		$order = WC_Helper_Order::create_order();

		$captured_subject = null;
		add_filter(
			'wcpay_checkout_has_recurring_items',
			function ( $has_recurring_items, $context, $subject ) use ( &$captured_subject ) {
				if ( 'order' === $context ) {
					$captured_subject = $subject;
				}
				return $has_recurring_items;
			},
			10,
			3
		);

		RecurringItemHelper::has_recurring_items( 'order', $order->get_id() );
		$this->assertInstanceOf( WC_Order::class, $captured_subject );
		$this->assertSame( $order->get_id(), $captured_subject->get_id() );

		$captured_subject = null;
		RecurringItemHelper::has_recurring_items( 'order', $order );
		$this->assertSame( $order, $captured_subject );

		$captured_subject = null;
		RecurringItemHelper::has_recurring_items( 'order', 'not-numeric' );
		$this->assertSame( 'not-numeric', $captured_subject );
	}

	/**
	 * @covers ::has_recurring_items
	 */
	public function test_has_recurring_items_casts_non_boolean_filter_return_values() {
		add_filter(
			'wcpay_checkout_has_recurring_items',
			function () {
				return 'yes';
			}
		);

		$this->assertTrue( RecurringItemHelper::has_recurring_items( 'order', 123 ) );

		remove_all_filters( 'wcpay_checkout_has_recurring_items' );

		add_filter(
			'wcpay_checkout_has_recurring_items',
			function () {
				return 0;
			}
		);

		$this->assertFalse( RecurringItemHelper::has_recurring_items( 'order', 123 ) );
	}

	/**
	 * @covers ::is_subscriptions_enabled
	 */
	public function test_is_subscriptions_enabled_reflects_plugin_version() {
		RecurringItemHelper::reset_cache();
		$previous_version          = WC_Subscriptions::$version;
		WC_Subscriptions::$version = '2.1.0';

		try {
			$this->assertFalse( RecurringItemHelper::is_subscriptions_enabled() );
		} finally {
			WC_Subscriptions::$version = $previous_version;
			RecurringItemHelper::reset_cache();
		}

		$this->assertTrue( RecurringItemHelper::is_subscriptions_enabled() );
	}

	/**
	 * @covers ::is_subscriptions_enabled
	 */
	public function test_is_subscriptions_enabled_memoizes_result() {
		RecurringItemHelper::reset_cache();

		$this->assertTrue( RecurringItemHelper::is_subscriptions_enabled() );

		$previous_version          = WC_Subscriptions::$version;
		WC_Subscriptions::$version = '2.1.0';
		try {
			$this->assertTrue(
				RecurringItemHelper::is_subscriptions_enabled(),
				'Cached result should be reused until reset_cache() is called.'
			);
		} finally {
			WC_Subscriptions::$version = $previous_version;
			RecurringItemHelper::reset_cache();
		}
	}
}
