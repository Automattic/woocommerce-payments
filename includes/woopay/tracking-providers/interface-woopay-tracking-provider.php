<?php
/**
 * WooPay Tracking Provider Interface
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for shipping tracking data providers.
 *
 * Each provider reads tracking data from a specific shipping plugin's
 * storage format and normalizes it into a common structure.
 */
interface WooPay_Tracking_Provider {
	/**
	 * Whether this provider can supply tracking for the given order.
	 *
	 * Should check plugin availability (class_exists/function_exists)
	 * AND whether tracking data exists for this order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_available( \WC_Order $order ): bool;

	/**
	 * Extract shipments from the order in normalized format.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array[] Array of shipment arrays, each with keys:
	 *   - tracking_number (string)
	 *   - carrier_name    (string)
	 *   - tracking_url    (string)
	 *   - date_shipped    (string, Y-m-d format)
	 *   - status          (string, e.g. 'fulfilled')
	 *   - items           (array of ['name' => string, 'quantity' => int])
	 */
	public function get_shipments( \WC_Order $order ): array;

	/**
	 * Return the WordPress action hooks this provider listens to for changes.
	 *
	 * Used by the sync class to register webhook triggers dynamically.
	 *
	 * @return array[] Array of hook configs, each with keys:
	 *   - hook      (string) The WordPress action hook name.
	 *   - arg_count (int)    Number of arguments the hook passes.
	 *   - meta_key  (string, optional) When the hook is a generic WordPress
	 *               meta-write event (added_post_meta, updated_post_meta,
	 *               added_order_meta, updated_order_meta) the sync class
	 *               filters fires by this key. The hook signature is then
	 *               assumed to be ($meta_id, $object_id, $meta_key, $meta_value)
	 *               and arg_count must be 4.
	 */
	public function get_hooks(): array;
}
