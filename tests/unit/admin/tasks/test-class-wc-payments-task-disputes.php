<?php
/**
 * Class WC_Payments_Task_Disputes_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WooCommerce\Payments\Tasks\WC_Payments_Task_Disputes;

/**
 * WC_Payments_Task_Disputes unit tests.
 */
class WC_Payments_Task_Disputes_Test extends WCPAY_UnitTestCase {
	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );

		parent::tear_down();
	}

	public function test_disputes_task_returns_the_correct_content() {
		$disputes_task = new WC_Payments_Task_Disputes();

		$this->assertEquals( '', $disputes_task->get_content() );
		$this->assertEquals( '', $disputes_task->get_time() );
		$this->assertEquals( 'woocommerce_payments_disputes_task', $disputes_task->get_id() );
		$this->assertEquals( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes&filter=awaiting_response', $disputes_task->get_action_url() );
		$this->assertEquals( 'Disputes', $disputes_task->get_action_label() );
		$this->assertEquals( 'View and respond', $disputes_task->get_additional_info() );
		$this->assertEquals( false, $disputes_task->is_complete() );
	}

	public function test_disputes_task_returns_the_correct_content_with_empty_needs_response() {
		$disputes_task = new WC_Payments_Task_Disputes();

		$this->mock_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( 'wcpay_dispute_status_counts_cache' )
			->willReturn( [ 'needs_response' => 0 ] );

		$this->assertEquals( '0 disputed payments need your response', $disputes_task->get_title() );
		$this->assertEquals( false, $disputes_task->can_view() );
	}

	public function test_disputes_task_returns_the_correct_content_with_empty_cache() {
		$disputes_task = new WC_Payments_Task_Disputes();

		$this->mock_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( 'wcpay_dispute_status_counts_cache' )
			->willReturn( [] );

		$this->assertEquals( '0 disputed payments need your response', $disputes_task->get_title() );
		$this->assertEquals( false, $disputes_task->can_view() );
	}

	public function test_disputes_task_returns_the_correct_content_with_single_item_on_needs_response() {
		$disputes_task = new WC_Payments_Task_Disputes();

		$this->mock_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( 'wcpay_dispute_status_counts_cache' )
			->willReturn( [ 'needs_response' => 1 ] );

		$this->assertEquals( '1 disputed payment needs your response', $disputes_task->get_title() );
		$this->assertEquals( true, $disputes_task->can_view() );
	}

	public function test_disputes_task_returns_the_correct_content_with_populated_needs_response() {
		$disputes_task = new WC_Payments_Task_Disputes();

		$this->mock_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( 'wcpay_dispute_status_counts_cache' )
			->willReturn( [ 'needs_response' => 2 ] );

		$this->assertEquals( '2 disputed payments need your response', $disputes_task->get_title() );
		$this->assertEquals( true, $disputes_task->can_view() );
	}
}
