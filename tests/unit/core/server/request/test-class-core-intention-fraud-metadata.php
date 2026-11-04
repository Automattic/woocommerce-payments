<?php
/**
 * Intention fraud metadata regression tests.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Setup_Intention;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Update_Intention;

/**
 * Tests fraud item counts for order payments independently of the session cart.
 */
class Intention_Fraud_Metadata_Test extends WCPAY_UnitTestCase {
	/**
	 * @dataProvider order_payment_provider
	 */
	public function test_fingerprint_uses_order_quantity( $request_class, $order_quantity, $cart_quantity ) {
		$original_cart = WC()->cart;
		$order         = WC_Helper_Order::create_order( 0 );
		try {
			$order->remove_order_items();
			$item = new WC_Order_Item_Product();
			$item->set_quantity( $order_quantity );
			$order->add_item( $item );
			$order->save();

			$cart = $this->createMock( WC_Cart::class );
			$cart->method( 'get_cart_contents_count' )->willReturn( $cart_quantity );
			WC()->cart = $cart;

			$request = new $request_class(
				$this->createMock( WC_Payments_API_Client::class ),
				$this->createMock( WC_Payments_Http_Interface::class ),
				'pi_test'
			);
			$request->set_metadata( [ 'order_id' => $order->get_id() ] );
			$request->set_fingerprint( 'test-fingerprint', $order );
			$metadata = $request->get_param( 'metadata' );

			$this->assertSame( $order_quantity, $metadata['fraud_prevention_data_cart_contents'] );
			$this->assertSame( 'test-fingerprint', $metadata['fraud_prevention_data_shopper_ua_hash'] );
			$this->assertTrue( $metadata['fraud_prevention_data_available'] );
		} finally {
			WC()->cart = $original_cart;
			$order->delete( true );
		}
	}

	public function order_payment_provider() {
		$cases = [];
		foreach ( [ Create_Intention::class, Create_And_Confirm_Intention::class, Update_Intention::class, Create_And_Confirm_Setup_Intention::class ] as $request_class ) {
			foreach ( [ [ 25, 1 ], [ 25, 0 ], [ 1, 25 ], [ 0, 25 ] ] as $quantities ) {
				$cases[ $request_class . ':' . implode( '-', $quantities ) ] = array_merge( [ $request_class ], $quantities );
			}
		}
		return $cases;
	}

	public function test_update_metadata_uses_order_quantity() {
		$order = WC_Helper_Order::create_order( 0 );
		try {
			$request = new Update_Intention(
				$this->createMock( WC_Payments_API_Client::class ),
				$this->createMock( WC_Payments_Http_Interface::class ),
				'pi_test'
			);
			$request->set_metadata( [ 'order_id' => $order->get_id() ], $order );
			$metadata = $request->get_param( 'metadata' );
			$this->assertSame( 4, $metadata['fraud_prevention_data_cart_contents'] );
		} finally {
			$order->delete( true );
		}
	}

	public function test_fingerprint_without_order_omits_item_count() {
		$original_cart = WC()->cart;
		try {
			$cart = $this->createMock( WC_Cart::class );
			$cart->expects( $this->never() )->method( 'get_cart_contents_count' );
			WC()->cart = $cart;

			$request = new Create_Intention(
				$this->createMock( WC_Payments_API_Client::class ),
				$this->createMock( WC_Payments_Http_Interface::class )
			);
			$request->set_metadata( [ 'order_id' => 123 ] );
			$request->set_fingerprint( 'test-fingerprint' );
			$metadata = $request->get_param( 'metadata' );

			$this->assertArrayNotHasKey( 'fraud_prevention_data_cart_contents', $metadata );
			$this->assertTrue( $metadata['fraud_prevention_data_available'] );
		} finally {
			WC()->cart = $original_cart;
		}
	}
}
