<?php
/**
 * Subscription helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Subscription.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscription extends WC_Order {
	/**
	 * Helper variable for mocking get_related_orders.
	 *
	 * @var array
	 */
	public $related_orders;

	/**
	 * Helper variable for mocking get_last_order.
	 *
	 * @var integer
	 */
	public $last_order;

	public function get_related_orders( $type ) {
		return $this->related_orders;
	}

	public function set_related_orders( $array ) {
		$this->related_orders = $array;
	}

	public function get_last_order() {
		return $this->last_order;
	}

	public function set_last_order( $last_order ) {
		$this->last_order = $last_order;
	}
}
