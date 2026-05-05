<?php
/**
 * In-memory test double for WC Fulfillment objects.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Exposes the get_meta(), get_status(), and get_entity_id() methods that
 * WooPay_Fulfillments_API_Provider reads.
 */
class Fake_Fulfillment {
	/**
	 * Meta storage.
	 *
	 * @var array
	 */
	private $meta;

	/**
	 * Status string.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Constructor.
	 *
	 * @param array  $meta   Meta map.
	 * @param string $status Status string.
	 */
	public function __construct( array $meta = [], string $status = 'fulfilled' ) {
		$this->meta   = $meta;
		$this->status = $status;
	}

	/**
	 * Read a meta value.
	 *
	 * @param string $key Meta key.
	 * @return mixed
	 */
	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? '';
	}

	/**
	 * Get fulfillment status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Get associated entity (order) ID as a string.
	 *
	 * @return string
	 */
	public function get_entity_id(): string {
		return (string) ( $this->meta['_entity_id'] ?? '0' );
	}
}
