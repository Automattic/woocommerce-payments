<?php
/**
 * WC_Payments_API_Authorization class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A authorization object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Authorization implements \JsonSerializable {
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
	 * Status
	 *
	 * @var bool
	 */
	private $status;

	/**
	 * WC_Payments_API_Charge constructor.
	 *
	 * @param string $id        - ID.
	 * @param integer $amount   - Amount.
	 * @param string $status    - Status.
	 */
	public function __construct(
		string $id,
		int $amount,
		string $status
	) {
		$this->id       = $id;
		$this->amount   = $amount;
		$this->status   = $status;

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
	 * Gets amount
	 *
	 * @return int
	 */
	public function get_amount() {
		return $this->amount;
	}

	/**
	 * Gets status
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}



	/**
	 * Defines which data will be serialized to JSON
	 */
	public function jsonSerialize(): array {
		return [
			'id'                     => $this->get_id(),
			'amount'                 => $this->get_amount(),
			'status'                 => $this->get_status(),
		];
	}
}
