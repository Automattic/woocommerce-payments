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
class WC_Payments_In_Person_Payments_Receipt_Service {
	/**
	 * Doc get_print_receipt_template
	 *
	 * @param  array $settings Merchant settings.
	 * @param  array $order Order data.
	 * @param  array $charge Charge data.
	 *
	 * @return string
	 * @throws Exception Error generating the receipt.
	 */
	public static function get_receipt_html( $settings, $order, $charge ) {
		return wc_get_template(
			'html-in-person-payment-receipt.php',
			[
				'amount_captured'        => $charge['amount_captured'],
				'coupon_lines'           => $order['coupon_lines'] ?? [],
				'business_name'          => $settings['business_name'],
				'line_items'             => $order['line_items'] ?? [],
				'order'                  => $order,
				'payment_method_details' => $charge['payment_method_details'],
				'support_address'        => $settings['support_info']['address'],
				'support_email'          => $settings['support_info']['email'],
				'support_phone'          => $settings['support_info']['phone'],
				'tax_lines'              => $order['tax_lines'] ?? [],
			],
			'',
			__DIR__ . '/templates/'
		);
	}
}
