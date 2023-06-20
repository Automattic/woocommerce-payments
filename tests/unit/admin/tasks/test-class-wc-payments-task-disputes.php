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

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_disputes' ] )
			->getMock();
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );

		parent::tear_down();
	}

	public function test_disputes_task_with_single_dispute_outside_7days() {
		$disputes_task        = new WC_Payments_Task_Disputes(
			$this->mock_api_client,
			$this->mock_cache
		);
		$mock_active_disputes = [
			[
				'wcpay_disputes_cache_id' => 21,
				'stripe_account_id'       => 'acct_abc',
				'dispute_id'              => 'dp_2',
				'charge_id'               => 'ch_2',
				'amount'                  => 2000,
				'currency'                => 'eur',
				'reason'                  => 'product_not_received',
				'source'                  => 'visa',
				'order_number'            => 14,
				'customer_name'           => 'customer',
				'customer_email'          => 'email@email.com',
				'customer_country'        => 'US',
				'status'                  => 'needs_response',
				'created'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'due_by'                  => gmdate( 'Y-m-d H:i:s', strtotime( '+9 days' ) ),
			],
		];
		$this->mock_cache->method( 'get_or_add' )->willReturn(
			$mock_active_disputes
		);

		$this->assertEquals( false, $disputes_task->can_view() );
	}

	public function test_disputes_task_with_single_dispute_within_7days() {
		$disputes_task        = new WC_Payments_Task_Disputes(
			$this->mock_api_client,
			$this->mock_cache
		);
		$mock_active_disputes = [
			[
				'wcpay_disputes_cache_id' => 21,
				'stripe_account_id'       => 'acct_abc',
				'dispute_id'              => 'dp_2',
				'charge_id'               => 'ch_2',
				'amount'                  => 2000,
				'currency'                => 'eur',
				'reason'                  => 'product_not_received',
				'source'                  => 'visa',
				'order_number'            => 14,
				'customer_name'           => 'customer',
				'customer_email'          => 'email@email.com',
				'customer_country'        => 'US',
				'status'                  => 'needs_response',
				'created'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'due_by'                  => gmdate( 'Y-m-d H:i:s', strtotime( '+6 days' ) ),
			],
		];
		$this->mock_cache->method( 'get_or_add' )->willReturn(
			$mock_active_disputes
		);

		$this->assertEquals( 'Respond to a dispute for 20,00 €', $disputes_task->get_title() );
		// "By <date> – <count> days left to respond"
		$this->assertMatchesRegularExpression( '/By \w{3} \d{1,2}, \d{4} – \d+ days left to respond/', $disputes_task->get_additional_info() );
		$this->assertEquals( true, $disputes_task->can_view() );

	}

	public function test_disputes_task_with_single_dispute_within_24h() {
		$disputes_task        = new WC_Payments_Task_Disputes(
			$this->mock_api_client,
			$this->mock_cache
		);
		$mock_active_disputes = [
			[
				'wcpay_disputes_cache_id' => 21,
				'stripe_account_id'       => 'acct_abc',
				'dispute_id'              => 'dp_2',
				'charge_id'               => 'ch_2',
				'amount'                  => 2000,
				'currency'                => 'eur',
				'reason'                  => 'product_not_received',
				'source'                  => 'visa',
				'order_number'            => 14,
				'customer_name'           => 'customer',
				'customer_email'          => 'email@email.com',
				'customer_country'        => 'US',
				'status'                  => 'needs_response',
				'created'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'due_by'                  => gmdate( 'Y-m-d H:i:s', strtotime( '+23 hours' ) ),
			],
		];
		$this->mock_cache->method( 'get_or_add' )->willReturn(
			$mock_active_disputes
		);

		$this->assertEquals( 'Respond to a dispute for 20,00 € – Last day', $disputes_task->get_title() );
		// "Respond today by <time> <AM|PM>"
		$this->assertMatchesRegularExpression( '/Respond today by \d{1,2}:\d{2} (AM|PM)/', $disputes_task->get_additional_info() );
		$this->assertEquals( true, $disputes_task->can_view() );

	}

	public function test_disputes_task_with_multiple_disputes_within_7days() {
		$disputes_task        = new WC_Payments_Task_Disputes(
			$this->mock_api_client,
			$this->mock_cache
		);
		$mock_active_disputes = [
			[
				'wcpay_disputes_cache_id' => 21,
				'stripe_account_id'       => 'acct_abc',
				'dispute_id'              => 'dp_2',
				'charge_id'               => 'ch_2',
				'amount'                  => 2000,
				'currency'                => 'eur',
				'reason'                  => 'product_not_received',
				'source'                  => 'visa',
				'order_number'            => 14,
				'customer_name'           => 'customer',
				'customer_email'          => 'email@email.com',
				'customer_country'        => 'US',
				'status'                  => 'needs_response',
				'created'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'due_by'                  => gmdate( 'Y-m-d H:i:s', strtotime( '+6 days' ) ),
			],
			[
				'wcpay_disputes_cache_id' => 21,
				'stripe_account_id'       => 'acct_abc',
				'dispute_id'              => 'dp_2',
				'charge_id'               => 'ch_2',
				'amount'                  => 1234,
				'currency'                => 'usd',
				'reason'                  => 'fraudulent',
				'source'                  => 'visa',
				'order_number'            => 14,
				'customer_name'           => 'customer',
				'customer_email'          => 'email@email.com',
				'customer_country'        => 'US',
				'status'                  => 'warning_needs_response',
				'created'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'due_by'                  => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days' ) ),
			],
		];
		$this->mock_cache->method( 'get_or_add' )->willReturn(
			$mock_active_disputes
		);

		$this->assertEquals( 'Respond to 2 active disputes for a total of 20,00 €, $12.34', $disputes_task->get_title() );
		$this->assertEquals( 'Last week to respond to 2 of the disputes', $disputes_task->get_additional_info() );
		$this->assertEquals( true, $disputes_task->can_view() );
	}

	public function test_disputes_task_with_multiple_disputes_within_24h() {
		$disputes_task        = new WC_Payments_Task_Disputes(
			$this->mock_api_client,
			$this->mock_cache
		);
		$mock_active_disputes = [
			[
				'wcpay_disputes_cache_id' => 21,
				'stripe_account_id'       => 'acct_abc',
				'dispute_id'              => 'dp_2',
				'charge_id'               => 'ch_2',
				'amount'                  => 2000,
				'currency'                => 'eur',
				'reason'                  => 'product_not_received',
				'source'                  => 'visa',
				'order_number'            => 14,
				'customer_name'           => 'customer',
				'customer_email'          => 'email@email.com',
				'customer_country'        => 'US',
				'status'                  => 'needs_response',
				'created'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'due_by'                  => gmdate( 'Y-m-d H:i:s', strtotime( '+23 hours' ) ),
			],
			[
				'wcpay_disputes_cache_id' => 21,
				'stripe_account_id'       => 'acct_abc',
				'dispute_id'              => 'dp_2',
				'charge_id'               => 'ch_2',
				'amount'                  => 1234,
				'currency'                => 'usd',
				'reason'                  => 'fraudulent',
				'source'                  => 'visa',
				'order_number'            => 14,
				'customer_name'           => 'customer',
				'customer_email'          => 'email@email.com',
				'customer_country'        => 'US',
				'status'                  => 'warning_needs_response',
				'created'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'due_by'                  => gmdate( 'Y-m-d H:i:s', strtotime( '+23 hours' ) ),
			],
		];
		$this->mock_cache->method( 'get_or_add' )->willReturn(
			$mock_active_disputes
		);

		$this->assertEquals( 'Respond to 2 active disputes for a total of 20,00 €, $12.34', $disputes_task->get_title() );
		$this->assertEquals( 'Final day to respond to 2 of the disputes', $disputes_task->get_additional_info() );
		$this->assertEquals( true, $disputes_task->can_view() );

	}
}
