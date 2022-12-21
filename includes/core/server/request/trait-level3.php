<?php
/**
 * Trait file for WCPay\Core\Server\Request\Level3.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

/**
 * Tait for fixing intentions' level 3 data.
 */
trait Level3 {
	/**
	 * Fixes level 3 data data inconsistencies.
	 *
	 * @param  array $data The available level 3 data.
	 * @return array       The fixed level 3 data.
	 */
	private function fix_level3_data( array $data ) {
		// If level3 data doesn't contain any items, add a zero priced fee to meet Stripe's requirement.
		if (
			! isset( $data['line_items'] )
			|| ! is_array( $data['line_items'] )
			|| 0 === count( $data['line_items'] )
		) {
			$data['line_items'] = [
				[
					'discount_amount'     => 0,
					'product_code'        => 'empty-order',
					'product_description' => 'The order is empty',
					'quantity'            => 1,
					'tax_amount'          => 0,
					'unit_cost'           => 0,
				],
			];
		}

		return $data;
	}
}
