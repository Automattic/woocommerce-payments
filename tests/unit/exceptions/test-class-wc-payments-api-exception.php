<?php
/**
 * Class WC_Payments_API_Exception_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

/**
 * Test cases for API_Exception
 */
class WC_Payments_API_Exception_Test extends WP_UnitTestCase {

	public function test_get_user_message() {
		$e_without_data = new WCPay\Exceptions\API_Exception( 'Test', 'wcpay_amount_too_low', 400, [] );
		$this->assertEquals(
			__(
				'The specified amount is less than the minimum amount allowed. Use a higher amount and try again.',
				'woocommerce-payments'
			),
			$e_without_data->get_user_message()
		);

		$error_data = [
			'currency'   => 'usd',
			'min_amount' => 60,
		];

		$e_with_data = new WCPay\Exceptions\API_Exception( 'Test', 'wcpay_amount_too_low', 400, $error_data );
		$this->assertEquals(
			sprintf(
				// translators: %1$s is a formatted amount with currency code.
				__(
					'Sorry, the minimum allowed order total is %1$s to use this payment method.',
					'woocommerce-payments'
				),
				wc_price( $error_data['min_amount'] / 100, [ 'currency' => strtoupper( $error_data['currency'] ) ] )
			),
			$e_with_data->get_user_message()
		);

		$e_with_data_wrong_code = new WCPay\Exceptions\API_Exception( 'Test', 'foobar', 400, [] );
		$this->assertEquals(
			'Test',
			$e_with_data_wrong_code->get_user_message()
		);
	}
}
