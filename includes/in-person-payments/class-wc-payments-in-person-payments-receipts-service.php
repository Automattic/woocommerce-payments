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
class WC_Payments_In_Person_Payments_Receipts_Service {

	/**
	 * Renders the receipt template.
	 *
	 * @param  array    $settings Merchant settings.
	 * @param  WC_Order $order Order instance.
	 * @param  array    $charge Charge data.
	 *
	 * @return string
	 */
	public function get_receipt_markup( array $settings, WC_Order $order, array $charge ): string {
		$this->validate_settings( $settings );
		$this->validate_charge( $charge );

		if ( $order instanceof WC_Payments_Printed_Receipt_Sample_Order ) {
			$order_data      = $order->get_data();
			$line_items_data = $order_data['line_items'];
		} else {
			$order_data      = [
				'id'           => $order->get_id(),
				'currency'     => $order->get_currency(),
				'subtotal'     => $order->get_subtotal(),
				'line_items'   => $order->get_items(),
				'coupon_lines' => $order->get_items( 'coupon' ),
				'tax_lines'    => $order->get_items( 'tax' ),
				'total'        => $order->get_total(),
				'shipping_tax' => $order->get_shipping_methods() ? $order->get_shipping_total() : 0,
				'total_fees'   => $order->get_total_fees(),
			];
			$line_items_data = $this->format_line_items( $order_data );
		}

		ob_start();

		wc_get_template(
			'html-in-person-payment-receipt.php',
			[
				'amount_captured'        => $charge['amount_captured'] / 100,
				'coupon_lines'           => $order_data['coupon_lines'] ?? [],
				'branding_logo'          => $settings['branding_logo'] ?? [],
				'business_name'          => $settings['business_name'],
				'line_items'             => $line_items_data,
				'order'                  => $order_data,
				'payment_method_details' => $charge['payment_method_details']['card_present'],
				'receipt'                => $charge['payment_method_details']['card_present']['receipt'],
				'support_address'        => $settings['support_info']['address'],
				'support_email'          => $settings['support_info']['email'],
				'support_phone'          => $settings['support_info']['phone'],
				'tax_lines'              => $order_data['tax_lines'] ?? [],
			],
			'',
			__DIR__ . '/templates/'
		);

		return ob_get_clean();
	}


	/**
	 * Send card reader receipt to customer by email
	 *
	 * @param  WC_Order $order the order.
	 * @param array    $merchant_settings The merchant settings.
	 * @param  array    $charge the charge.
	 * @return void
	 */
	public function send_customer_ipp_receipt_email( WC_Order $order, array $merchant_settings, array $charge ) {
		$email_receipt = WC()->mailer()->get_emails()['WC_Payments_Email_IPP_Receipt'];
		if ( $email_receipt instanceof WC_Payments_Email_IPP_Receipt ) {
			$email_receipt->trigger( $order, $merchant_settings, $charge );
		}
	}

	/**
	 * Format line items
	 *
	 * @param  array $order the order.
	 * @return array
	 */
	private function format_line_items( array $order ): array {
		$line_items_data = [];

		foreach ( $order['line_items'] as $item ) {
			$item_data            = $item->get_data();
			$item_data['product'] = $item->get_product()->get_data();
			$line_items_data[]    = $item_data;
		}

		return $line_items_data;
	}

	/**
	 * Validate settings
	 *
	 * @param  array $settings Settings.
	 * @return void
	 * @throws \RuntimeException Error validating settings.
	 */
	private function validate_settings( array $settings ) {
		if ( ! array_key_exists( 'business_name', $settings ) ) {
			throw new \RuntimeException( 'Business name needs to be provided.' );
		}

		if ( empty( $settings['support_info'] ) || ! is_array( $settings['support_info'] ) ) {
			throw new \RuntimeException( 'Support information needs to be provided.' );
		}

		$this->validate_required_fields(
			[ 'address', 'email', 'phone' ],
			$settings['support_info'],
			'Error validating support information'
		);
	}

	/**
	 * Validate charge information
	 *
	 * @param  array $charge Charge info.
	 * @return void
	 * @throws \RuntimeException Error validating charge info.
	 */
	private function validate_charge( array $charge ) {
		if ( ! array_key_exists( 'amount_captured', $charge ) ) {
			throw new \RuntimeException( 'Captured amount needs to be provided.' );
		}

		if ( empty( $charge['payment_method_details']['card_present'] ) || ! is_array( $charge['payment_method_details']['card_present'] ) ) {
			throw new \RuntimeException( 'Payment method details needs to be provided.' );
		}

		$this->validate_required_fields(
			[ 'brand', 'last4', 'receipt' ],
			$charge['payment_method_details']['card_present'],
			'Error validating payment information'
		);

		$this->validate_required_fields(
			[ 'application_preferred_name', 'dedicated_file_name', 'account_type' ],
			$charge['payment_method_details']['card_present']['receipt'],
			'Error validating receipt information'
		);
	}

	/**
	 * Validate required field
	 *
	 * @param  array  $required_fields Required fields.
	 * @param  array  $data Data to validate.
	 * @param  string $message Error message.
	 * @return void
	 * @throws \RuntimeException Error validating required fields.
	 */
	private function validate_required_fields( array $required_fields, array $data, string $message ) {
		foreach ( $required_fields as $required_key ) {
			if ( ! array_key_exists( $required_key, $data ) ) {
				throw new \RuntimeException( esc_html( sprintf( '%s. Missing key: %s', $message, $required_key ) ) );
			}
		}
	}
}
