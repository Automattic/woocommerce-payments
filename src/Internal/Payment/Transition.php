<?php
/**
 * Class Transition
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

/**
 * A Transition object used for logging.
 * Contains the details of a state transition during the payment process.
 */
class Transition {

	/**
	 * Order ID.
	 *
	 * @var string
	 */
	private $order_id;

	/**
	 * State of the transition.
	 *
	 * @var string
	 */
	private $state;

	/**
	 * Changes made during the transition.
	 *
	 * @var array
	 */
	private $changes;

	/**
	 * Timestamp of the transition.
	 *
	 * @var int
	 */
	private $timestamp;

	/**
	 * Constructs the class, with changes, order ID, timestamp and state.
	 *
	 * @param string $order_id Order ID.
	 * @param string $state State of the transition.
	 * @param array  $changes Changes made during the transition.
	 * @param int    $timestamp Timestamp of the transition.
	 */
	public function __construct( string $order_id, string $state, array $changes, int $timestamp ) {
		$this->order_id  = $order_id;
		$this->state     = $state;
		$this->changes   = $changes;
		$this->timestamp = $timestamp;
	}

	/**
	 * Returns the changes made during the transition.
	 *
	 * @return array Changes made during the transition.
	 */
	public function get_changes() : array {
		return $this->changes;
	}

	/**
	 * Returns the order ID.
	 *
	 * @return string Order ID.
	 */
	public function get_order_id() : string {
		return $this->order_id;
	}

	/**
	 * Returns the timestamp of the transition.
	 *
	 * @return int Timestamp of the transition.
	 */
	public function get_timestamp() : int {
		return $this->timestamp;
	}

	/**
	 * Returns the state of the transition.
	 *
	 * @return string State of the transition.
	 */
	public function get_state() : string {
		return $this->state;
	}

}
