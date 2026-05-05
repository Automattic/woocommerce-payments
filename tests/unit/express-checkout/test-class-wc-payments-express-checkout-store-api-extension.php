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
	 * Mock helper used to control the canonical ECE filter result.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper|MockObject
	 */
	private $mock_helper;

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Express_Checkout_Store_API_Extension
	 */
	private $extension;

	public function set_up() {
		parent::set_up();
		$this->mock_helper = $this->createMock( WC_Payments_Express_Checkout_Button_Helper::class );
		$this->extension   = new WC_Payments_Express_Checkout_Store_API_Extension( $this->mock_helper );
	}

	public function test_extend_cart_data_delegates_to_helper() {
		$this->mock_helper
			->expects( $this->once() )
			->method( 'get_enabled_express_checkout_methods_for_context' )
			->willReturn( [ 'payment_request', 'amazon_pay' ] );

		$result = $this->extension->extend_cart_data();

		$this->assertSame(
			[ 'express_checkout_methods' => [ 'payment_request', 'amazon_pay' ] ],
			$result
		);
	}

	public function test_extend_cart_data_returns_empty_list_when_helper_returns_no_methods() {
		$this->mock_helper
			->method( 'get_enabled_express_checkout_methods_for_context' )
			->willReturn( [] );

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
