<?php
/**
 * Class Change
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

/**
 * A change object used for logging.
 * Key, former and current values are set as string.
 */
class Change {

	/**
	 * Identifier or key for the changed value.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Former value for the key.
	 *
	 * @var mixed
	 */
	private $old_value;

	/**
	 * Current value, updated against the key.
	 *
	 * @var mixed
	 */
	private $new_value;

	/**
	 * Constructs the class, with key, former and current values.
	 *
	 * @param string $key Identifier or key for the changed value.
	 * @param mixed  $old_value Former value for the key.
	 * @param mixed  $new_value Current value, updated against the key.
	 */
	public function __construct( $key, $old_value, $new_value ) {
		$this->key       = $key;
		$this->old_value = $old_value;
		$this->new_value = $new_value;
	}

	/**
	 * Returns the key.
	 *
	 * @return string Identifier or key for the changed value.
	 */
	public function get_key(): string {
		return $this->key;
	}

	/**
	 * Returns the previous value.
	 *
	 * @return mixed Former value for the key.
	 */
	public function get_old_value() {
		return $this->old_value;
	}

	/**
	 * Returns the current value.
	 *
	 * @return mixed Current value, updated against the key.
	 */
	public function get_new_value() {
		return $this->new_value;
	}
}
