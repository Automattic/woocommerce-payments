<?php
/**
 * Class WC_Payments_Express_Checkout_Store_API_Extension_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the Store API cart extension that surfaces the ECE method
 * list filtered against the cart's currency.
 */
class WC_Payments_Express_Checkout_Store_API_Extension_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock helper.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper|MockObject
	 */
	private $mock_helper;

	/**
	 * Mock gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Express_Checkout_Store_API_Extension
	 */
	private $extension;

	public function set_up() {
		parent::set_up();
		$this->mock_helper  = $this->createMock( WC_Payments_Express_Checkout_Button_Helper::class );
		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->extension    = new WC_Payments_Express_Checkout_Store_API_Extension(
			$this->mock_helper,
			$this->mock_gateway
		);
	}

	public function test_extend_cart_data_includes_payment_request_when_enabled() {
		$this->mock_gateway->method( 'is_payment_request_enabled' )->willReturn( true );
		$this->mock_helper->method( 'can_use_amazon_pay' )->willReturn( false );

		$result = $this->extension->extend_cart_data();

		$this->assertSame( [ 'express_checkout_methods' => [ 'payment_request' ] ], $result );
	}

	public function test_extend_cart_data_includes_amazon_pay_when_can_use() {
		$this->mock_gateway->method( 'is_payment_request_enabled' )->willReturn( false );
		$this->mock_helper->method( 'can_use_amazon_pay' )->willReturn( true );

		$result = $this->extension->extend_cart_data();

		$this->assertSame( [ 'express_checkout_methods' => [ 'amazon_pay' ] ], $result );
	}

	public function test_extend_cart_data_includes_both_when_both_pass() {
		$this->mock_gateway->method( 'is_payment_request_enabled' )->willReturn( true );
		$this->mock_helper->method( 'can_use_amazon_pay' )->willReturn( true );

		$result = $this->extension->extend_cart_data();

		$this->assertSame(
			[ 'express_checkout_methods' => [ 'payment_request', 'amazon_pay' ] ],
			$result
		);
	}

	public function test_extend_cart_data_returns_empty_list_when_neither_passes() {
		$this->mock_gateway->method( 'is_payment_request_enabled' )->willReturn( false );
		$this->mock_helper->method( 'can_use_amazon_pay' )->willReturn( false );

		$result = $this->extension->extend_cart_data();

		$this->assertSame( [ 'express_checkout_methods' => [] ], $result );
	}

	public function test_extend_cart_schema_describes_the_methods_field() {
		$schema = $this->extension->extend_cart_schema();

		$this->assertArrayHasKey( 'express_checkout_methods', $schema );
		$this->assertSame( 'array', $schema['express_checkout_methods']['type'] );
		$this->assertSame( 'string', $schema['express_checkout_methods']['items']['type'] );
		$this->assertTrue( $schema['express_checkout_methods']['readonly'] );
	}
}
