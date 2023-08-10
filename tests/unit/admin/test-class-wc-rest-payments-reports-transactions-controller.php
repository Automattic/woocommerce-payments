<?php
/**
 * Class WC_REST_Payments_Reader_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Core\Server\Request\List_Transactions;

/**
 * WC_REST_Payments_Reports_Transactions_Controller unit tests.
 */
class WC_REST_Payments_Reports_Transactions_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Reports_Transactions_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function set_up() {
		parent::set_up();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Reports_Transactions_Controller( $this->mock_api_client );
	}


	public function test_get_transactions_success() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'per_page', 2 );

		$mock_request = $this->mock_wcpay_request( List_Transactions::class );
		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $this->get_transactions_list_from_server() );

		// check that in the end, page size is set correctly.
		$mock_request->expects( $this->any() )
			->method( 'set_page_size' )
			->withConsecutive(
				[ $this->anything() ],
				[ '2' ]
			);
		$response = $this->controller->get_transactions( $request );
		$this->assertEquals( $this->get_transactions_list(), $response->get_data() );
	}

	public function test_get_transactions_response_error() {
		$request = new WP_REST_Request( 'POST' );

		$mock_request = $this->mock_wcpay_request( List_Transactions::class );
		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new Connection_Exception(
						'Test error.',
						'wcpay_http_request_failed',
						400
					)
				)
			);

		$response = $this->controller->get_transactions( $request );
		$expected = new WP_Error( 'wcpay_http_request_failed', 'Test error.' );
		$this->assertEquals( $expected, $response );
	}


	public function test_get_transactions_filter_type() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'type', 'refund' );

		$mock_request = $this->mock_wcpay_request( List_Transactions::class );
		$mock_request->expects( $this->once() )
			->method( 'set_type_is' )
			->with( 'refund' );

		$this->controller->get_transactions( $request );
	}

	public function test_get_transactions_filter_order_id() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'order_id', 123 );

		$mock_request = $this->mock_wcpay_request( List_Transactions::class );
		$mock_request->expects( $this->any() )
			->method( 'set_filters' )
			->withConsecutive(
				[ $this->anything() ],
				[
					[
						'order_id_is'       => 123,
						'customer_email_is' => null,
						'source_is'         => null,
					],
				]
			);

		$this->controller->get_transactions( $request );
	}

	public function test_get_transactions_filter_all() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'order_id', 345 );
		$request->set_param( 'customer_email', 'test@woocommerce.com' );
		$request->set_param( 'payment_method_type', 'visa' );

		$mock_request = $this->mock_wcpay_request( List_Transactions::class );
		$mock_request->expects( $this->any() )
			->method( 'set_filters' )
			->withConsecutive(
				[ $this->anything() ],
				[
					[
						'order_id_is'       => 345,
						'customer_email_is' => 'test@woocommerce.com',
						'source_is'         => 'visa',
					],
				]
			);

		$this->controller->get_transactions( $request );
	}

	private function get_transactions_list_from_server() {
		return [
			'data' => [
				[
					'transaction_id'    => 'txn_123',
					'type'              => 'charge',
					'date'              => '2023-07-19 10:27:55',
					'source'            => 'visa',
					'source_identifier' => '3184',
					'customer_name'     => 'Test Customer1',
					'customer_email'    => 'test1@woocommerce.com',
					'customer_country'  => 'US',
					'amount'            => 2583,
					'net'               => 2426,
					'fees'              => 157,
					'currency'          => 'usd',
					'risk_level'        => 0,
					'charge_id'         => 'ch_3NVXQQR7Mcmd7SUg0eV2k74L',
					'deposit_id'        => 'wcpay_estimated_daily_usd_1689897600',
					'available_on'      => '2023-07-21',
					'exchange_rate'     => 1.12284,
					'customer_amount'   => 2300,
					'customer_currency' => 'eur',
					'order_id'          => 123,
					'amount_in_usd'     => 2583,
					'source_device'     => null,
					'channel'           => null,
					'deposit_status'    => 'estimated',
					'order'             => [
						'number'        => '123',
						'url'           => 'https:\/\/wcpay.test\/wp-admin\/post.php?post=278&action=edit',
						'customer_url'  => 'admin.php?page=wc-admin&path=\/customers&filter=single_customer&customers=1',
						'subscriptions' => [],
					],
					'payment_intent_id' => 'pi_345',
				],
				[
					'transaction_id'    => 'txn_345',
					'type'              => 'charge',
					'date'              => '2023-07-20 10:16:37',
					'source'            => 'giropay',
					'source_identifier' => '3184',
					'customer_name'     => 'Test Customer2',
					'customer_email'    => 'test2@woocommerce.com',
					'customer_country'  => 'US',
					'amount'            => 2583,
					'net'               => 2452,
					'fees'              => 131,
					'currency'          => 'usd',
					'risk_level'        => 0,
					'charge_id'         => 'ch_3NVXQER7Mcmd7SUg1Mk9SsNy',
					'deposit_id'        => 'wcpay_estimated_daily_usd_1689897600',
					'available_on'      => '2023-07-21',
					'exchange_rate'     => 1.12284,
					'customer_amount'   => 2300,
					'customer_currency' => 'eur',
					'order_id'          => 275,
					'amount_in_usd'     => 2583,
					'source_device'     => null,
					'channel'           => null,
					'deposit_status'    => 'estimated',
					'order'             => [
						'number'        => '275',
						'url'           => 'https:\/\/wcpay.test\/wp-admin\/post.php?post=275&action=edit',
						'customer_url'  => 'admin.php?page=wc-admin&path=\/customers&filter=single_customer&customers=1',
						'subscriptions' => [],
					],
					'payment_intent_id' => 'pi_678',
				],
			],
		];
	}


	private function get_transactions_list() {
		return [
			[
				'transaction_id'       => 'txn_123',
				'date'                 => '2023-07-19 10:27:55',
				'payment_id'           => 'pi_345',
				'channel'              => null,
				'payment_method'       => [
					'type' => 'visa',
				],
				'type'                 => 'charge',
				'transaction_currency' => 'eur',
				'amount'               => 2583,
				'exchange_rate'        => 1.12284,
				'deposit_currency'     => 'usd',
				'fees'                 => 157,
				'customer'             => [
					'name'    => 'Test Customer1',
					'email'   => 'test1@woocommerce.com',
					'country' => 'US',
				],
				'net_amount'           => 2426,
				'order_id'             => 123,
				'risk_level'           => 0,
				'deposit_date'         => '2023-07-21',
				'deposit_id'           => 'wcpay_estimated_daily_usd_1689897600',
				'deposit_status'       => 'estimated',
			],
			[
				'transaction_id'       => 'txn_345',
				'date'                 => '2023-07-20 10:16:37',
				'payment_id'           => 'pi_678',
				'channel'              => null,
				'payment_method'       => [
					'type' => 'giropay',
				],
				'type'                 => 'charge',
				'transaction_currency' => 'eur',
				'amount'               => 2583,
				'exchange_rate'        => 1.12284,
				'deposit_currency'     => 'usd',
				'fees'                 => 131,
				'customer'             => [
					'name'    => 'Test Customer2',
					'email'   => 'test2@woocommerce.com',
					'country' => 'US',
				],
				'net_amount'           => 2452,
				'order_id'             => 275,
				'risk_level'           => 0,
				'deposit_date'         => '2023-07-21',
				'deposit_id'           => 'wcpay_estimated_daily_usd_1689897600',
				'deposit_status'       => 'estimated',
			],
		];
	}
}
