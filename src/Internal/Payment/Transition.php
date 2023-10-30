<?php
/**
 * Class Transition
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WCPay\Internal\Payment\Change;

/**
 * A Transition object used for logging.
 * Contains the details of a state transition during the payment process.
 */
class Transition {

	/**
	 * State of the transition.
	 *
	 * @var string|null
	 */
	private $from_state;

	/**
	 * State of the transition.
	 *
	 * @var string|null
	 */
	private $to_state;

	/**
	 * Changes made during the transition.
	 *
	 * @var Change[]
	 */
	private $changes;

	/**
	 * Timestamp of the transition.
	 *
	 * @var int
	 */
	private $timestamp;

	/**
	 * Constructs the class, with all the parameters.
	 *
	 * @param int|null    $timestamp Timestamp of the transition.
	 * @param string|null $from_state State of the transition.
	 * @param string|null $to_state State of the transition.
	 * @param Change[]    $changes Changes made during the transition.
	 */
	public function __construct( ?int $timestamp, ?string $from_state, ?string $to_state = null, array $changes = [] ) {
		$this->from_state = $from_state;
		$this->to_state   = $to_state;
		$this->changes    = $changes;
		$this->timestamp  = $timestamp ?? time();
	}

	/**
	 * Returns the changes made during the transition.
	 *
	 * @return Change[] Changes made during the transition.
	 */
	public function get_changes() : array {
		return $this->changes;
	}

	/**
	 * Adds a change to the changes array.
	 *
	 * @param Change $change Change.
	 */
	public function add_change( Change $change ) : void {
		$this->changes[] = $change;
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

	/**
	 * Returns the timestamp.
	 *
	 * @return int Timestamp.
	 */
	public function get_timestamp() : int {
		return $this->timestamp;
	}
}
