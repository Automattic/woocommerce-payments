<?php
/**
 * Subscription WC_Product_Subscription helper.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Product_Subscription.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Product_Subscription extends WC_Product_Simple {
	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'subscription';
	}
}
