<?php
/**
 * Class Gateway_Orderer
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Gateway_Orderer
 */
class Gateway_Orderer {
	/**
	 * The original gateway ordering.
	 *
	 * @var array
	 */
	private $ordering;

	/**
	 * The main WooPayments gateway ID.
	 *
	 * @var string
	 */
	private $main_gateway_id;

	/**
	 * The current index.
	 *
	 * @var int
	 */
	private $current_index = 0;

	/**
	 * Collection of WooPayments gateway IDs.
	 *
	 * @var array
	 */
	private $woopayments_gateway_ids = [];

	/**
	 * The new ordering being built.
	 *
	 * @var array
	 */
	private $new_ordering = [];

	/**
	 * Constructor.
	 *
	 * @param array $ordering The original gateway ordering.
	 */
	public function __construct( $ordering, $woopayments_gateway_ids, $main_woopayments_gateway_id ) {
		$this->ordering                = (array) $ordering;
		$this->main_gateway_id         = $main_woopayments_gateway_id;
		$this->woopayments_gateway_ids = $woopayments_gateway_ids;
	}

	/**
	 * Process the ordering based on whether main gateway exists.
	 *
	 * @return array
	 */
	public function order_gateways() {
		$this->current_index = 0;

		// If gateway is not in the ordering, add all WooPayments gateways at the beginning
		if ( ! isset( $this->ordering[ $this->main_gateway_id ] ) || ! is_numeric( $this->ordering[ $this->main_gateway_id ] ) ) {
			$this->add_woopayments_gateways_at_beginning();
		} else {
			// Main gateway already has a position, use the step-by-step algorithm
			$this->add_gateways_before_woopayments()
				->add_woopayments_gateways()
				->add_gateways_after_woopayments();
		}

		return $this->get_ordering();
	}

	/**
	 * Add all WooPayments gateways at the beginning.
	 *
	 * @return $this
	 */
	private function add_woopayments_gateways_at_beginning() {
		$start_position = empty( $this->ordering ) ? 0 : ( min( $this->ordering ) - count( $this->woopayments_gateway_ids ) );
		$index          = 0;

		// Add all WooPayments gateways at the beginning
		foreach ( $this->woopayments_gateway_ids as $gateway_id ) {
			$this->new_ordering[ $gateway_id ] = $start_position + $index++;
		}

		// Add all other gateways after WooPayments gateways
		foreach ( $this->ordering as $gateway_id => $position ) {
			if ( ! in_array( $gateway_id, $this->woopayments_gateway_ids, true ) ) {
				$this->new_ordering[ $gateway_id ] = $position;
			}
		}

		return $this;
	}

	/**
	 * Add gateways that come before the main gateway.
	 *
	 * @return $this
	 */
	private function add_gateways_before_woopayments() {
		$index                 = 0;
		$main_gateway_position = $this->ordering[ $this->main_gateway_id ];

		foreach ( $this->ordering as $gateway_id => $position ) {
			if ( $position < $main_gateway_position && ! in_array( $gateway_id, $this->woopayments_gateway_ids, true ) ) {
				$this->new_ordering[ $gateway_id ] = $index++;
			}
		}

		$this->current_index = $index;
		return $this;
	}

	/**
	 * Add WooPayments gateways.
	 *
	 * @return $this
	 */
	private function add_woopayments_gateways() {
		$index = $this->current_index ?? 0;

		foreach ( $this->woopayments_gateway_ids as $gateway_id ) {
			$this->new_ordering[ $gateway_id ] = $index++;
		}

		$this->current_index = $index;
		return $this;
	}

	/**
	 * Add gateways that come after the main gateway.
	 *
	 * @return $this
	 */
	private function add_gateways_after_woopayments() {
		$main_gateway_position = $this->ordering[ $this->main_gateway_id ];
		$index                 = $this->current_index ?? 0;

		foreach ( $this->ordering as $gateway_id => $position ) {
			if ( $position > $main_gateway_position && ! in_array( $gateway_id, $this->woopayments_gateway_ids, true ) ) {
				$this->new_ordering[ $gateway_id ] = $index++;
			}
		}

		return $this;
	}

	/**
	 * Get the final ordering.
	 *
	 * @return array
	 */
	private function get_ordering() {
		return $this->new_ordering;
	}
}
