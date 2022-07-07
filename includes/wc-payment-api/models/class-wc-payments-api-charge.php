<?php
/**
 * WC_Payments_API_Charge class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A charge object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Charge {
	/**
	 * Charge ID
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Charge amount
	 *
	 * @var int
	 */
	private $amount;

	/**
	 * Time charge created
	 *
	 * Server-side times are presumed to be UTC, (de)serializers should take care to set/respect the timezone on the
	 * DateTime object.
	 *
	 * @var DateTime
	 */
	private $created;

	/**
	 * Flag indicated whether the charge has been captured or not
	 *
	 * @var bool
	 */
	private $captured;

	/**
	 * Payment method details object
	 *
	 * @var array
	 */
	private $payment_method_details;

	/**
	 * WC_Payments_API_Charge constructor.
	 *
	 * @param string   $id                     - ID of the charge.
	 * @param integer  $amount                 - Amount charged.
	 * @param DateTime $created                - Time charge created.
	 * @param array    $payment_method_details - Payment method details object.
	 */
	public function __construct(
		$id,
		$amount,
		DateTime $created,
		$payment_method_details = []
	) {
		$this->id                     = $id;
		$this->amount                 = $amount;
		$this->created                = $created;
		$this->payment_method_details = $payment_method_details;

		// Set default properties.
		$this->captured = false;
	}

	/**
	 * Gets charge ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Gets charge amount
	 *
	 * @return int
	 */
	public function get_amount() {
		return $this->amount;
	}

	/**
	 * Gets charge created time
	 *
	 * @return DateTime
	 */
	public function get_created() {
		return $this->created;
	}

	/**
	 * Is the charge captured?
	 *
	 * @return bool
	 */
	public function is_captured() {
		return $this->captured;
	}

	/**
	 * Sets charge captured flag
	 *
	 * @param bool $captured - Flag indicating capture status of charge.
	 */
	public function set_captured( $captured ) {
		$this->captured = $captured;
	}

	/**
	 * Returns the payment method details associated with this charge
	 *
	 * @return array
	 */
	public function get_payment_method_details() {
		return $this->payment_method_details;
	}
}
