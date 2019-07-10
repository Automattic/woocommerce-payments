<?php
/**
 * WC_Payments_API_Intention class
 *
 * @package WooCommerce\Payments
 */

/**
 * An intention object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Intention {
	/**
	 * Intention ID
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
	 * The status of the intention
	 *
	 * @var string
	 */
	private $status;

	/**
	 * The status of the intention
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * WC_Payments_API_Intention constructor.
	 *
	 * @param string   $id             - ID of the charge.
	 * @param integer  $amount         - Amount charged.
	 * @param DateTime $created        - Time charge created.
	 * @param string   $status         - Intention status.
	 * @param string   $client_secret  - Client secret.
	 */
	public function __construct( $id, $amount, DateTime $created, $status, $client_secret ) {
		$this->id            = $id;
		$this->amount        = $amount;
		$this->created       = $created;
		$this->status        = $status;
		$this->client_secret = $client_secret;
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
	 * Gets intention status
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Gets client secret
	 *
	 * @return string
	 */
	public function get_client_secret() {
		return $this->client_secret;
	}
}
