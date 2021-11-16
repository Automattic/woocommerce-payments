<?php
/**
 * Doc
 *
 * @package Package
 */

/**
 * Class WC_Payments_In_Person_Payment_Print_Receipt_Service
 *
 * @package WooCommerce\Payments
 */
class WC_Payments_In_Person_Payment_Receipt_Service {
	/**
	 * Doc get_print_receipt_template
	 *
	 * @param  array $receipt_data Data representing the receipt.
	 *
	 * @return string
	 */
	public static function get_receipt_html( $receipt_data ) {
		$business_name   = $receipt_data['store']['business_name'];
		$support_address = $receipt_data['store']['support_info']['address'];
		$support_phone   = $receipt_data['store']['support_info']['phone'];
		$support_email   = $receipt_data['store']['support_info']['email'];
		$order           = $receipt_data['order'];
		$charge          = $receipt_data['charge'];

		return wc_get_template(
			'html-in-person-payment-receipt.php',
			[
				'business_name'   => $business_name,
				'charge'          => $charge,
				'order'           => $order,
				'support_address' => $support_address,
				'support_email'   => $support_email,
				'support_phone'   => $support_phone,
			],
			'',
			__DIR__ . '/templates/'
		);
	}
}
