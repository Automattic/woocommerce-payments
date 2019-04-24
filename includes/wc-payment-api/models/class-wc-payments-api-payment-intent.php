<?php
/**
 * WC_Payments_API_Payment_Intent class
 *
 * @package WooCommerce\Payments
 */

/**
 * A payment intent object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Payment_Intent {
	/**
	 * Payment intent ID
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
	 * The status of the payment intent
	 *
	 * @var string
	 */
	private $status;

	/**
	 * WC_Payments_API_Payment_Intent constructor.
	 *
	 * @param string   $id      - ID of the charge.
	 * @param integer  $amount  - Amount charged.
	 * @param DateTime $created - Time charge created.
	 * @param string   $status  - Payment intent status.
	 */
	public function __construct( $id, $amount, DateTime $created, $status ) {
		$this->id      = $id;
		$this->amount  = $amount;
		$this->created = $created;
		$this->status  = $status;
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
	 * Gets payment intent status
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}
}
