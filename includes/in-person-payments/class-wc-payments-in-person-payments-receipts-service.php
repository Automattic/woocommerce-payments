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
	public function get_receipt_markup( array $settings, WC_Order $order, array $charge ) :string {
		$this->validate_settings( $settings );
		$this->validate_charge( $charge );

		$order_data = [
			'id'           => $order->get_id(),
			'currency'     => $order->get_currency(),
			'subtotal'     => $order->get_subtotal(),
			'line_items'   => $order->get_items(),
			'coupon_lines' => $order->get_items( 'coupon' ),
			'tax_lines'    => $order->get_items( 'tax' ),
			'total'        => $order->get_total(),
		];

		ob_start();

		wc_get_template(
			'html-in-person-payment-receipt.php',
			[
				'amount_captured'        => $charge['amount_captured'] / 100,
				'coupon_lines'           => $order_data['coupon_lines'] ?? [],
				'business_name'          => $settings['business_name'],
				'line_items'             => $this->format_line_items( $order_data ),
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
	 * Format line items
	 *
	 * @param  array $order the order.
	 * @return array
	 */
	private function format_line_items( array $order ) :array {
		$line_items_data = [];

		foreach ( $order['line_items'] as $item ) {
			$item_data            = $item->get_data();
			$item_data['product'] = $item->get_product()->get_data();
			$line_items_data[]    = $item_data;
		};

		return $line_items_data;
	}

	/**
	 * Validate settings
	 *
	 * @param  array $settings Settings.
	 * @return void
	 * @throws \Exception Error validating settings.
	 */
	private function validate_settings( $settings ) {
		if ( ! $settings ) {
			throw new \Exception( 'You must provide settings information' );
		}

		if ( ! array_key_exists( 'business_name', $settings ) ) {
			throw new \Exception( 'You must provide a business name' );
		}

		$this->validate_support_info( $settings['support_info'] );
	}

	/**
	 * Validate support info
	 *
	 * @param  array $support_info Support info.
	 * @return void
	 * @throws \Exception Error validating support info.
	 */
	private function validate_support_info( $support_info ) {
		if ( ! $support_info ) {
			throw new Exception( 'You must provide support information.' );
		}

		foreach ( $support_info as $key => $value ) {
			if ( ! in_array( $key, [ 'address', 'email', 'phone' ], true ) ) {
				throw new Exception( 'Invalid support information.' );
			}
		}
	}

	/**
	 * Validate charge information
	 *
	 * @param  mixed $charge Charge info.
	 * @return void
	 * @throws \Exception Error validating charge info.
	 */
	private function validate_charge( $charge ) {
		if ( ! $charge ) {
			throw new Exception( 'You must provide charge information' );
		}

		if ( ! array_key_exists( 'amount_captured', $charge ) ) {
			throw new Exception( 'You must provide a captured amount' );
		}

		if ( ! array_key_exists( 'payment_method_details', $charge ) ) {
			throw new Exception( 'You must provide payment method details' );
		}

		if ( ! array_key_exists( 'card_present', $charge['payment_method_details'] ) ) {
			throw new Exception( 'You must provide card present details' );
		}

		if ( ! array_key_exists( 'receipt', $charge['payment_method_details']['card_present'] ) ) {
			throw new Exception( 'You must provide card present details' );
		}

	}
}
