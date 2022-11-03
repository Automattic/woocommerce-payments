<?php
/**
 * Create charge request.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\ValueObjects\API\Request;

use WCPay\Core\Enums\Http_Methods;
use WCPay\Core\Enums\Wc_Pay_Endpoints;

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
	 * Get amount.
	 *
	 * @return int
	 */
	public function get_amount() {
		return $this->amount;
	}

	/**
	 * Get source id
	 *
	 * @return string
	 */
	public function get_source_id() {
		return $this->source_id;
	}

	/**
	 * Set amount.
	 *
	 * @param int $amount Amount to charge.
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function set_amount( $amount ) {
		if ( ! is_numeric( $amount ) ) {
			throw new \InvalidArgumentException( 'Amount is not numeric value' );
		}
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
		if ( is_string( $source_id ) && $source_id ) {
			$this->source_id = $source_id;
			return $this;
		}
		throw new \InvalidArgumentException( 'Source ID is invalid! Source needs to be string type and not empty' );
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
		return Http_Methods::POST;
	}

	/**
	 * Get route.
	 *
	 * @return string
	 */
	public function get_route() {
		return Wc_Pay_Endpoints::INTENTIONS_API;
	}


}
