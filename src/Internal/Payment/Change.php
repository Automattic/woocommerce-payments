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
	 * @var string|null
	 */
	private $old_value;

	/**
	 * Current value, updated aganist the key.
	 *
	 * @var string|null
	 */
	private $new_value;

	/**
	 * Constructs the class, with key, former and current values.
	 *
	 * @param string      $key Identifier or key for the changed value.
	 * @param string|null $old_value Former value for the key.
	 * @param string|null $new_value Current value, updated aganist the key.
	 */
	public function __construct( $key, $old_value, $new_value ) {
		$this->key       = $key;
		$this->old_value = $old_value;
		$this->new_value = $new_value;
	}

	/**
	 * Returns a string representation of the object.
	 *
	 * @return string String representation of the object.
	 */
	public function __toString() {
		return sprintf(
			'Change: %s from %s to %s',
			$this->key,
			var_export( $this->old_value, true ), // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
			var_export( $this->new_value, true ) // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);
	}

}
