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
	private $from_state;

	/**
	 * State of the transition.
	 *
	 * @var string
	 */
	private $to_state;

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
	 * @param string $from_state State of the transition.
	 * @param string $to_state State of the transition.
	 * @param array  $changes Changes made during the transition.
	 * @param int    $timestamp Timestamp of the transition.
	 */
	public function __construct( string $order_id, ?string $from_state, ?string $to_state, array $changes, int $timestamp ) {
		$this->order_id   = $order_id;
		$this->from_state = $from_state;
		$this->to_state   = $to_state;
		$this->changes    = $changes;
		$this->timestamp  = $timestamp;
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
	 * Sets the changes made during the transition.
	 *
	 * @param array $changes Changes made during the transition.
	 */
	public function set_changes( array $changes ) {
		$this->changes = $changes;
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
	 * Returns the from state of the transition.
	 *
	 * @return string|null From state of the transition.
	 */
	public function get_from_state() : ?string {
		return $this->from_state;
	}

	/**
	 * Returns the to state of the transition.
	 *
	 * @return string|null To state of the transition.
	 */
	public function get_to_state() : ?string {
		return $this->to_state;
	}

	/**
	 * Sets the to state of the transition.
	 *
	 * @param string $to_state To state of the transition.
	 */
	public function set_to_state( string $to_state ) {
		$this->to_state = $to_state;
	}
}
