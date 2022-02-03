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
	 * @param  array $settings Merchant settings.
	 * @param  array $order_data Order instance.
	 * @param  array $charge Charge data.
	 *
	 * @return string
	 */
	public function get_receipt_markup( array $settings, array $order_data, array $charge ) :string {
		$this->validate_settings( $settings );
		$this->validate_charge( $charge );

		ob_start();

		wc_get_template(
			'html-in-person-payment-receipt.php',
			[
				'amount_captured'        => $charge['amount_captured'] / 100,
				'coupon_lines'           => $order_data['coupon_lines'] ?? [],
				'branding_logo'          => $settings['branding_logo'] ?? [],
				'business_name'          => $settings['business_name'],
				'line_items'             => $order_data['line_items'],
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
				throw new \RuntimeException( sprintf( '%s. Missing key: %s', $message, $required_key ) );
			}
		}
	}
}
