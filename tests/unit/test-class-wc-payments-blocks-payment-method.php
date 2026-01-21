<?php
/**
 * Class WC_Payments_Blocks_Payment_Method_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Blocks_Payment_Method unit tests.
 */
class WC_Payments_Blocks_Payment_Method_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Blocks_Payment_Method
	 */
	private $blocks_payment_method;

	/**
	 * Mock WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * Backup of the original gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $original_gateway;

	/**
	 * Pre-test setup.
	 */
	public function set_up() {
		parent::set_up();

		// Create a mock gateway.
		$this->mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->setMethods( [ 'is_enabled', 'is_available' ] )
			->getMock();

		$this->mock_gateway->id = 'woocommerce_payments';

		// Backup original gateway and set our mock.
		$this->original_gateway = WC_Payments::get_gateway();
		WC_Payments::set_gateway( $this->mock_gateway );

		// Create the blocks payment method instance.
		// Note: gateway and name are set in constructor, initialize() only sets wc_payments_checkout.
		$this->blocks_payment_method = new WC_Payments_Blocks_Payment_Method();
		$this->blocks_payment_method->initialize();
	}

	/**
	 * Post-test teardown.
	 */
	public function tear_down() {
		// Restore original gateway.
		WC_Payments::set_gateway( $this->original_gateway );

		parent::tear_down();
	}

	/**
	 * Tests that is_active() returns true when gateway is enabled,
	 * regardless of is_available() status.
	 */
	public function test_is_active_returns_true_when_gateway_is_enabled() {
		$this->mock_gateway->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		// is_available() should NOT be called - this is the key behavior change.
		$this->mock_gateway->expects( $this->never() )
			->method( 'is_available' );

		$this->assertTrue( $this->blocks_payment_method->is_active() );
	}

	/**
	 * Tests that is_active() returns false when gateway is disabled.
	 */
	public function test_is_active_returns_false_when_gateway_is_disabled() {
		$this->mock_gateway->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( false );

		// is_available() should NOT be called.
		$this->mock_gateway->expects( $this->never() )
			->method( 'is_available' );

		$this->assertFalse( $this->blocks_payment_method->is_active() );
	}

	/**
	 * Tests that is_active() returns true even when is_available() would return false.
	 *
	 * This is the critical test case - in the block editor context, is_available()
	 * may return false due to runtime checks (HTTPS, currency, account status),
	 * but is_active() should still return true if the gateway is enabled.
	 */
	public function test_is_active_returns_true_when_enabled_but_not_available() {
		$this->mock_gateway->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		// Configure is_available() to return false (simulating editor context).
		// This should NOT be called, but if it were, it would return false.
		$this->mock_gateway->expects( $this->never() )
			->method( 'is_available' );

		$this->assertTrue( $this->blocks_payment_method->is_active() );
	}

	/**
	 * Tests that a gateway can be passed directly to the constructor.
	 *
	 * This is used for split gateways (Affirm, Apple Pay, etc.) which need
	 * to be registered with the blocks system separately.
	 */
	public function test_constructor_accepts_gateway_parameter() {
		// Create a new mock gateway for a split gateway (e.g., Affirm).
		$split_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->setMethods( [ 'is_enabled', 'is_available' ] )
			->getMock();

		$split_gateway->id = 'woocommerce_payments_affirm';

		$split_gateway->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		// Create blocks payment method with the split gateway.
		$split_blocks_method = new WC_Payments_Blocks_Payment_Method( $split_gateway );
		$split_blocks_method->initialize();

		// Verify is_active() uses the passed gateway.
		$this->assertTrue( $split_blocks_method->is_active() );
	}
}
