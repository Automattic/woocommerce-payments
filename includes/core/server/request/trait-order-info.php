<?php
/**
 * Trait file for WCPay\Core\Server\Request\Order_info.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore;
use WC_Order;

/**
 * Tait for getting order data or order info.
 */
trait Order_Info {
	/**
	 * Creates the array representing order for frontend.
	 *
	 * @param WC_Order $order The order.
	 * @return array
	 */
	private function build_order_info( WC_Order $order ): array {
		$order_info = [
			'number'       => $order->get_order_number(),
			'url'          => $order->get_edit_order_url(),
			'customer_url' => $this->get_customer_url( $order ),
		];

		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$order_info['subscriptions'] = [];

			$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => [ 'parent', 'renewal' ] ] );
			foreach ( $subscriptions as $subscription ) {
				$order_info['subscriptions'][] = [
					'number' => $subscription->get_order_number(),
					'url'    => $subscription->get_edit_order_url(),
				];
			}
		}
		return $order_info;
	}

	/**
	 * Generates url to single customer in analytics table.
	 *
	 * @param WC_Order $order The Order.
	 * @return string|null
	 */
	private function get_customer_url( WC_Order $order ) {
		$customer_id = DataStore::get_existing_customer_id_from_order( $order );

		if ( ! $customer_id ) {
			return null;
		}

		return add_query_arg(
			[
				'page'      => 'wc-admin',
				'path'      => '/customers',
				'filter'    => 'single_customer',
				'customers' => $customer_id,
			],
			'admin.php'
		);
	}
}
