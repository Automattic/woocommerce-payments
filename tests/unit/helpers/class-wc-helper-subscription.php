<?php
/**
 * Subscription helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

// WC_Mock_WC_Data is needed to mock '*_meta_data' methods required in tests.
require_once dirname( __FILE__ ) . '/class-wc-mock-wc-data.php';

/**
 * Class WC_Subscription.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscription extends WC_Mock_WC_Data {
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

	/**
	 * Helper variable for mocking *_payment_method
	 *
	 * @var mixed
	 */
	public $payment_method;

	/**
	 * Helper variable for mocking *_payment_method_title
	 *
	 * @var string
	 */
	public $payment_method_title;

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

	public function set_payment_method( $payment_method ) {
		$this->payment_method = $payment_method;
	}

	public function get_payment_method() {
		return $this->payment_method;
	}

	public function set_payment_method_title( $payment_method_title ) {
		$this->payment_method_title = $payment_method_title;
	}

	public function get_payment_method_title() {
		return $this->payment_method_title;
	}
}
