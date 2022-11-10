<?php
/**
 * Create charge request.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Enums\Endpoints;

/**
 * Create charge request.
 */
final class Create_Charge extends Base_Request {

	/**
	 * AMount to charge.
	 *
	 * @var int $amount
	 */
	protected $amount;

	/**
	 * ID of the source to associate with charge.
	 *
	 * @var string $source_id
	 */
	protected $source_id;

	/**
	 * Set amount.
	 *
	 * @param int $amount Amount to charge.
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function set_amount( $amount ) {
		$this->is_greater_than( $amount, 0 );
		$this->amount = $amount;
		return $this;
	}

	/**
	 * Set source id.
	 *
	 * @param string $source_id Source id.
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function set_source_id( $source_id ) {
		$this->is_string_and_not_empty( $source_id );
		$this->source_id = $source_id;
		return $this;
	}

	/**
	 * Get parameters.
	 *
	 * @return array
	 */
	public function get_parameters() {
		return [
			'amount' => $this->amount,
			'source' => $this->source_id,
		];
	}

	/**
	 * Get method.
	 *
	 * @return string
	 */
	public function get_method() {
		return \Requests::POST;
	}

	/**
	 * Get route.
	 *
	 * @return string
	 */
	public function get_route() {
		return Endpoints::INTENTIONS_API;
	}


}
