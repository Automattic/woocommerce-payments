<?php
/**
 * Class WC_Payments_In_Person_Payment_Print_Receipt_Service
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class handling in person payments receipts.
 */
class WC_Payments_In_Person_Payments_Receipt_Service {

	/**
	 * Renders the receipt template.
	 *
	 * @param  array $settings Merchant settings.
	 * @param  array $order Order data.
	 * @param  array $charge Charge data.
	 *
	 * @return void
	 */
	public static function render_receipt( array $settings, array $order, array $charge ) {
		wc_get_template(
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
