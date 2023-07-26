<?php
/**
 * Class List_Deposits_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions;

/**
 * WCPay\Core\Server\List_Fraud_Outcome_Transactions_Test unit tests.
 */
class List_Fraud_Outcome_Transactions_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_Http_Interface|MockObject
	 */
	private $mock_wc_payments_http_client;

	/**
	 * Set up the unit tests objects.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client              = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wc_payments_http_client = $this->createMock( WC_Payments_Http_Interface::class );
	}

	public function test_list_fraud_outcome_transactions_request() {
		$page        = 2;
		$page_size   = 50;
		$direction   = 'asc';
		$sort        = 'date';
		$search      = [ 'search' ];
		$search_term = 'search_term';
		$status      = 'block';

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_page( $page );
		$request->set_page_size( $page_size );
		$request->set_sort_direction( $direction );
		$request->set_sort_by( $sort );
		$request->set_search( $search );
		$request->set_search_term( $search_term );
		$request->set_status( $status );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $search, $params['search'] );
		$this->assertSame( $search_term, $params['search_term'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::FRAUD_OUTCOMES_API . '/status/' . $status, $request->get_api() );

	}
	public function test_list_fraud_outcome_transactions_request_using_from_rest_request_function() {
		$page        = 2;
		$page_size   = 50;
		$direction   = 'asc';
		$sort        = 'date';
		$search      = [ 'search' ];
		$search_term = 'search_term';
		$status      = 'block';

		$rest_request = new WP_REST_Request( 'GET' );
		$rest_request->set_param( 'page', $page );
		$rest_request->set_param( 'pagesize', $page_size );
		$rest_request->set_param( 'sort', $sort );
		$rest_request->set_param( 'direction', $direction );
		$rest_request->set_param( 'search', $search );
		$rest_request->set_param( 'search_term', $search_term );
		$rest_request->set_param( 'status', $status );

		$request = List_Fraud_Outcome_Transactions::from_rest_request( $rest_request );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $search, $params['search'] );
		$this->assertSame( $search_term, $params['search_term'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::FRAUD_OUTCOMES_API . '/status/' . $status, $request->get_api() );
	}

	public function test_list_fraud_outcome_transactions_request_format_response() {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'succeeded' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review_approved' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id'          => $mock_first_order->get_id(),
				'payment_intent_id' => 'pi_123',
			],
			[
				'order_id' => $mock_second_order->get_id(),
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'asc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$expected = [
			[
				'order_id'            => $mock_first_order->get_id(),
				'payment_intent'      => [
					'id'     => 'pi_123',
					'status' => 'requires_capture',
				],
				'amount'              => 5000,
				'currency'            => $mock_first_order->get_currency(),
				'customer_name'       => wc_clean( $mock_first_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
				'fraud_meta_box_type' => 'review',
			],
		];

		$this->assertEquals( $expected, $result );
	}

	public function test_list_fraud_outcome_transactions_request_format_response_invalid_response() {
		$mock_response = null;

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'asc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$this->assertEquals( $mock_response, $result );
	}

	public function test_list_fraud_outcome_transactions_request_format_response_correct_order_desc() {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_intent_id', 'pi_123' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_second_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id' => $mock_first_order->get_id(),
				'created'  => 1681136843,
			],
			[
				'order_id' => $mock_second_order->get_id(),
				'created'  => 1681136943,
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'desc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$expected = [
			array_merge(
				$mock_response[1],
				[
					'payment_intent'      => [
						'id'     => 'pi_234',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_second_order->get_currency(),
					'customer_name'       => wc_clean( $mock_second_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
			array_merge(
				$mock_response[0],
				[
					'payment_intent'      => [
						'id'     => 'pi_123',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_first_order->get_currency(),
					'customer_name'       => wc_clean( $mock_first_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
		];

		$this->assertEquals( $expected, $result );
	}

	public function test_list_fraud_outcome_transactions_request_format_response_correct_order_asc() {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_intent_id', 'pi_123' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_second_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id' => $mock_first_order->get_id(),
				'created'  => 1681136843,
			],
			[
				'order_id' => $mock_second_order->get_id(),
				'created'  => 1681136943,
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'asc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$expected = [
			array_merge(
				$mock_response[0],
				[
					'payment_intent'      => [
						'id'     => 'pi_123',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_first_order->get_currency(),
					'customer_name'       => wc_clean( $mock_first_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
			array_merge(
				$mock_response[1],
				[
					'payment_intent'      => [
						'id'     => 'pi_234',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_second_order->get_currency(),
					'customer_name'       => wc_clean( $mock_second_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
		];

		$this->assertEquals( $expected, $result );
	}

	/**
	 * @dataProvider provider_get_sort_key
	 */
	public function test_list_fraud_outcome_transactions_request_format_response_correct_order_sort_key( $sort_key ) {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_intent_id', 'pi_123' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_second_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id' => $mock_first_order->get_id(),
				'created'  => 1681136843,
			],
			[
				'order_id' => $mock_second_order->get_id(),
				'created'  => 1681136843,
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'asc' );
		$request->set_sort_by( $sort_key );
		$request->set_search( [] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$expected = [
			array_merge(
				$mock_response[0],
				[
					'payment_intent'      => [
						'id'     => 'pi_123',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_first_order->get_currency(),
					'customer_name'       => wc_clean( $mock_first_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
			array_merge(
				$mock_response[1],
				[
					'payment_intent'      => [
						'id'     => 'pi_234',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_second_order->get_currency(),
					'customer_name'       => wc_clean( $mock_second_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
		];

		$this->assertEquals( $expected, $result );
	}

	public function provider_get_sort_key(): array {
		return [
			[ 'date' ],
			[ 'invalid-key' ],
		];
	}

	public function test_list_fraud_outcome_transactions_request_format_response_filtered_by_search_order_id() {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_intent_id', 'pi_123' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id' => $mock_first_order->get_id(),
				'created'  => 1681136843,
			],
			[
				'order_id' => $mock_second_order->get_id(),
				'created'  => 1681136943,
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'desc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [ 'Order #' . $mock_first_order->get_id() ] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$expected = [
			array_merge(
				$mock_response[0],
				[
					'payment_intent'      => [
						'id'     => 'pi_123',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_first_order->get_currency(),
					'customer_name'       => wc_clean( $mock_first_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
		];

		$this->assertEquals( $expected, $result );
	}

	public function test_list_fraud_outcome_transactions_request_format_response_filtered_by_search_multiple_search_parameters() {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_intent_id', 'pi_123' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id' => $mock_first_order->get_id(),
				'created'  => 1681136843,
			],
			[
				'order_id' => $mock_second_order->get_id(),
				'created'  => 1681136943,
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'desc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [ 'Order #' . $mock_first_order->get_id(), wc_clean( $mock_first_order->get_billing_first_name() ) ] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$expected = [
			array_merge(
				$mock_response[0],
				[
					'payment_intent'      => [
						'id'     => 'pi_123',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_first_order->get_currency(),
					'customer_name'       => wc_clean( $mock_first_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
		];

		$this->assertEquals( $expected, $result );
	}

	public function test_list_fraud_outcome_transactions_request_format_response_filtered_by_search_customer_name() {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_intent_id', 'pi_123' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->set_billing_last_name( 'Doe' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_second_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id' => $mock_first_order->get_id(),
				'created'  => 1681136843,
			],
			[
				'order_id' => $mock_second_order->get_id(),
				'created'  => 1681136943,
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'desc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [ $mock_first_order->get_billing_last_name() ] );
		$request->set_status( 'review' );

		$result = $request->format_response( $mock_response );

		$expected = [
			array_merge(
				$mock_response[0],
				[
					'payment_intent'      => [
						'id'     => 'pi_123',
						'status' => 'requires_capture',
					],
					'amount'              => 5000,
					'currency'            => $mock_first_order->get_currency(),
					'customer_name'       => wc_clean( $mock_first_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_first_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review',
				]
			),
		];

		$this->assertEquals( $expected, $result );
	}

	public function test_list_fraud_outcome_transactions_request_filters_out_non_blocked_outcomes() {
		$mock_first_order = WC_Helper_Order::create_order();
		$mock_first_order->add_meta_data( '_intention_status', 'requires_capture' );
		$mock_first_order->add_meta_data( '_intent_id', 'pi_123' );
		$mock_first_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review' );
		$mock_first_order->set_billing_last_name( 'Doe' );
		$mock_first_order->save();

		$mock_second_order = WC_Helper_Order::create_order();
		$mock_second_order->add_meta_data( '_intention_status', 'canceled' );
		$mock_second_order->add_meta_data( '_intent_id', 'pi_234' );
		$mock_second_order->add_meta_data( '_wcpay_fraud_meta_box_type', 'review_blocked' );
		$mock_second_order->save();

		$mock_response = [
			[
				'order_id' => $mock_first_order->get_id(),
				'created'  => 1681136843,
			],
			[
				'order_id' => $mock_second_order->get_id(),
				'created'  => 1681136943,
			],
		];

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_sort_direction( 'desc' );
		$request->set_sort_by( 'date' );
		$request->set_search( [] );
		$request->set_status( 'block' );

		$result = $request->format_response( $mock_response );

		$expected = [
			array_merge(
				$mock_response[1],
				[
					'payment_intent'      => [
						'id'     => 'pi_234',
						'status' => 'canceled',
					],
					'amount'              => 5000,
					'currency'            => $mock_second_order->get_currency(),
					'customer_name'       => wc_clean( $mock_second_order->get_billing_first_name() ) . ' ' . wc_clean( $mock_second_order->get_billing_last_name() ),
					'fraud_meta_box_type' => 'review_blocked',
				]
			),
		];

		$this->assertEquals( $expected, $result );
	}
}
