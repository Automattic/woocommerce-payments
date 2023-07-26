<?php
/**
 * Class Trait_Order_Info_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request\Order_Info;

/**
 * WCPay\Core\Server\Order_Info unit tests.
 */
class Trait_Order_Info_Test extends WCPAY_UnitTestCase {
	use Order_Info;

	public function test_build_order_info() {
		$mock_order     = WC_Helper_Order::create_order();
		$mock_order_id  = $mock_order->get_id();
		$edit_order_url = "http://example.org/wp-admin/post.php?post=$mock_order_id&action=edit";

		$expected = [
			'number'        => $mock_order_id,
			'url'           => $edit_order_url,
			'customer_url'  => null,
			'subscriptions' => [],
		];

		$result = $this->build_order_info( $mock_order );

		$this->assertEquals( $expected, $result );
	}

	public function test_build_order_info_with_subscriptions() {
		$mock_order     = WC_Helper_Order::create_order();
		$mock_order_id  = $mock_order->get_id();
		$edit_order_url = "http://example.org/wp-admin/post.php?post=$mock_order_id&action=edit";

		$expected = [
			'number'        => $mock_order_id,
			'url'           => $edit_order_url,
			'customer_url'  => null,
			'subscriptions' => [
				[
					'number' => $mock_order_id,
					'url'    => $edit_order_url,
				],
			],
		];

		$mock_subscription = new WC_Subscription();
		$mock_subscription->set_parent( $mock_order );

		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function( $parent_order ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		$result = $this->build_order_info( $mock_order );

		$this->assertEquals( $expected, $result );
	}
}
