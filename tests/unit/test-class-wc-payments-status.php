<?php
/**
 * Class WC_Payments_Status_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Mode;

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

		// Set up an admin user with proper capabilities.
		wp_set_current_user( 1 );

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
	 * Post-test teardown.
	 */
	public function tear_down(): void {
		WC_Payments::mode()->live();
		parent::tear_down();
	}

	/**
	 * Test that debug_tools filter adds all tools.
	 */
	public function test_debug_tools_adds_wcpay_tools() {
		$tools = $this->status->debug_tools( [] );

		$this->assertArrayHasKey( 'clear_wcpay_account_cache', $tools );
		$this->assertArrayHasKey( 'delete_wcpay_test_orders', $tools );
		$this->assertArrayHasKey( 'clear_wcpay_styles_cache', $tools );
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
		$this->assertStringContainsString( 'deletes test mode orders with a successful or authorized', $delete_tool['desc'] );
		$this->assertStringContainsString( 'Orders with incomplete payment details or placed via other gateways will not be affected', $delete_tool['desc'] );
		$this->assertStringContainsString( 'cannot be undone', $delete_tool['desc'] );
	}

	/**
	 * Test that the clear styles cache tool has correct structure.
	 */
	public function test_clear_styles_cache_tool_structure() {
		$tools = $this->status->debug_tools( [] );

		$clear_styles_tool = $tools['clear_wcpay_styles_cache'];

		$this->assertEquals( 'Clear WooPayments calculated styles', $clear_styles_tool['name'] );
		$this->assertEquals( 'Clear', $clear_styles_tool['button'] );
		$this->assertEquals( 'This tool will clear the styles cached for the WooPayments gateway UI elements at checkout', $clear_styles_tool['desc'] );
		$this->assertIsCallable( $clear_styles_tool['callback'] );
	}

	/**
	 * Test that clear_styles_cache deletes the option and returns expected message.
	 */
	public function test_clear_styles_cache_clears_option_and_returns_message() {
		update_option( 'wcpay_styles_cache_version', 'test_version' );

		$result = $this->status->clear_styles_cache();

		$this->assertEquals( 'WooPayments styles cleared', $result );
		$this->assertFalse( get_option( 'wcpay_styles_cache_version' ) );
	}

	/**
	 * Test delete_test_orders when no test orders exist.
	 */
	public function test_delete_test_orders_with_no_orders() {
		// Mock wc_get_orders to return empty array.
		$filter_callback = function ( $query, $query_vars ) {
			if ( isset( $query_vars['meta_key'] ) && '_wcpay_mode' === $query_vars['meta_key'] ) {
				$query['post__in'] = [ 0 ]; // Force no results.
			}
			return $query;
		};
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $filter_callback, 10, 2 );

		$result = $this->status->delete_test_orders();

		$this->assertEquals( 'No test orders found.', $result );

		// Clean up filter.
		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $filter_callback, 10 );
	}

	/**
	 * Test delete_test_orders successfully deletes test orders.
	 */
	public function test_delete_test_orders_deletes_orders() {
		// Create test orders with _wcpay_mode meta.
		$order1 = wc_create_order();
		$order1->update_meta_data( '_wcpay_mode', 'test' );
		$order1->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order1->set_transaction_id( 'pi_test' );
		$order1->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, 'pi_test' );
		$order1->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, Intent_Status::SUCCEEDED );
		$order1->save();

		$order2 = wc_create_order();
		$order2->update_meta_data( '_wcpay_mode', 'test' );
		$order2->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order2->set_transaction_id( 'pi_test' );
		$order2->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, 'pi_test' );
		$order2->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, Intent_Status::SUCCEEDED );
		$order2->save();

		// Create a non-test order that should not be deleted.
		$order3 = wc_create_order();
		$order3->update_meta_data( '_wcpay_mode', Order_Mode::PRODUCTION );
		$order3->save();

		$result = $this->status->delete_test_orders();

		// Verify result message.
		$this->assertStringContainsString( '2 test orders have been permanently deleted.', $result );

		// Verify test orders were permanently deleted (no longer exist).
		$deleted_order1 = wc_get_order( $order1->get_id() );
		$this->assertFalse( $deleted_order1, 'Test order 1 should be permanently deleted' );

		$deleted_order2 = wc_get_order( $order2->get_id() );
		$this->assertFalse( $deleted_order2, 'Test order 2 should be permanently deleted' );

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
		$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->set_transaction_id( 'pi_test' );
		$order->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, 'pi_test' );
		$order->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, Intent_Status::SUCCEEDED );
		$order->save();

		$result = $this->status->delete_test_orders();

		$this->assertStringContainsString( '1 test order has been permanently deleted.', $result );

		// Verify order was permanently deleted (no longer exists).
		$deleted_order = wc_get_order( $order->get_id() );
		$this->assertFalse( $deleted_order, 'Test order should be permanently deleted' );
	}

	/**
	 * Keeps historical attempts and other gateways away from permanent deletion.
	 *
	 * @dataProvider provide_test_order_deletion_cases
	 * @param string $gateway Payment gateway saved on the order.
	 * @param string $mode Stored WooPayments mode.
	 * @param string $intent_status Status of the WooPayments attempt.
	 * @param string $intent_id WooPayments intent ID.
	 * @param string $transaction_id Current order transaction.
	 * @param string $order_status WooCommerce order status.
	 * @param bool   $deleted Whether the order can safely be deleted.
	 * @param bool   $hpos Whether to use HPOS.
	 */
	public function test_delete_test_orders_requires_current_accepted_payment( $gateway, $mode, $intent_status, $intent_id, $transaction_id, $order_status, $deleted, $hpos ) {
		$storage_filter = static function () use ( $hpos ) {
			return $hpos ? 'yes' : 'no';
		};
		add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage_filter );
		add_filter( 'pre_option_woocommerce_feature_custom_order_tables_enabled', $storage_filter );

		try {
			$this->assertSame( $hpos, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
			$order = wc_create_order();
			$order->set_payment_method( $gateway );
			$order->set_status( $order_status );
			$order->set_transaction_id( $transaction_id );
			$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, $mode );
			$order->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, $intent_id );
			$order->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, $intent_status );
			$order->save();
			$order_id = $order->get_id();

			$result = $this->status->delete_test_orders();

			if ( $deleted ) {
				$this->assertFalse( wc_get_order( $order_id ) );
				$this->assertSame( '1 test order has been permanently deleted.', $result );
			} else {
				$preserved = wc_get_order( $order_id );
				$this->assertInstanceOf( WC_Order::class, $preserved );
				$this->assertSame( $order_status, $preserved->get_status() );
				$this->assertSame( $transaction_id, $preserved->get_transaction_id() );
				$this->assertSame( $mode, $preserved->get_meta( '_wcpay_mode' ) );
				$this->assertSame( 'No test orders found.', $result );
			}
		} finally {
			if ( isset( $order_id ) && wc_get_order( $order_id ) ) {
				wc_get_order( $order_id )->delete( true );
			}
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage_filter );
			remove_filter( 'pre_option_woocommerce_feature_custom_order_tables_enabled', $storage_filter );
		}
	}

	/**
	 * Historical orders can have a stale gateway as well as stale mode metadata.
	 *
	 * @return iterable
	 */
	public function provide_test_order_deletion_cases() {
		$gateway = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$test    = Order_Mode::TEST;
		$paid    = Intent_Status::SUCCEEDED;
		$failed  = Intent_Status::REQUIRES_PAYMENT_METHOD;
		$cases   = [
			'COD after decline'                => [ 'cod', $test, $failed, 'pi_declined', 'pi_declined', 'processing', false ],
			'bank transfer after decline'      => [ 'bacs', $test, $failed, 'pi_declined', 'pi_declined', 'on-hold', false ],
			'other card gateway'               => [ 'stripe', $test, $failed, 'pi_declined', 'ch_other', 'completed', false ],
			'other gateway after test payment' => [ 'cod', $test, $paid, 'pi_test', 'pi_test', 'processing', false ],
			'stale gateway after decline'      => [ $gateway, $test, $failed, 'pi_declined', 'pi_declined', 'processing', false ],
			'stale gateway without status'     => [ $gateway, $test, '', 'pi_declined', 'pi_declined', 'completed', false ],
			'abandoned authentication'         => [ $gateway, $test, Intent_Status::REQUIRES_ACTION, 'pi_test', 'pi_test', 'pending', false ],
			'unconfirmed intent'               => [ $gateway, $test, Intent_Status::REQUIRES_CONFIRMATION, 'pi_test', 'pi_test', 'pending', false ],
			'cancelled authorization'          => [ $gateway, $test, Intent_Status::CANCELED, 'pi_test', 'pi_test', 'cancelled', false ],
			'failed test order'                => [ $gateway, $test, $failed, 'pi_test', 'pi_test', 'failed', false ],
			'no payment intent'                => [ $gateway, $test, $paid, '', '', 'completed', false ],
			'no current transaction'           => [ $gateway, $test, $paid, 'pi_test', '', 'completed', false ],
			'another current transaction'      => [ $gateway, $test, $paid, 'pi_test', 'pi_other', 'completed', false ],
			'gateway prefix collision'         => [ 'woocommerce_payments_otherplugin', $test, $failed, 'pi_test', 'pi_test', 'processing', false ],
			'gateway without family delimiter' => [ 'woocommerce_paymentscustom', $test, $paid, 'pi_test', 'pi_test', 'processing', false ],
			'no gateway'                       => [ '', $test, $paid, 'pi_test', 'pi_test', 'processing', false ],
			'live payment'                     => [ $gateway, Order_Mode::PRODUCTION, $paid, 'pi_live', 'pi_live', 'processing', false ],
			'noncanonical mode'                => [ $gateway, 'TEST', $paid, 'pi_test', 'pi_test', 'processing', false ],
			'no mode'                          => [ $gateway, '', $paid, 'pi_test', 'pi_test', 'processing', false ],
			'card payment'                     => [ $gateway, $test, $paid, 'pi_test', 'pi_test', 'processing', true ],
			'completed payment'                => [ $gateway, $test, $paid, 'pi_test', 'pi_test', 'completed', true ],
			'refunded test payment'            => [ $gateway, $test, $paid, 'pi_test', 'pi_test', 'refunded', true ],
			'authorization'                    => [ $gateway, $test, Intent_Status::REQUIRES_CAPTURE, 'pi_test', 'pi_test', 'on-hold', true ],
			'asynchronous payment'             => [ $gateway . '_sepa_debit', $test, Intent_Status::PROCESSING, 'pi_test', 'pi_test', 'on-hold', true ],
			'disabled legacy payment method'   => [ $gateway . '_giropay', $test, $paid, 'pi_test', 'pi_test', 'completed', true ],
			'zero-value setup intent'          => [ $gateway, $test, $paid, 'seti_test', 'seti_test', 'completed', true ],
		];

		foreach ( $cases as $name => $case ) {
			yield 'CPT: ' . $name => array_merge( $case, [ false ] );
			yield 'HPOS: ' . $name => array_merge( $case, [ true ] );
		}
	}

	/**
	 * Refunds can appear in the order query but do not own payment details.
	 *
	 * @dataProvider provide_order_storage_modes
	 * @param bool $hpos Whether to use HPOS.
	 */
	public function test_delete_test_orders_preserves_tagged_refunds( $hpos ) {
		$storage_filter = static function () use ( $hpos ) {
			return $hpos ? 'yes' : 'no';
		};
		add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage_filter );
		add_filter( 'pre_option_woocommerce_feature_custom_order_tables_enabled', $storage_filter );

		try {
			$this->assertSame( $hpos, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
			$order = wc_create_order();
			$order->set_payment_method( 'cod' );
			$order->save();
			$refund = new WC_Order_Refund();
			$refund->set_parent_id( $order->get_id() );
			$refund->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
			$refund->save();

			$result = $this->status->delete_test_orders();

			$this->assertSame( 'No test orders found.', $result );
			$this->assertInstanceOf( WC_Order::class, wc_get_order( $order->get_id() ) );
			$this->assertInstanceOf( WC_Order_Refund::class, wc_get_order( $refund->get_id() ) );
			$this->assertSame( 'test', wc_get_order( $refund->get_id() )->get_meta( '_wcpay_mode' ) );
		} finally {
			if ( isset( $order ) ) {
				$order->delete( true );
			}
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage_filter );
			remove_filter( 'pre_option_woocommerce_feature_custom_order_tables_enabled', $storage_filter );
		}
	}

	/**
	 * Storage backends supported by the deletion tool.
	 *
	 * @return array
	 */
	public function provide_order_storage_modes() {
		return [
			'CPT'  => [ false ],
			'HPOS' => [ true ],
		];
	}

	/**
	 * Test delete_test_orders handles exceptions.
	 */
	public function test_delete_test_orders_handles_exception() {
		// Mock wc_get_orders to throw an exception.
		$filter_callback = function () {
			throw new Exception( 'Database error' );
		};
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $filter_callback, 10, 2 );

		$result = $this->status->delete_test_orders();

		$this->assertStringContainsString( 'Error deleting test orders:', $result );
		$this->assertStringContainsString( 'Database error', $result );

		// Clean up filter.
		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $filter_callback, 10 );
	}

	/**
	 * Test delete_test_orders denies access for users without manage_woocommerce capability.
	 */
	public function test_delete_test_orders_requires_manage_woocommerce_capability() {
		// Create test orders with _wcpay_mode meta.
		$order1 = wc_create_order();
		$order1->update_meta_data( '_wcpay_mode', 'test' );
		$order1->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order1->set_transaction_id( 'pi_test' );
		$order1->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, 'pi_test' );
		$order1->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, Intent_Status::SUCCEEDED );
		$order1->save();

		$order2 = wc_create_order();
		$order2->update_meta_data( '_wcpay_mode', 'test' );
		$order2->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order2->set_transaction_id( 'pi_test' );
		$order2->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, 'pi_test' );
		$order2->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, Intent_Status::SUCCEEDED );
		$order2->save();

		// Mock that the current user is missing the manage_woocommerce capability.
		$filter_callback = function ( $allcaps ) {
			$allcaps['manage_woocommerce'] = false;

			return $allcaps;
		};
		add_filter( 'user_has_cap', $filter_callback );

		$result = $this->status->delete_test_orders();

		// Verify permission denied message.
		$this->assertEquals( 'You do not have permission to delete orders.', $result );

		// Verify orders were NOT deleted.
		$order1_check = wc_get_order( $order1->get_id() );
		$this->assertInstanceOf( WC_Order::class, $order1_check );

		$order2_check = wc_get_order( $order2->get_id() );
		$this->assertInstanceOf( WC_Order::class, $order2_check );

		// Clean up filter.
		remove_filter( 'user_has_cap', $filter_callback );
	}

	/**
	 * Test that the Dev Mode row shows "Disabled" when dev mode is off.
	 */
	public function test_dev_mode_row_shows_disabled_when_dev_mode_off(): void {
		$this->set_up_connected_mocks();
		WC_Payments::mode()->live();

		ob_start();
		$this->status->render_status_report_section();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'#data-export-label="Dev Mode">(?:(?!</tr>).)*<td>\s*Disabled\s*</td>\s*</tr>#s',
			$output
		);
	}

	/**
	 * Configures class-level mocks so that render_status_report_section() reaches the Dev Mode row.
	 */
	private function set_up_connected_mocks(): void {
		$this->mock_http->method( 'is_connected' )->willReturn( true );
		$this->mock_http->method( 'get_blog_id' )->willReturn( '123456789' );
		$this->mock_gateway->method( 'is_connected' )->willReturn( true );
		$this->mock_account->method( 'get_stripe_account_id' )->willReturn( 'acct_test123' );
		$this->mock_gateway->method( 'needs_setup' )->willReturn( false );
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );
		$this->mock_gateway->method( 'is_payment_request_enabled' )->willReturn( false );
		$this->mock_gateway->method( 'get_option' )->willReturnCallback(
			function ( $key, $default_value = null ) {
				$map = [
					'current_protection_level'       => 'standard',
					'manual_capture'                 => 'no',
					'account_business_support_phone' => '555-0123',
				];
				if ( isset( $map[ $key ] ) ) {
					return $map[ $key ];
				}
				if ( strpos( $key, 'express_checkout_' ) === 0 ) {
					return [];
				}
				return $default_value;
			}
		);
	}
}
