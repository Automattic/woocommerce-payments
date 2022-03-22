<?php
/**
 * Class WC_Payments_Printed_Receipt_Sample_Order
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;
/**
 * This class represents a sample order to be used when generating a preview of a printed receipt.
 *
 * @see WC_REST_Payments_Reader_Controller::preview_print_receipt
 */
class WC_Payments_Printed_Receipt_Sample_Order extends WC_Order {
	const PREVIEW_RECEIPT_ORDER_DATA = [
		'id'           => '42',
		'currency'     => 'USD',
		'subtotal'     => 0,
		'line_items'   => [
			[
				'name'     => 'Sample',
				'quantity' => 1,
				'subtotal' => 0,
				'product'  => [
					'price'         => 0,
					'regular_price' => 1,
					'id'            => 'sample',
				],
			],
			[
				'name'     => 'Sample',
				'quantity' => 1,
				'subtotal' => 0,
				'product'  => [
					'price'         => 0,
					'regular_price' => 1,
					'id'            => 'sample',
				],
			],
		],
		'coupon_lines' => [
			[
				'code'        => 'DISCOUNT',
				'description' => 'sample',
				'discount'    => 0,
			],
		],
		'tax_lines'    => [
			[
				'rate_percent' => 0,
				'tax_total'    => '0',
			],
		],
		'total'        => 0,
	];

	/**
	 * __construct
	 */
	public function __construct() {
		// noop.
	}

	/**
	 * Returns order data
	 *
	 * @return array
	 */
	public function get_data(): array {
		return self::PREVIEW_RECEIPT_ORDER_DATA;
	}

}
