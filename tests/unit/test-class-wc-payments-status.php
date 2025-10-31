<?php
/**
 * Class WC_Payments_Status_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Status unit tests.
 */
class WC_Payments_Status_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Status
	 */
	private $status;

	/**
	 * Mock gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_gateway;

	/**
	 * Mock HTTP client.
	 *
	 * @var WC_Payments_Http_Interface|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_http;

	/**
	 * Mock account service.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_account;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_http    = $this->createMock( WC_Payments_Http_Interface::class );
		$this->mock_account = $this->createMock( WC_Payments_Account::class );

		$this->status = new WC_Payments_Status(
			$this->mock_gateway,
			$this->mock_http,
			$this->mock_account
		);
	}

	/**
	 * Test that debug_tools filter adds both tools.
	 */
	public function test_debug_tools_adds_wcpay_tools() {
		$tools = $this->status->debug_tools( [] );

		$this->assertArrayHasKey( 'clear_wcpay_account_cache', $tools );
		$this->assertArrayHasKey( 'delete_wcpay_test_orders', $tools );
	}

	/**
	 * Test that the clear cache tool has correct structure.
	 */
	public function test_clear_cache_tool_structure() {
		$tools = $this->status->debug_tools( [] );

		$clear_cache_tool = $tools['clear_wcpay_account_cache'];

		$this->assertArrayHasKey( 'name', $clear_cache_tool );
		$this->assertArrayHasKey( 'button', $clear_cache_tool );
		$this->assertArrayHasKey( 'desc', $clear_cache_tool );
		$this->assertArrayHasKey( 'callback', $clear_cache_tool );

		$this->assertStringContainsString( 'Clear', $clear_cache_tool['name'] );
		$this->assertEquals( 'Clear', $clear_cache_tool['button'] );
	}

	/**
	 * Test that the delete test orders tool has correct structure.
	 */
	public function test_delete_test_orders_tool_structure() {
		$tools = $this->status->debug_tools( [] );

		$delete_tool = $tools['delete_wcpay_test_orders'];

		$this->assertArrayHasKey( 'name', $delete_tool );
		$this->assertArrayHasKey( 'button', $delete_tool );
		$this->assertArrayHasKey( 'desc', $delete_tool );
		$this->assertArrayHasKey( 'callback', $delete_tool );

		$this->assertEquals( 'Delete WooPayments test orders', $delete_tool['name'] );
		$this->assertEquals( 'Delete', $delete_tool['button'] );
		$this->assertStringContainsString( 'Note:', $delete_tool['desc'] );
		$this->assertStringContainsString( 'strong class="red"', $delete_tool['desc'] );
		$this->assertStringContainsString( 'deletes all test mode orders placed via', $delete_tool['desc'] );
		$this->assertStringContainsString( 'Orders placed via other gateways will not be affected', $delete_tool['desc'] );
		$this->assertStringContainsString( 'cannot be undone', $delete_tool['desc'] );
	}

	/**
	 * Test delete_test_orders when no test orders exist.
	 */
	public function test_delete_test_orders_with_no_orders() {
		// Mock wc_get_orders to return empty array.
		add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			function ( $query, $query_vars ) {
				if ( isset( $query_vars['meta_key'] ) && '_wcpay_mode' === $query_vars['meta_key'] ) {
					$query['post__in'] = [ 0 ]; // Force no results.
				}
				return $query;
			},
			10,
			2
		);

		$result = $this->status->delete_test_orders();

		$this->assertEquals( 'No test orders found.', $result );
	}

	/**
	 * Test delete_test_orders successfully deletes test orders.
	 */
	public function test_delete_test_orders_deletes_orders() {
		// Create test orders with _wcpay_mode meta.
		$order1 = wc_create_order();
		$order1->update_meta_data( '_wcpay_mode', 'test' );
		$order1->save();

		$order2 = wc_create_order();
		$order2->update_meta_data( '_wcpay_mode', 'test' );
		$order2->save();

		// Create a non-test order that should not be deleted.
		$order3 = wc_create_order();
		$order3->update_meta_data( '_wcpay_mode', 'live' );
		$order3->save();

		$result = $this->status->delete_test_orders();

		// Verify result message.
		$this->assertStringContainsString( '2 test orders have been permanently deleted.', $result );

		// Verify test orders were moved to trash.
		$trashed_order1 = wc_get_order( $order1->get_id() );
		$this->assertInstanceOf( WC_Order::class, $trashed_order1 );
		$this->assertEquals( 'trash', $trashed_order1->get_status() );

		$trashed_order2 = wc_get_order( $order2->get_id() );
		$this->assertInstanceOf( WC_Order::class, $trashed_order2 );
		$this->assertEquals( 'trash', $trashed_order2->get_status() );

		// Verify non-test order was not deleted.
		$order3_check = wc_get_order( $order3->get_id() );
		$this->assertInstanceOf( WC_Order::class, $order3_check );
		$this->assertNotEquals( 'trash', $order3_check->get_status() );
	}

	/**
	 * Test delete_test_orders with single order uses singular message.
	 */
	public function test_delete_test_orders_singular_message() {
		// Create single test order.
		$order = wc_create_order();
		$order->update_meta_data( '_wcpay_mode', 'test' );
		$order->save();

		$result = $this->status->delete_test_orders();

		$this->assertStringContainsString( '1 test order has been permanently deleted.', $result );

		// Verify order was moved to trash.
		$trashed_order = wc_get_order( $order->get_id() );
		$this->assertInstanceOf( WC_Order::class, $trashed_order );
		$this->assertEquals( 'trash', $trashed_order->get_status() );
	}

	/**
	 * Test delete_test_orders handles exceptions.
	 */
	public function test_delete_test_orders_handles_exception() {
		// Mock wc_get_orders to throw an exception.
		add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			function ( $query, $query_vars ) {
				throw new Exception( 'Database error' );
			},
			10,
			2
		);

		$result = $this->status->delete_test_orders();

		$this->assertStringContainsString( 'Error deleting test orders:', $result );
		$this->assertStringContainsString( 'Database error', $result );
	}
}
