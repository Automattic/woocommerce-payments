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
	 * Helper variable for mocking the subscription's parent order.
	 *
	 * @var WC_Order
	 */
	public $parent_order;

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

	/**
	 * Trial End timestamp
	 *
	 * @var int
	 */
	public $trial_end;

	/**
	 * Next Payment timestamp
	 *
	 * @var int
	 */
	public $next_payment;

	/**
	 * End timestamp
	 *
	 * @var int
	 */
	public $end;

	/**
	 * Helper variable for mocking the subscription's status.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Helper variable for mocking the subscription manual check.
	 *
	 * @var bool
	 */
	public $manual;

	/**
	 * Currency.
	 *
	 * @var string
	 */
	public $currency = 'USD';

	/**
	 * Helper variable for mocking the subscription's billing period.
	 *
	 * @var string
	 */
	public $billing_period = 'month';

	/**
	 * Helper variable for mocking the subscription's billing interval.
	 *
	 * @var string
	 */
	public $billing_interval = 1;

	/**
	 * A helper function for handling function calls not yet implimented on this helper.
	 *
	 * Attempts to get the value by checking if it has been set as an object property.
	 * Otherwise calls the parent order's function equalivent, if it exists.
	 *
	 * @param string $name
	 * @param array $arguments
	 *
	 * @throws Exception when the function or matching object property doesn't exist.
	 */
	public function __call( $name, $arguments = [] ) {
		$property = str_replace( 'get_', '', $name );

		// If the property has been set manually, return that. Otherwise return the parent order's result if that is callable.
		if ( isset( $this->$property ) ) {
			return $this->$property;
		} elseif ( $this->parent_order && is_callable( [ $this->parent_order, $name ] ) ) {
			return call_user_func_array( [ $this->parent_order, $name ], $arguments );
		}

		throw new Exception( "Call to undefined method WC_Subscription::{$name}()" );
	}

	public function get_parent_id() {
		return ! empty( $this->parent_order ) ? $this->parent_order->get_id() : 0;
	}

	public function get_parent() {
		return ! empty( $this->parent_order ) ? $this->parent_order : false;
	}

	public function set_parent( $parent_order ) {
		$this->parent_order = $parent_order;
	}

	public function get_items( $type = 'line_item' ) {
		return ! empty( $this->parent_order ) ? $this->parent_order->get_items( $type ) : [];
	}

	public function get_fees() {
		return ! empty( $this->parent_order ) ? $this->parent_order->get_fees() : [];
	}

	public function get_shipping_methods() {
		return ! empty( $this->parent_order ) ? $this->parent_order->get_shipping_methods() : [];
	}

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

	public function get_time( $time ) {
		return $this->{$time};
	}

	public function update_dates( $dates = [] ) {
		foreach ( $dates as $date_type => $date_string ) {
			$this->{$date_type} = strtotime( $date_string );
		}
	}

	public function get_currency() {
		return $this->currency;
	}

	public function add_order_note( $note = '' ) {
		// do nothing.
	}

	public function payment_failed( $new_status = 'on-hold' ) {
		$this->status = $new_status;
	}

	public function get_status() {
		return $this->status;
	}

	public function get_billing_period() {
		return $this->billing_period;
	}

	public function get_billing_interval() {
		return $this->billing_interval;
	}

	public function has_status( $status ) {
		return ( is_array( $status ) && in_array( $this->get_status(), $status, true ) ) || $this->get_status() === $status;
	}

	public function is_manual() {
		return $this->manual;
	}

	public function set_requires_manual_renewal( $bool ) {
		$this->manual = $bool;
	}
}
