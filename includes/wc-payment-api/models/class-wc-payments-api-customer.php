<?php
/**
 * WC_Payments_API_Customer class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A authorization object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Customer {
	/**
	 * ID
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * pPayment method
	 *
	 * @var bool
	 */
	private $default_payment_method;

	/**
	 * WC_Payments_API_Charge constructor.
	 *
	 * @param string $id        - ID.
	 * @param string $name   - Amount.
	 * @param string $default_payment_method    - Payment method.
	 */
	public function __construct(
		string $id,
		string $name,
		string $default_payment_method
	) {
		$this->id       = $id;
		$this->name   = $name;
		$this->default_payment_method   = $default_payment_method;

	}

	/**
	 * Gets ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Gets name
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Gets default payment method
	 *
	 * @return string
	 */
	public function get_default_payment_method() {
		return $this->default_payment_method;
	}
}
