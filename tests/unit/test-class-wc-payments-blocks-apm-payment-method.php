<?php
/**
 * Class WC_Payments_Blocks_APM_Payment_Method_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the WC_Payments_Blocks_APM_Payment_Method class.
 */
class WC_Payments_Blocks_APM_Payment_Method_Test extends WCPAY_UnitTestCase {
	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * @var array Original payment gateway map.
	 */
	private $original_gateway_map;

	/**
	 * Pre-test setup.
	 */
	public function set_up() {
		parent::set_up();

		// Include the class file.
		require_once WCPAY_ABSPATH . 'includes/class-wc-payments-blocks-apm-payment-method.php';

		// Store original gateway map to restore later.
		$this->original_gateway_map = $this->get_payment_gateway_map();

		// Create a mock gateway.
		$this->mock_gateway     = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_gateway->id = 'woocommerce_payments_affirm';
	}

	/**
	 * Post-test cleanup.
	 */
	public function tear_down() {
		// Restore original gateway map.
		$this->set_payment_gateway_map( $this->original_gateway_map );

		parent::tear_down();
	}

	/**
	 * Test that initialize() sets the correct name from gateway.
	 */
	public function test_initialize_sets_name_from_gateway() {
		$this->set_payment_gateway_map( [ 'affirm' => $this->mock_gateway ] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$this->assertEquals( 'woocommerce_payments_affirm', $this->get_name_property( $apm_payment_method ) );
	}

	/**
	 * Test that initialize() sets fallback name when gateway not found.
	 */
	public function test_initialize_sets_fallback_name_when_gateway_not_found() {
		$this->set_payment_gateway_map( [] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$this->assertEquals( 'woocommerce_payments_affirm', $this->get_name_property( $apm_payment_method ) );
	}

	/**
	 * Helper to get the protected name property via reflection.
	 *
	 * @param WC_Payments_Blocks_APM_Payment_Method $apm_payment_method The payment method instance.
	 * @return string The name property value.
	 */
	private function get_name_property( $apm_payment_method ) {
		$reflection = new \ReflectionClass( $apm_payment_method );
		$property   = $reflection->getProperty( 'name' );
		$property->setAccessible( true );
		return $property->getValue( $apm_payment_method );
	}

	/**
	 * Test that is_active() returns true when gateway is available.
	 */
	public function test_is_active_returns_true_when_gateway_available() {
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'is_available' )
			->willReturn( true );

		$this->set_payment_gateway_map( [ 'affirm' => $this->mock_gateway ] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$this->assertTrue( $apm_payment_method->is_active() );
	}

	/**
	 * Test that is_active() returns false when gateway is not available.
	 */
	public function test_is_active_returns_false_when_gateway_not_available() {
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'is_available' )
			->willReturn( false );

		$this->set_payment_gateway_map( [ 'affirm' => $this->mock_gateway ] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$this->assertFalse( $apm_payment_method->is_active() );
	}

	/**
	 * Test that is_active() returns false when gateway not found.
	 */
	public function test_is_active_returns_false_when_gateway_not_found() {
		$this->set_payment_gateway_map( [] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$this->assertFalse( $apm_payment_method->is_active() );
	}

	/**
	 * Test that get_payment_method_script_handles() returns the main gateway script.
	 */
	public function test_get_payment_method_script_handles_returns_main_gateway_script() {
		$this->set_payment_gateway_map( [ 'affirm' => $this->mock_gateway ] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$script_handles = $apm_payment_method->get_payment_method_script_handles();

		$this->assertEquals( [ 'WCPAY_BLOCKS_CHECKOUT' ], $script_handles );
	}

	/**
	 * Test that get_payment_method_data() returns gateway options.
	 */
	public function test_get_payment_method_data_returns_gateway_options() {
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_option' )
			->willReturnMap(
				[
					[ 'title', '', 'Affirm' ],
					[ 'description', '', 'Pay with Affirm' ],
				]
			);

		$this->set_payment_gateway_map( [ 'affirm' => $this->mock_gateway ] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$data = $apm_payment_method->get_payment_method_data();

		$this->assertArrayHasKey( 'title', $data );
		$this->assertArrayHasKey( 'description', $data );
		$this->assertArrayHasKey( 'is_admin', $data );
		$this->assertEquals( 'Affirm', $data['title'] );
		$this->assertEquals( 'Pay with Affirm', $data['description'] );
	}

	/**
	 * Test that get_payment_method_data() returns empty array when gateway not found.
	 */
	public function test_get_payment_method_data_returns_empty_when_gateway_not_found() {
		$this->set_payment_gateway_map( [] );

		$apm_payment_method = new WC_Payments_Blocks_APM_Payment_Method( 'affirm' );
		$apm_payment_method->initialize();

		$data = $apm_payment_method->get_payment_method_data();

		$this->assertEquals( [], $data );
	}
}
