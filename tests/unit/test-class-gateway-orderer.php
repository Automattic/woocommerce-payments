<?php
/**
 * Class WC_Pay_Gateway_Orderer_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Gateway_Orderer;

/**
 * WC_Pay_Gateway_Orderer unit tests.
 */
class Gateway_Orderer_Test extends WCPAY_UnitTestCase {

	/**
	 * Test ordering when main gateway is not in the ordering.
	 */
	public function test_order_gateways_when_main_gateway_not_in_ordering() {
		$ordering = [
			'gateway1' => 10,
			'gateway2' => 20,
		];

		$woopayments_gateway_ids = [ 'woocommerce_payments', 'woocommerce_payments_ideal' ];
		$main_gateway_id         = 'woocommerce_payments';

		$orderer = new Gateway_Orderer( $ordering, $woopayments_gateway_ids, $main_gateway_id );
		$result  = $orderer->order_gateways();

		// WooPayments gateways should be at the beginning
		$this->assertEquals( 8, $result['woocommerce_payments'] );
		$this->assertEquals( 9, $result['woocommerce_payments_ideal'] );

		// Other gateways should maintain their positions
		$this->assertEquals( 10, $result['gateway1'] );
		$this->assertEquals( 20, $result['gateway2'] );
	}

	/**
	 * Test ordering when main gateway is in the ordering.
	 */
	public function test_order_gateways_when_main_gateway_in_ordering() {
		$ordering = [
			'gateway1'             => 10,
			'woocommerce_payments' => 20,
			'gateway2'             => 30,
		];

		$woopayments_gateway_ids = [ 'woocommerce_payments', 'woocommerce_payments_ideal' ];
		$main_gateway_id         = 'woocommerce_payments';

		$orderer = new Gateway_Orderer( $ordering, $woopayments_gateway_ids, $main_gateway_id );
		$result  = $orderer->order_gateways();

		// Gateways before WooPayments should maintain their order
		$this->assertEquals( 0, $result['gateway1'] );

		// WooPayments gateways should be grouped together
		$this->assertEquals( 1, $result['woocommerce_payments'] );
		$this->assertEquals( 2, $result['woocommerce_payments_ideal'] );

		// Gateways after WooPayments should maintain their order
		$this->assertEquals( 3, $result['gateway2'] );
	}

	/**
	 * Test ordering with empty ordering array.
	 */
	public function test_order_gateways_with_empty_ordering() {
		$ordering = [];

		$woopayments_gateway_ids = [ 'woocommerce_payments', 'woocommerce_payments_ideal' ];
		$main_gateway_id         = 'woocommerce_payments';

		$orderer = new Gateway_Orderer( $ordering, $woopayments_gateway_ids, $main_gateway_id );
		$result  = $orderer->order_gateways();

		// WooPayments gateways should be at position 0 and 1
		$this->assertEquals( 0, $result['woocommerce_payments'] );
		$this->assertEquals( 1, $result['woocommerce_payments_ideal'] );
	}

	/**
	 * Test ordering with non-numeric position for main gateway.
	 */
	public function test_order_gateways_with_non_numeric_position() {
		$ordering = [
			'gateway1'             => 10,
			'woocommerce_payments' => 'top',
			'gateway2'             => 30,
		];

		$woopayments_gateway_ids = [ 'woocommerce_payments', 'woocommerce_payments_ideal' ];
		$main_gateway_id         = 'woocommerce_payments';

		$orderer = new Gateway_Orderer( $ordering, $woopayments_gateway_ids, $main_gateway_id );
		$result  = $orderer->order_gateways();

		// WooPayments gateways should be at the beginning
		$this->assertEquals( 8, $result['woocommerce_payments'] );
		$this->assertEquals( 9, $result['woocommerce_payments_ideal'] );

		// Other gateways should maintain their positions
		$this->assertEquals( 10, $result['gateway1'] );
		$this->assertEquals( 30, $result['gateway2'] );
	}
}
