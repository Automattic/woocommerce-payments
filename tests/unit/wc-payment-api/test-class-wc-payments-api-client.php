<?php
/**
 * Class WC_Payments_API_Client_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\Constants\Intent_Status;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\API_Merchant_Exception;
use WCPay\Internal\Logger;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Fraud_Prevention\Buyer_Fingerprinting_Service;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_API_Client unit tests.
 */
class WC_Payments_API_Client_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Mock HTTP client.
	 *
	 * @var WC_Payments_Http&MockObject
	 */
	private $mock_http_client;

	/**
	 * Mock DB wrapper.
	 *
	 * @var WC_Payments_DB&MockObject
	 */
	private $mock_db_wrapper;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_http_client = $this
			->getMockBuilder( 'WC_Payments_Http' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_blog_id', 'is_connected', 'remote_request' ] )
			->getMock();

		$this->mock_db_wrapper = $this
			->getMockBuilder( 'WC_Payments_DB' )
			->disableOriginalConstructor()
			->getMock();

		$this->payments_api_client = new WC_Payments_API_Client(
			'Unit Test Agent/0.1.0',
			$this->mock_http_client,
			$this->mock_db_wrapper
		);
	}

	/**
	 * Traverse real typed requests while mocking only the remote HTTP boundary.
	 *
	 * @dataProvider refund_collection_provider
	 */
	public function test_refund_history_collection( $scenario, $reason, $calls, $count ) {
		require_once dirname( __DIR__, 3 ) . '/src/Internal/Service/RefundHistoryCollection.php';
		$factory = function ( $existing, $request_class ) {
			return \WCPay\Core\Server\Request\List_Charge_Refunds::class === $request_class
				? new $request_class( $this->payments_api_client, $this->mock_http_client ) : $existing;
		};
		add_filter( 'wcpay_create_request', $factory, 10, 2 );
		$index = 0;
		$this->mock_http_client->expects( $this->exactly( $calls ) )->method( 'remote_request' )->willReturnCallback(
			function ( $args ) use ( &$index, $scenario ) {
				parse_str( wp_parse_url( $args['url'], PHP_URL_QUERY ), $query );
				$this->assertSame( '0', $query['test_mode'] );
				$this->assertSame( 'ch_history', $query['charge'] );
				$this->assertSame( '1', $query['include_reporting_context'] );
				$this->assertSame( [ 'data.balance_transaction', 'data.failure_balance_transaction' ], $query['expand'] );
				if ( $index ) {
					$this->assertSame( 're_page' . $index, $query['starting_after'] );
				} else {
					$this->assertArrayNotHasKey( 'starting_after', $query );
				}
				++$index;
				if ( 'offline' === $scenario && 2 === $index ) {
					throw new RuntimeException( 'Synthetic offline response' );
				}
				$id                              = 'repeat' === $scenario ? 're_page1' : 're_page' . $index;
				$data                            = [
					'object'   => 'list',
					'has_more' => $index < 2 || 'limit' === $scenario,
					'data'     => [
						[
							'object'                      => 'refund',
							'id'                          => $id,
							'charge'                      => 'ch_history',
							'status'                      => 'failed',
							'balance_transaction'         => 'txn_debit',
							'failure_balance_transaction' => 'txn_reversal',
						],
					],
				];
				$data['wcpay_reporting_context'] = [
					'version'    => 1,
					'account_id' => 'acct_original',
					'site_id'    => 123,
					'test_mode'  => false,
				];
				if ( 'context_missing' === $scenario ) {
					unset( $data['wcpay_reporting_context'] );
				} elseif ( 2 === $index && 'context_account' === $scenario ) {
					$data['wcpay_reporting_context']['account_id'] = 'acct_other';
				} elseif ( 2 === $index && 'context_mode' === $scenario ) {
					$data['wcpay_reporting_context']['test_mode'] = true;
				} elseif ( 2 === $index && 'context_site' === $scenario ) {
					$data['wcpay_reporting_context']['site_id'] = 456;
				}
				if ( 'missing_marker' === $scenario ) {
					unset( $data['has_more'] );
				} elseif ( 'empty_more' === $scenario ) {
					$data['data'] = [];
				} elseif ( 'wrong_charge' === $scenario ) {
					$data['data'][0]['charge'] = 'ch_other';
				} elseif ( 'empty_complete' === $scenario ) {
					$data['data']     = [];
					$data['has_more'] = false;
				}
				return [
					'body'     => wp_json_encode( $data ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'cookies'  => [],
				];
			}
		);
		try {
			$started_at = time();
			$result     = ( new \WCPay\Internal\Service\RefundHistoryCollection() )->collect(
				'ch_history',
				false,
				[
					'version'    => 1,
					'account_id' => 'acct_original',
					'site_id'    => 123,
					'test_mode'  => false,
				]
			);
			$this->assertIsArray( $result['retrieval'] ?? null );
			$this->assertGreaterThanOrEqual( $started_at, $result['retrieval']['started_at'] );
			$this->assertGreaterThanOrEqual( $result['retrieval']['started_at'], $result['retrieval']['completed_at'] );
			$this->assertLessThanOrEqual( time(), $result['retrieval']['completed_at'] );
			$this->assertSame( 'ch_history', $result['charge_id'] );
			$this->assertSame( $reason ? 'incomplete' : 'listed', $result['state'] );
			$this->assertSame( $reason, $result['reason'] ?? null );
			$this->assertCount( $count, $result['refunds'] );
			$this->assertSame(
				[
					'version'    => 1,
					'account_id' => 'acct_original',
					'site_id'    => 123,
					'test_mode'  => false,
				],
				$result['reporting_context']
			);
			if ( $count ) {
				$this->assertSame( 'txn_reversal', $result['refunds'][0]['failure_balance_transaction'] );
			}
		} finally {
			remove_filter( 'wcpay_create_request', $factory, 10 );
		}
	}

	/**
	 * Invalid queued provenance cannot trigger a remote request.
	 */
	public function test_refund_collection_rejects_invalid_expected_context() {
		require_once dirname( __DIR__, 3 ) . '/src/Internal/Service/RefundHistoryCollection.php';
		$this->mock_http_client->expects( $this->never() )->method( 'remote_request' );
		$valid = [
			'version'    => 1,
			'account_id' => 'acct_original',
			'site_id'    => 123,
			'test_mode'  => false,
		];
		foreach ( [ [], array_merge( $valid, [ 'version' => 2 ] ), array_merge( $valid, [ 'account_id' => '' ] ), array_merge( $valid, [ 'site_id' => '123' ] ), array_merge( $valid, [ 'test_mode' => 0 ] ), array_merge( $valid, [ 'extra' => true ] ) ] as $context ) {
			$this->assertSame(
				[
					'state'   => 'incomplete',
					'reason'  => 'refund_expected_context_invalid',
					'refunds' => [],
				],
				( new \WCPay\Internal\Service\RefundHistoryCollection() )->collect( 'ch_history', false, $context )
			);
		}
	}

	/**
	 * Traversal cases, not accounting qualification cases.
	 *
	 * @return array Test scenarios.
	 */
	public function refund_collection_provider() {
		return [
			'missing server context'      => [ 'context_missing', 'refund_context_mismatch', 1, 0 ],
			'account changes on page two' => [ 'context_account', 'refund_context_mismatch', 2, 1 ],
			'mode changes on page two'    => [ 'context_mode', 'refund_context_mismatch', 2, 1 ],
			'site changes on page two'    => [ 'context_site', 'refund_context_mismatch', 2, 1 ],
			'two pages'                   => [ 'normal', null, 2, 2 ],
			'old server repeats'          => [ 'repeat', 'refund_pagination_repeated', 2, 1 ],
			'missing coverage marker'     => [ 'missing_marker', 'refund_response_invalid', 1, 0 ],
			'empty unfinished page'       => [ 'empty_more', 'refund_pagination_empty', 1, 0 ],
			'wrong charge'                => [ 'wrong_charge', 'refund_identity_invalid', 1, 0 ],
			'empty complete list'         => [ 'empty_complete', null, 1, 0 ],
			'offline after first page'    => [ 'offline', 'refund_retrieval_failed', 2, 1 ],
			'bounded traversal'           => [ 'limit', 'refund_page_limit', 20, 20 ],
		];
	}

	/**
	 * Historical refund mode survives the real API client's default parameters.
	 */
	public function test_refund_request_historical_mode_reaches_http_boundary() {
		$mode_property = new ReflectionProperty( \WCPay\Core\Mode::class, 'test_mode' );
		$mode_property->setAccessible( true );
		$previous_mode = $mode_property->getValue( WC_Payments::mode() );
		$observed      = [];
		$this->mock_http_client->expects( $this->exactly( 4 ) )->method( 'remote_request' )->willReturnCallback(
			function ( $args ) use ( &$observed ) {
				parse_str( wp_parse_url( $args['url'], PHP_URL_QUERY ), $query );
				$observed[] = $query['test_mode'];
				$this->assertSame( 'py_historical', $query['charge'] );
				if ( count( $observed ) % 2 ) {
					$this->assertSame( 're_previous', $query['starting_after'] );
					$this->assertSame( [ 'data.balance_transaction', 'data.failure_balance_transaction' ], $query['expand'] );
				} else {
					$this->assertArrayNotHasKey( 'starting_after', $query );
					$this->assertArrayNotHasKey( 'expand', $query );
				}
				return [
					'body'     => '[]',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'cookies'  => [],
				];
			}
		);
		try {
			foreach ( [ false, true ] as $historical_mode ) {
				$mode_property->setValue( WC_Payments::mode(), ! $historical_mode );
				foreach ( [ true, false ] as $explicit ) {
					$request = new \WCPay\Core\Server\Request\List_Charge_Refunds( $this->payments_api_client, $this->mock_http_client );
					$request->set_charge( 'py_historical' );
					if ( $explicit ) {
						$request->set_test_mode( $historical_mode );
						$request->set_starting_after( 're_previous' );
						$request->set_expand_balance_transactions();
					}
					$this->assertSame( [], $request->send()->to_array() );
					$this->assertSame( ! $historical_mode, WC_Payments::mode()->is_test() );
				}
			}
			$this->assertSame( [ '0', '1', '1', '0' ], $observed );
		} finally {
			$mode_property->setValue( WC_Payments::mode(), $previous_mode );
		}
	}

	/**
	 * Charge enrichment must preserve historical evidence and not invent missing fields.
	 *
	 * @dataProvider charge_snapshot_response_provider
	 */
	public function test_charge_request_preserves_snapshot_evidence( $scenario, $reason, $hpos, $test_mode ) {
		require_once dirname( __DIR__, 3 ) . '/src/Internal/Service/CapturedPaymentSnapshot.php';
		$request          = new \WCPay\Core\Server\Request\Get_Charge( $this->payments_api_client, $this->mock_http_client, 'ch_fixture' );
		$validator        = new \WCPay\Internal\Service\CapturedPaymentSnapshot();
		$expected_context = [
			'version'    => 1,
			'account_id' => 'acct_original',
			'site_id'    => 123,
			'test_mode'  => $test_mode,
		];
		$charge           = [
			'wcpay_reporting_context' => $expected_context,
			'id'                      => 'ch_fixture',
			'livemode'                => ! $test_mode,
			'paid'                    => true,
			'captured'                => true,
			'status'                  => 'succeeded',
			'amount'                  => 3600,
			'amount_captured'         => 3600,
			'currency'                => 'gbp',
			'amount_refunded'         => 0,
			'disputed'                => false,
			'balance_transaction'     => [
				'id'            => 'txn_fixture',
				'amount'        => 4190,
				'currency'      => 'eur',
				'created'       => 1700000000,
				'fee'           => 193,
				'net'           => 3997,
				'exchange_rate' => 1.16,
			],
		];
		if ( 'missing_id' === $scenario ) {
			unset( $charge['balance_transaction']['id'] );
		} elseif ( 'unexpanded' === $scenario ) {
			$charge['balance_transaction'] = 'txn_fixture';
		} elseif ( 'wrong_currency' === $scenario ) {
			$charge['balance_transaction']['currency'] = 'usd';
		}
		$fail_request   = false;
		$during_request = null;
		$observed_mode  = null;
		$wire_mode      = null;
		$wire_context   = null;
		$request_count  = 0;
		$this->mock_http_client->method( 'remote_request' )->willReturnCallback(
			static function ( $args ) use ( &$charge, &$fail_request, &$during_request, &$observed_mode, &$wire_mode, &$wire_context, &$request_count ) {
				parse_str( wp_parse_url( $args['url'], PHP_URL_QUERY ), $query );
				$wire_mode    = $query['test_mode'] ?? null;
				$wire_context = $query['include_reporting_context'] ?? null;
				++$request_count;
				// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Observes the existing mode hook for request isolation.
				$observed_mode = apply_filters( 'wcpay_test_mode', false );
				if ( $during_request ) {
					$during_request();
				}
				if ( $fail_request ) {
					return new WP_Error( 'http_request_failed', 'Synthetic unavailable transport' );
				}
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $charge ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}
		);
		$formatted = $request->send();
		$this->assertSame( $charge['balance_transaction'], $formatted['balance_transaction'] );
		$result = $validator->build( $formatted, 'ch_fixture', 'GBP', 3600, 'EUR' );
		require_once dirname( __DIR__, 3 ) . '/src/Internal/Service/CapturedPaymentSnapshotStore.php';
		require_once dirname( __DIR__, 3 ) . '/src/Internal/Service/CapturedPaymentSnapshotRecovery.php';
		$previous_storage = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		update_option( 'woocommerce_custom_orders_table_enabled', $hpos ? 'yes' : 'no' );
		$this->assertSame( $hpos, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		$order = new WC_Order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_currency( 'GBP' );
		$order->set_total( 36 );
		$order->update_meta_data( '_charge_id', 'ch_fixture' );
		$order->update_meta_data( '_wcpay_mode', $test_mode ? 'test' : 'prod' );
		$order->save();
		$factory = function ( $existing, $request_class, $id ) {
			return \WCPay\Core\Server\Request\Get_Charge::class === $request_class
				? new $request_class( $this->payments_api_client, $this->mock_http_client, $id ) : $existing;
		};
		add_filter( 'wcpay_create_request', $factory, 10, 3 );
		$mode_property = new ReflectionProperty( \WCPay\Core\Mode::class, 'test_mode' );
		$mode_property->setAccessible( true );
		$previous_mode = $mode_property->getValue( WC_Payments::mode() );
		try {
			// Simulate an already initialized ambient mode opposite to the order.
			$mode_property->setValue( WC_Payments::mode(), ! $test_mode );
			$this->assertSame( ! $test_mode, WC_Payments::mode()->is_test() );
			$store    = new \WCPay\Internal\Service\CapturedPaymentSnapshotStore( $validator );
			$recovery = new \WCPay\Internal\Service\CapturedPaymentSnapshotRecovery( $store );
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Observes the existing mode hook for request isolation.
			$ambient_mode = apply_filters( 'wcpay_test_mode', false );
			$this->assertSame( $result, $recovery->recover( $order, 'EUR', $expected_context ) );
			$this->assertSame( $ambient_mode, $observed_mode );
			$this->assertSame( $test_mode ? '1' : '0', $wire_mode );
			$this->assertSame( '1', $wire_context );
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Observes the existing mode hook for request isolation.
			$this->assertSame( $ambient_mode, apply_filters( 'wcpay_test_mode', false ) );
			$this->assertSame( $result, $store->read( $order, 'EUR', $expected_context ) );
			$fresh = wc_get_order( $order->get_id() );
			$this->assertEquals( 36, $fresh->get_total() );
			$this->assertSame( 'GBP', $fresh->get_currency() );
			$before = $fresh->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::CURRENT_META );
			$this->assertEquals( $expected_context, $before['context']['reporting_context'] ?? null );
			$reordered_context = array_reverse( $expected_context, true );
			$this->assertSame( $result, $store->read( $order, 'EUR', $reordered_context ) );
			$this->assertSame( $result, $store->record( $order, $charge, 'EUR', $reordered_context ) );
			$this->assertSame( $before, wc_get_order( $order->get_id() )->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::CURRENT_META ) );
			$other_context = array_merge( $expected_context, [ 'account_id' => 'acct_other' ] );
			$this->assertSame( 'snapshot_stale', $store->read( $order, 'EUR', $other_context )['reason'] );
			$other_charge = array_merge( $charge, [ 'wcpay_reporting_context' => $other_context ] );
			$this->assertSame( 'snapshot_context_mismatch', $store->record( $order, $other_charge, 'EUR', $other_context )['reason'] );
			$this->assertSame( $before, wc_get_order( $order->get_id() )->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::CURRENT_META ) );
			$this->assertSame( $result, $store->read( $order, 'EUR', $expected_context ) );
			$this->assertSame( $result, $recovery->recover( $order, 'EUR', $expected_context ) );
			$repeated = wc_get_order( $order->get_id() );
			$this->assertCount( 1, $repeated->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::REVISION_META, false ) );
			if ( null === $reason ) {
				$requests_before = $request_count;
				foreach ( [ [], array_merge( $expected_context, [ 'version' => 2 ] ), array_merge( $expected_context, [ 'account_id' => '' ] ), array_merge( $expected_context, [ 'site_id' => '123' ] ), array_merge( $expected_context, [ 'test_mode' => ! $test_mode ] ), array_merge( $expected_context, [ 'extra' => true ] ) ] as $invalid_context ) {
					$this->assertSame( 'charge_expected_context_invalid', ( $recovery->recover( $order, 'EUR', $invalid_context )['reason'] ?? null ) );
				}
				$this->assertSame( $requests_before, $request_count );
				$complete_charge = $charge;
				foreach ( [ null, [], array_merge( $expected_context, [ 'account_id' => 'acct_changed' ] ), array_merge( $expected_context, [ 'site_id' => 456 ] ), array_merge( $expected_context, [ 'test_mode' => ! $test_mode ] ), array_merge( $expected_context, [ 'version' => 2 ] ) ] as $wrong_context ) {
					$charge['wcpay_reporting_context'] = $wrong_context;
					$this->assertSame( 'charge_context_mismatch', ( $recovery->recover( $order, 'EUR', $expected_context )['reason'] ?? null ) );
					$this->assertSame( $before, wc_get_order( $order->get_id() )->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::CURRENT_META ) );
				}
				$charge             = $complete_charge;
				$charge['livemode'] = $test_mode;
				$this->assertSame( 'payment_mode_mismatch', $recovery->recover( $order, 'EUR', $expected_context )['reason'] );
				$this->assertSame( $result, $store->read( $order, 'EUR', $expected_context ) );
				$charge = $complete_charge;
				foreach ( [ 'unexpanded', 'missing_id', 'missing_time' ] as $missing ) {
					$charge = $complete_charge;
					if ( 'unexpanded' === $missing ) {
						$charge['balance_transaction'] = 'txn_fixture';
					} elseif ( 'missing_id' === $missing ) {
						unset( $charge['balance_transaction']['id'] );
					} else {
						unset( $charge['balance_transaction']['created'] );
					}
					$this->assertSame( 'incomplete', $recovery->recover( $order, 'EUR', $expected_context )['state'] );
					$this->assertSame( $result, $store->read( $order, 'EUR', $expected_context ) );
				}
				foreach ( [ 'amount', 'currency' ] as $changed_field ) {
					$conflict_order = new WC_Order();
					$conflict_order->set_payment_method( 'woocommerce_payments' );
					$conflict_order->set_currency( 'GBP' );
					$conflict_order->set_total( 36 );
					$conflict_order->update_meta_data( '_charge_id', 'ch_fixture' );
					$conflict_order->update_meta_data( '_wcpay_mode', $test_mode ? 'test' : 'prod' );
					$conflict_order->save();
					try {
						$store->record( $conflict_order, $complete_charge, 'EUR', $expected_context );
						$contradiction = $complete_charge;
						if ( 'amount' === $changed_field ) {
							$contradiction['balance_transaction']['amount'] = 4290;
							$contradiction['balance_transaction']['net']    = 4097;
							unset( $contradiction['balance_transaction']['created'] );
						} else {
							$contradiction['balance_transaction']['currency'] = 'usd';
						}
						$this->assertSame( 'inconsistent', $store->record( $conflict_order, $contradiction, 'EUR', $expected_context )['state'] );
					} finally {
						$conflict_order->delete( true );
					}
				}
				$charge = $complete_charge;
				$this->assertSame( $result, $recovery->recover( $order, 'EUR', $expected_context ) );
				$this->assertCount( 1, wc_get_order( $order->get_id() )->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::REVISION_META, false ) );

				$this->assertSame(
					[
						'state'  => 'incomplete',
						'reason' => 'reporting_currency_mismatch',
					],
					$recovery->recover( $order, 'USD', $expected_context )
				);
				$this->assertSame( $result, $store->read( $order, 'EUR', $expected_context ) );
				$this->assertSame( $result, $recovery->recover( $order, 'EUR', $expected_context ) );
			}
			$fail_request = true;
			$this->assertSame(
				[
					'state'  => 'incomplete',
					'reason' => 'charge_retrieval_failed',
				],
				$recovery->recover( $order, 'EUR', $expected_context )
			);
			$this->assertSame( $result, $store->read( $order, 'EUR', $expected_context ) );
			$after = wc_get_order( $order->get_id() );
			$this->assertSame( $before, $after->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::CURRENT_META ) );
			$fail_request = false;
			if ( null === $reason ) {
				foreach ( [ null, 'invalid' ] as $invalid_mode ) {
					$during_request = static function () use ( $order, $invalid_mode ) {
						$changed = wc_get_order( $order->get_id() );
						if ( null === $invalid_mode ) {
							$changed->delete_meta_data( '_wcpay_mode' );
						} else {
							$changed->update_meta_data( '_wcpay_mode', $invalid_mode );
						}
						$changed->save_meta_data();
					};
					$this->assertSame( 'payment_mode_missing', ( $recovery->recover( $order, 'EUR', $expected_context )['reason'] ?? null ) );
					$this->assertNotSame( 'ready', $store->read( $order, 'EUR', $expected_context )['state'] );
					$this->assertSame( $before, wc_get_order( $order->get_id() )->get_meta( \WCPay\Internal\Service\CapturedPaymentSnapshotStore::CURRENT_META ) );
					$during_request = null;
					$restore        = wc_get_order( $order->get_id() );
					$restore->update_meta_data( '_wcpay_mode', $test_mode ? 'test' : 'prod' );
					$restore->save_meta_data();
				}
				$during_request = static function () use ( $order, $test_mode ) {
					$changed = wc_get_order( $order->get_id() );
					$changed->update_meta_data( '_wcpay_mode', $test_mode ? 'prod' : 'test' );
					$changed->save_meta_data();
				};
				$this->assertSame( 'payment_mode_mismatch', $recovery->recover( $order, 'EUR', $expected_context )['reason'] );
				$this->assertNotSame( 'ready', $store->read( $order, 'EUR', $expected_context )['state'] );
				$during_request = null;
				$restore        = wc_get_order( $order->get_id() );
				$restore->update_meta_data( '_wcpay_mode', $test_mode ? 'test' : 'prod' );
				$restore->save_meta_data();
				$this->assertSame( $result, $recovery->recover( $order, 'EUR', $expected_context ) );
			}
			$during_request = static function () use ( $order ) {
				$changed = wc_get_order( $order->get_id() );
				$changed->update_meta_data( '_charge_id', 'ch_replaced' );
				$changed->save_meta_data();
			};
			$stale          = $recovery->recover( $order, 'EUR', $expected_context );
			$this->assertNotSame( 'ready', $stale['state'] );
			$this->assertNotSame( 'ready', $store->read( $order, 'EUR', $expected_context )['state'] );
			$this->assertSame( 'ch_replaced', wc_get_order( $order->get_id() )->get_meta( '_charge_id' ) );

		} finally {
			$mode_property->setValue( WC_Payments::mode(), $previous_mode );
			remove_filter( 'wcpay_create_request', $factory, 10 );
			$order->delete( true );
			update_option( 'woocommerce_custom_orders_table_enabled', $previous_storage );
		}

		if ( null !== $reason ) {
			$this->assertSame(
				[
					'state'  => 'incomplete',
					'reason' => $reason,
				],
				$result
			);
			return;
		}
		$this->assertSame( 'ready', $result['state'] );
		$this->assertSame( 4190, $result['amount'] );
		$this->assertSame( 3997, $result['net_amount'] );
	}

	/** Historical evidence cases delivered through the HTTP test boundary. */
	public function charge_snapshot_response_provider() {
		$cases  = [
			'complete historical event'            => [ 'complete', null ],
			'missing transaction identity'         => [ 'missing_id', 'balance_transaction_missing' ],
			'unexpanded transaction'               => [ 'unexpanded', 'balance_transaction_missing' ],
			'account currency differs from report' => [ 'wrong_currency', 'reporting_currency_mismatch' ],
		];
		$result = [];
		foreach ( $cases as $label => $case ) {
			foreach ( [ false, true ] as $test_mode ) {
				$suffix                               = $test_mode ? ' test' : ' production';
				$result[ $label . ' CPT' . $suffix ]  = array_merge( $case, [ false, $test_mode ] );
				$result[ $label . ' HPOS' . $suffix ] = array_merge( $case, [ true, $test_mode ] );
			}
		}
		return $result;
	}

	/**
	 * Test a successful fetch of a single transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_success() {
		$transaction_id = 'txn_231mdaism';

		$this->set_http_mock_response(
			200,
			[
				'id'        => $transaction_id,
				'type'      => 'charge',
				'charge_id' => 'ch_ji3djhabvh23',
			]
		);

		$transaction = $this->payments_api_client->get_transaction( $transaction_id );
		$this->assertEquals( $transaction_id, $transaction['id'] );
	}

	/**
	 * Test fetching of non existing transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_not_found() {
		$transaction_id = 'txn_231mdaism';
		$error_code     = 'resource_missing';
		$error_message  = 'No such balance transaction';

		$this->set_http_mock_response(
			404,
			[
				'error' => [
					'code'    => $error_code,
					'message' => $error_message,
				],
			]
		);
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "Error: $error_message" );

		$this->payments_api_client->get_transaction( $transaction_id );
	}

	/**
	 * Test that a fraud rule block error surfaces the ruleset results shipped with the error body.
	 */
	public function test_fraud_rule_block_error_carries_ruleset_results() {
		$this->set_http_mock_response(
			403,
			[
				'code'    => 'wcpay_blocked_by_fraud_rule',
				'message' => "There's a problem with this payment.",
				'data'    => [
					'status'          => 403,
					'ruleset_results' => [ 'avs_verification' => 'block' ],
				],
			]
		);

		try {
			$this->payments_api_client->get_transaction( 'txn_mock' );
			$this->fail( 'Expected Blocked_By_Fraud_Rules_Exception to be thrown.' );
		} catch ( \WCPay\Exceptions\Blocked_By_Fraud_Rules_Exception $e ) {
			$this->assertSame( 'wcpay_blocked_by_fraud_rule', $e->get_error_code() );
			$this->assertSame( [ 'avs_verification' => 'block' ], $e->get_ruleset_results() );
		}
	}

	/**
	 * Test creating a customer.
	 *
	 * @throws API_Exception
	 */
	public function test_create_customer_success() {
		$customer_data = [
			'name'        => 'Test Customer',
			'email'       => 'test.customer@example.com',
			'description' => 'Test Customer Description',
		];

		$this->set_http_mock_response(
			200,
			[
				'id'   => 'cus_test12345',
				'type' => 'customer',
			]
		);

		$customer_id = $this->payments_api_client->create_customer( $customer_data );

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test updating a customer.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_success() {
		$name        = 'Test Customer';
		$email       = 'test.customer@example.com';
		$description = 'Test Customer Description';

		// Mock the HTTP client manually so that we can assert against the request used.
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->validate_default_remote_request_params( $data, 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/customers/cus_test12345', 'POST' );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode'   => false,
						'name'        => 'Test Customer',
						'email'       => 'test.customer@example.com',
						'description' => 'Test Customer Description',
					]
				),
				true,
				false
			)
			->will(
				$this->returnValue(
					[
						'body'     => wp_json_encode(
							[
								'id'   => 'cus_test12345',
								'type' => 'customer',
							]
						),
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$this->payments_api_client->update_customer(
			'cus_test12345',
			[
				'name'        => $name,
				'email'       => $email,
				'description' => $description,
			]
		);
	}

	/**
	 * Test updating a customer with null customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_null_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( null );
	}

	/**
	 * Test updating a customer with an empty string customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_empty_string_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( '' );
	}

	/**
	 * Test updating a customer with an empty string customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_whitespace_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( ' ' );
	}

	/**
	 * Test getting initial onboarding data.
	 *
	 * @throws API_Exception
	 */
	public function test_get_onboarding_data() {
		update_option( 'woocommerce_store_id', 'test-store-id-12345' );

		$site_data = [
			'site_username' => 'admin',
			'site_locale'   => 'en_US',
		];

		$user_data = [
			'user_id'           => 1,
			'ip_address'        => '0.0.0.0',
			'browser'           => [
				'user_agent'       => 'Unit Test Agent/0.1.0',
				'accept_language'  => 'en-US,en;q=0.5',
				'content_language' => 'en-US,en;q=0.5',
			],
			'referer'           => 'https://example.com',
			'onboarding_source' => 'test_source',
		];

		$account_data = [];

		$actioned_notes = [
			'd' => 4,
			'e' => 5,
			'f' => 6,
		];

		$default_wc_pages = $this->create_woocommerce_default_pages();

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->validate_default_remote_request_params( $data, 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/onboarding/init', 'POST' );
						$this->assertSame( 'POST', $data['method'] );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode'                   => false,
						'return_url'                  => 'http://localhost',
						'site_data'                   => $site_data,
						'user_data'                   => $user_data,
						'account_data'                => $account_data,
						'actioned_notes'              => $actioned_notes,
						'create_live_account'         => true,
						'collect_payout_requirements' => false,
						'woocommerce_store_id'        => 'test-store-id-12345',
						'compatibility_data'          => $this->get_mock_compatibility_data(),
						'referral_code'               => null,
					]
				),
				true,
				true // get_onboarding_data should use user token auth.
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'url' => false ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		// Call the method under test.
		$result = $this->payments_api_client->get_onboarding_data(
			true,
			'http://localhost',
			$site_data,
			$user_data,
			$account_data,
			$actioned_notes
		);

		// Assert the response is correct.
		$this->assertEquals( [ 'url' => false ], $result );

		// Remove test pages created.
		$this->delete_test_posts( $default_wc_pages );
	}

	/**
	 * Test getting onboarding business types.
	 *
	 * @throws API_Exception
	 */
	public function test_get_onboarding_business_types() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->containsIdentical( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/onboarding/business_types?test_mode=0' ),
				null,
				true,
				true // get_onboarding_business_types should use user token auth.
			);

		$this->payments_api_client->get_onboarding_business_types();
	}

	public function test_get_link() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->validate_default_remote_request_params( $data, 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/links', 'POST' );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode' => false,
						'type'      => 'login_link',
						'param'     => 'some_other_param',
					]
				),
				true,
				true // get_link should use user token auth.
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'url' => 'https://login.url' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->get_link(
			[
				'type'  => 'login_link',
				'param' => 'some_other_param',
			]
		);

		$this->assertEquals( [ 'url' => 'https://login.url' ], $result );
	}

	public function test_get_currency_rates() {
		$currency_from = Currency_Code::UNITED_STATES_DOLLAR;

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				// Please note the use of the V2 API endpoint - `/transact/` instead of `/wcpay/`.
				$this->containsIdentical( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/transact/currency/rates?test_mode=0&currency_from=USD' ),
				null,
				true,
				false
			)->willReturn(
				[
					'body'     => wp_json_encode(
						[
							'GBP' => 0.75,
							'EUR' => 0.82,
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->get_currency_rates( $currency_from );

		$this->assertEquals(
			[
				'GBP' => 0.75,
				'EUR' => 0.82,
			],
			$result
		);
	}


	public function test_create_terminal_location_validation_array() {
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessageMatches( '~address.*required~i' );
		$this->payments_api_client->create_terminal_location( 'Example', '' );
	}

	public function test_create_terminal_location_validation_values() {
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessageMatches( '~address.*required~i' );
		$this->payments_api_client->create_terminal_location(
			'Example',
			[
				'country' => Country_Code::UNITED_STATES,
			]
		);
	}

	public function test_create_terminal_location_success() {
		$location = [
			'display_name' => 'Example',
			'address'      => [
				'country' => Country_Code::UNITED_STATES,
				'line1'   => 'Some Str. 2',
			],
			'metadata'     => [],
		];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $request ) {
						return 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/terminal/locations' === $request['url'] && 'POST' === $request['method'];
					}
				),
				$this->callback(
					function ( $body ) use ( $location ) {
						$flags = [ 'test_mode' => false ];

						return wp_json_encode( array_merge( $flags, $location ) ) === $body;
					}
				)
			)
			->will(
				$this->returnValue(
					[
						'body'     => wp_json_encode( $location ),
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$result = $this->payments_api_client->create_terminal_location( $location['display_name'], $location['address'] );
		// The returned value is an object, even though Stripe specifies an array.
		$result['metadata'] = (array) $result['metadata'];
		$this->assertSame( $location, $result );
	}

	public function test_delete_terminal_location_success() {
		$delete_location_response = [
			'id'      => 'tml_XXXXXXX',
			'object'  => 'terminal.deleted',
			'deleted' => true,
		];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->containsIdentical( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/terminal/locations/tml_XXXXXXX?test_mode=0' ),
				null,
				true,
				false
			)
			->will(
				$this->returnValue(
					[
						'body'     => wp_json_encode( $delete_location_response ),
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$this->assertSame(
			$this->payments_api_client->delete_terminal_location( 'tml_XXXXXXX' ),
			$delete_location_response
		);
	}

	/**
	 * @dataProvider data_get_intent_description
	 */
	public function test_get_intent_description( $order_id, $blog_id, $expected_intent_description ) {
		$this->mock_http_client
			->method( 'is_connected' )
			->willReturn( true );

		$this->mock_http_client
			->method( 'get_blog_id' )
			->willReturn( $blog_id );

		$actual_intent_description = PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'get_intent_description',
			[ $order_id ]
		);

		$this->assertSame( $expected_intent_description, $actual_intent_description );
	}

	/**
	 * Data provider for test_get_intent_description
	 */
	public function data_get_intent_description() {
		return [
			'no_order_id'               => [
				0,
				999,
				'Online Payment for example.org blog_id 999',
			],
			'no_blog_id'                => [
				100,
				null,
				'Online Payment for Order #100 for example.org',
			],
			'with_order_id_and_blog_id' => [
				100,
				999,
				'Online Payment for Order #100 for example.org blog_id 999',
			],
		];
	}

	/**
	 * Test a successful fetch of a single invoice.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_invoice_success() {
		$invoice_id = 'in_test_invoice';

		$this->set_http_mock_response(
			200,
			[
				'id'     => $invoice_id,
				'object' => 'invoice',
			]
		);

		$invoice = $this->payments_api_client->get_invoice( $invoice_id );
		$this->assertEquals( $invoice_id, $invoice['id'] );
	}

	/**
	 * Test a successful call to cancel subscription.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_cancel_subscription() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->containsIdentical( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/subscriptions/sub_test?test_mode=0' ),
				null,
				true,
				false
			)
			->will(
				$this->returnValue(
					[
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'body'     => wp_json_encode(
							[
								'id'     => 'sub_test',
								'object' => 'subscription',
							]
						),
					]
				)
			);

		$result = $this->payments_api_client->cancel_subscription( 'sub_test' );
		$this->assertSame( 'sub_test', $result['id'] );
		$this->assertSame( 'subscription', $result['object'] );
	}

	/**
	 * Test redacting request params.
	 *
	 * @dataProvider redacting_params_data
	 * @throws Exception - In the event of test failure.
	 */
	public function test_redacting_params( $request_arguments, $logger_num_calls ) {
		$mock_logger          = $this->getMockBuilder( 'WC_Logger' )
			->setMethods( [ 'log' ] )
			->getMock();
		$mock_internal_logger = new Logger( $mock_logger, WC_Payments::mode() );
		wcpay_get_test_container()->replace( Logger::class, $mock_internal_logger );

		WC_Payments::mode()->dev();

		$mock_logger
			->expects( $this->exactly( $logger_num_calls ) )
			->method( 'log' )
			->with(
				$this->anything(),
				$this->callback(
					function ( $message ) {
						return false === strpos( $message, 'some-secret' );
					}
				)
			);

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					[
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'body'     => wp_json_encode(
							[
								'status' => true,
							]
						),
					]
				)
			);

		$reflection     = new ReflectionClass( $this->payments_api_client );
		$request_method = $reflection->getMethod( 'request' );
		$request_method->setAccessible( true );
		$request_method->invokeArgs( $this->payments_api_client, $request_arguments );
		$request_method->setAccessible( false );

		// clean up.
		WC_Payments::mode()->live();
		wcpay_get_test_container()->reset_all_replacements();
	}

	/**
	 * Data provider for test_redacting_params
	 */
	public function redacting_params_data() {
		return [
			'delete' => [
				[ [ 'client_secret' => 'some-secret' ], 'abc', 'DELETE' ],
				2,
			],
			'get'    => [
				[ [ 'client_secret' => 'some-secret' ], 'abc', 'GET' ],
				2,
			],
			'post'   => [
				[ [ 'client_secret' => 'some-secret' ], 'abc', 'POST' ],
				2,
			],
		];
	}

	/**
	 * Test that data in GET request query parameters is redacted in the log context URL.
	 *
	 * Regression test for WOOPMNT-5954: the raw URL (containing e.g. email, name) was
	 * passed directly into the log context, while the human-readable log message correctly
	 * used the redacted URL.
	 */
	public function test_get_request_url_is_redacted_in_log_context() {
		$mock_logger          = $this->getMockBuilder( 'WC_Logger' )
			->setMethods( [ 'log' ] )
			->getMock();
		$mock_internal_logger = new Logger( $mock_logger, WC_Payments::mode() );
		wcpay_get_test_container()->replace( Logger::class, $mock_internal_logger );

		WC_Payments::mode()->dev();

		$captured_context = null;
		$mock_logger
			->expects( $this->atLeastOnce() )
			->method( 'log' )
			->willReturnCallback(
				function ( $level, $message, $context ) use ( &$captured_context ) {
					if ( false !== strpos( $message, 'API REQUEST' ) ) {
						$captured_context = $context;
					}
				}
			);

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					[
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'body'     => wp_json_encode( [ 'status' => true ] ),
					]
				)
			);

		$reflection     = new ReflectionClass( $this->payments_api_client );
		$request_method = $reflection->getMethod( 'request' );
		$request_method->setAccessible( true );
		$request_method->invokeArgs(
			$this->payments_api_client,
			[ [ 'email' => 'customer@example.com' ], 'abc', 'GET' ]
		);
		$request_method->setAccessible( false );

		$this->assertNotNull( $captured_context, 'API REQUEST log entry was not captured.' );
		$this->assertArrayHasKey( 'request', $captured_context );
		$this->assertArrayHasKey( 'url', $captured_context['request'] );
		$this->assertStringNotContainsString(
			'customer@example.com',
			$captured_context['request']['url'],
			'Raw email address must not appear in the log context URL.'
		);

		// clean up.
		WC_Payments::mode()->live();
		wcpay_get_test_container()->reset_all_replacements();
	}

	/**
	 * Test a sucessful fetch of disputes summary
	 *
	 * @throws Exception
	 */
	public function test_get_disputes_summary_success() {
		$this->set_http_mock_response(
			200,
			[
				'data' => [
					'count' => 12,
				],
			]
		);

		$disputes_summary = $this->payments_api_client->get_disputes_summary();
		$this->assertSame( 12, $disputes_summary['data']['count'] );
	}

	public function test_get_woopay_eligibility_success() {
		$this->set_http_mock_response(
			200,
			[
				'platform_checkout_eligible' => true,
			]
		);

		$response = $this->payments_api_client->get_woopay_eligibility();
		$this->assertTrue( $response['platform_checkout_eligible'] );
	}

	/**
	 * Test a sucessful fetch of documents summary
	 *
	 * @throws Exception
	 */
	public function test_get_documents_summary_success() {
		$this->set_http_mock_response(
			200,
			[
				'data' => [
					'count' => 12,
				],
			]
		);

		$documents_summary = $this->payments_api_client->get_documents_summary();
		$this->assertSame( 12, $documents_summary['data']['count'] );
	}

	/**
	 * Test a successful fetch of a document
	 *
	 * @throws Exception
	 */
	public function test_get_document_success() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $request ) {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/documents/someDocument?test_mode=0', $request['url'] );
						$this->assertSame( 'GET', $request['method'] );
						return true;
					}
				)
			)
			->will(
				$this->returnValue(
					[
						'headers'  => [ 'content-type' => 'text/html' ],
						'body'     => '<html><body>Document</body></html>',
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$documents_summary = $this->payments_api_client->get_document( 'someDocument' );
		$this->assertSame( '<html><body>Document</body></html>', $documents_summary['body'] );
		$this->assertSame( 'text/html', $documents_summary['headers']['content-type'] );
	}

	/**
	 * Test fetch of a document that errors
	 *
	 * @throws Exception
	 */
	public function test_get_document_error() {
		$this->set_http_mock_response(
			404,
			[
				'code'    => 'wcpay_document_not_found',
				'message' => 'Document not found',
				'data'    => [ 'status' => 404 ],
			]
		);

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Error: Document not found' );

		$this->payments_api_client->get_document( 'someDocument' );
	}

	/**
	 * Test that API client will retry request in case of network error
	 *
	 * POST calls have `Idempotency-Key` set in the `request`, thus are
	 * possible to retry.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_post_on_network_failure() {
		$this->mock_http_client
			->expects( $this->exactly( 4 ) )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'error' ] ),
					'response' => [
						'code'    => 0,
						'message' => 'Unknown network error',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will retry request in case of network error
	 * indiciated by Connection_Exception.
	 *
	 * POST calls have `Idempotency-Key` set in the `request`, thus are
	 * possible to retry.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_post_on_network_failure_exception() {
		$this->mock_http_client
			->expects( $this->exactly( 4 ) )
			->method( 'remote_request' )
			->willThrowException(
				new Connection_Exception( 'HTTP request failed', 'wcpay_http_request_failed', 500 )
			);

		$this->expectException( Connection_Exception::class );

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will retry request in case of network error
	 * and stop on success.
	 *
	 * POST calls have `Idempotency-Key` set in the `request`, thus are
	 * possible to retry.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_post_on_network_failure_exception_and_stops_on_success() {
		$this->mock_http_client
			->expects( $this->exactly( 3 ) )
			->method( 'remote_request' )
			->willReturnOnConsecutiveCalls(
				$this->throwException(
					new Connection_Exception( 'HTTP request failed', 'wcpay_http_request_failed', 500 )
				),
				$this->throwException(
					new Connection_Exception( 'HTTP request failed', 'wcpay_http_request_failed', 500 )
				),
				[
					'body'     => wp_json_encode( [ 'result' => 'success' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will not retry if connection exception indicates there
	 * was a response.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_doesnt_retry_on_other_exceptions() {
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->willThrowException(
				new Exception( 'Random exception' )
			);

		$this->expectException( Exception::class );

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will retry request in case of network error with
	 * Idempotency-Key header
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_get_with_idempotency_header_on_network_failure() {
		$this->mock_http_client
			->expects( $this->exactly( 4 ) )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'error' ] ),
					'response' => [
						'code'    => 0,
						'message' => 'Unknown network error',
					],
				]
			);

		$callable = function ( $headers ) {
			$headers['Idempotency-Key'] = 'ik_42';
			return $headers;
		};

		add_filter(
			'wcpay_api_request_headers',
			$callable,
			10,
			2
		);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'GET' ]
		);

		remove_filter(
			'wcpay_api_request_headers',
			$callable,
			10
		);
	}

	/**
	 * Test that API client won't retry GET request without Idemptency-Key header.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_doesnt_retry_get_without_idempotency_header_on_network_failure() {
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'error' ] ),
					'response' => [
						'code'    => 0,
						'message' => 'Unknown network error',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'GET' ]
		);
	}

	public function test_update_compatibility_data() {
		// Arrange: Set expectation and return for remote_request.
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'success' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		// Act: Get the result of updating the data.
		$result = $this->payments_api_client->update_compatibility_data(
			[
				'woocommerce_core_version' => WC_VERSION,
			]
		);

		// Assert: Confirm we get the expected response.
		$this->assertSame( 'success', $result['result'] );
	}

	public function test_get_readers_charge_summary() {
		$transaction_id = uniqid( 'trx_' );
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode(
						[
							'result' => 'success',
							'data'   => [
								(object) [
									'reader_id' => 'reader_1',
									'count'     => 1,
									'status'    => 'active',
									'fee'       => [
										'amount'   => 100,
										'currency' => Currency_Code::UNITED_STATES_DOLLAR,
									],
								],
							],
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->get_readers_charge_summary( '2024-01-01', $transaction_id );
		$this->assertSame( 1, $result['data'][0]['count'] );
	}


	public function test_get_tracking_info() {
		$expect = [ 'hosting-provider' => 'test' ];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( $expect ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->get_tracking_info();

		$this->assertEquals( $expect, $result );
	}

	public function test_throws_api_merchant_exception() {
		$mock_response                  = [];
		$mock_response['error']['code'] = 'card_declined';
		$mock_response['error']['payment_intent']['charges']['data'][0]['outcome']['seller_message'] = 'Bank declined';
		$this->set_http_mock_response(
			401,
			$mock_response
		);

		try {
			// This is a dummy call to trigger the response so that our test can validate the exception.
			$this->payments_api_client->create_subscription();
		} catch ( API_Merchant_Exception $e ) {
			$this->assertSame( 'card_declined', $e->get_error_code() );
			$this->assertSame( 'Bank declined', $e->get_merchant_message() );
		}
	}

	public function test_api_merchant_exception_includes_payment_intent_id() {
		$mock_response                                  = [];
		$mock_response['error']['code']                 = 'card_declined';
		$mock_response['error']['payment_intent']['id'] = 'pi_mock_failed_123';
		$mock_response['error']['payment_intent']['charges']['data'][0]['outcome']['seller_message'] = 'Bank declined';
		$this->set_http_mock_response(
			401,
			$mock_response
		);

		try {
			$this->payments_api_client->create_subscription();
			$this->fail( 'Expected API_Merchant_Exception was not thrown.' );
		} catch ( API_Merchant_Exception $e ) {
			$this->assertSame( 'pi_mock_failed_123', $e->get_intent_id() );
		}
	}

	public function test_api_exception_includes_payment_intent_id() {
		$mock_response                                  = [];
		$mock_response['error']['code']                 = 'incorrect_cvc';
		$mock_response['error']['type']                 = 'card_error';
		$mock_response['error']['payment_intent']['id'] = 'pi_mock_failed_456';
		$this->set_http_mock_response(
			402,
			$mock_response
		);

		try {
			$this->payments_api_client->create_subscription();
			$this->fail( 'Expected API_Exception was not thrown.' );
		} catch ( API_Exception $e ) {
			$this->assertSame( 'pi_mock_failed_456', $e->get_intent_id() );
		}
	}

	public function test_api_exception_intent_id_is_null_when_no_payment_intent() {
		$mock_response                     = [];
		$mock_response['error']['code']    = 'resource_missing';
		$mock_response['error']['message'] = 'No such payment_method: pm_123.';
		$this->set_http_mock_response(
			400,
			$mock_response
		);

		try {
			$this->payments_api_client->create_subscription();
			$this->fail( 'Expected API_Exception was not thrown.' );
		} catch ( API_Exception $e ) {
			$this->assertNull( $e->get_intent_id() );
		}
	}

	/**
	 * Test sending store setup data.
	 */
	public function test_send_store_setup() {
		$store_setup_data = [
			'store_name'    => 'Test Store',
			'store_url'     => 'https://example.com',
			'store_country' => 'US',
		];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->validate_default_remote_request_params( $data, 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/accounts/store_setup', 'POST', false );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode' => false,
						'snapshot'  => [
							'store_name'    => 'Test Store',
							'store_url'     => 'https://example.com',
							'store_country' => 'US',
						],
					]
				),
				true,
				false
			)
			->willReturn(
				[
					'body'     => wp_json_encode(
						[
							'result' => 'success',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->send_store_setup( $store_setup_data );

		$this->assertSame( 'success', $result['result'] );
	}

	/**
	 * Set up http mock response.
	 *
	 * @param int $status_code status code for the mocked response.
	 * @param array $body body for the mocked response.
	 * @param array $headers headers for the mocked response.
	 * @param array $cookies cookies to be used in the mocked response.
	 */
	private function set_http_mock_response( $status_code, $body = [], $headers = [], $cookies = [] ) {
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					[
						'headers'  => $headers,
						'body'     => wp_json_encode( $body ),
						'response' => [
							'code'    => $status_code,
							'message' => 'OK',
						],
						'cookies'  => $cookies,
						'filename' => null,
					]
				)
			);
	}

	/**
	 * Mock/validate default remote HTTP Params
	 *
	 * @param array $data The request args.
	 * @param string $url The expected URL.
	 * @param string $method The expected HTTP method.
	 * @param bool $blocking Whether the request is blocking. Default true.
	 */
	private function validate_default_remote_request_params( $data, $url, $method, $blocking = true ) {
		$this->assertIsArray( $data );
		$this->assertCount( 6, $data );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertSame( $url, $data['url'] );
		$this->assertNotFalse( filter_var( $data['url'], FILTER_VALIDATE_URL ) );
		$this->assertArrayHasKey( 'method', $data );
		$this->assertSame( $method, $data['method'] );
		$this->assertArrayHasKey( 'headers', $data );
		$this->assertArrayHasKey( 'Idempotency-Key', $data['headers'] );
		$this->assertNotEmpty( $data['headers']['Idempotency-Key'] );
		$this->assertArrayHasKey( 'User-Agent', $data['headers'] );
		$this->assertNotEmpty( $data['headers']['User-Agent'] );
		$this->assertArrayHasKey( 'Content-Type', $data['headers'] );
		$this->assertSame( 'application/json; charset=utf-8', $data['headers']['Content-Type'] );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertArrayHasKey( 'timeout', $data );
		$this->assertSame( 70, $data['timeout'] );
		$this->assertArrayHasKey( 'connect_timeout', $data );
		$this->assertSame( 70, $data['connect_timeout'] );
		$this->assertArrayHasKey( 'blocking', $data );
		$this->assertSame( $blocking, $data['blocking'] );
	}

	/**
	 * Returns the mock compatibility data.
	 *
	 * @param array $args If any values need to be overridden, the values can be added here.
	 *
	 * @return array
	 */
	private function get_mock_compatibility_data( array $args = [] ): array {
		return array_merge(
			[
				'woopayments_version'    => WCPAY_VERSION_NUMBER,
				'woocommerce_version'    => WC_VERSION,
				'woocommerce_permalinks' => get_option( 'woocommerce_permalinks' ),
				'woocommerce_shop'       => get_permalink( wc_get_page_id( 'shop' ) ),
				'woocommerce_cart'       => get_permalink( wc_get_page_id( 'cart' ) ),
				'woocommerce_checkout'   => get_permalink( wc_get_page_id( 'checkout' ) ),
				'blog_theme'             => 'default',
				'active_plugins'         => [],
				'post_types_count'       => [
					'post'       => 0,
					'page'       => 4,
					'attachment' => 0,
					'product'    => 0,
				],
			],
			$args
		);
	}

	/**
	 * Creates the default WooCommerce pages for test purposes.
	 *
	 * @return array Array of post IDs that were created.
	 */
	private function create_woocommerce_default_pages(): array {
		// Note: Inspired by WC_Install::create_pages().

		$pages = [
			'shop'           => [
				'name'    => 'shop',
				'title'   => 'Shop',
				'content' => '',
			],
			'cart'           => [
				'name'    => 'cart',
				'title'   => 'Cart',
				'content' => '',
			],
			'checkout'       => [
				'name'    => 'checkout',
				'title'   => 'Checkout',
				'content' => '',
			],
			'myaccount'      => [
				'name'    => 'my-account',
				'title'   => 'My account',
				'content' => '',
			],
			'refund_returns' => [
				'name'        => 'refund_returns',
				'title'       => 'Refund and Returns Policy',
				'content'     => '',
				'post_status' => 'draft',
			],
		];

		$page_ids = [];
		foreach ( $pages as $key => $page ) {
			$page_ids[] = wc_create_page(
				esc_sql( $page['name'] ),
				'woocommerce_' . $key . '_page_id',
				$page['title'],
				$page['content'],
				'',
				! empty( $page['post_status'] ) ? $page['post_status'] : 'publish'
			);
		}

		return $page_ids;
	}

	/**
	 * Test the determine_suggested_product_type method with various scenarios.
	 *
	 * @dataProvider data_determine_suggested_product_type
	 */
	public function test_determine_suggested_product_type( $order_items, $expected_product_type, $evidence_types_flag_enabled = true ) {
		// Set the feature flag option.
		update_option( WC_Payments_Features::DISPUTE_ADDITIONAL_EVIDENCE_TYPES, $evidence_types_flag_enabled ? '1' : '0' );

		// Create a mock order.
		$mock_order = $this->getMockBuilder( 'WC_Order' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_items' ] )
			->getMock();

		$mock_order->method( 'get_items' )->willReturn( $order_items );

		// Use reflection to call the private method.
		$reflection = new ReflectionClass( $this->payments_api_client );
		$method     = $reflection->getMethod( 'determine_suggested_product_type' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->payments_api_client, $mock_order );

		$this->assertEquals( $expected_product_type, $result );
	}

	/**
	 * Test updating a dispute with or without Visa compliance flag based on dispute reason.
	 *
	 * @dataProvider data_update_dispute_visa_compliance
	 * @throws API_Exception
	 */
	public function test_update_dispute_visa_compliance_flag( $dispute_reason, $should_have_flag ) {
		$dispute_id = 'dp_test123';
		$evidence   = [
			'product_description'    => 'Product description',
			'customer_name'          => 'Customer Name',
			'uncategorized_text'     => 'Additional details',
			'customer_email_address' => 'customer@example.com',
			'customer_purchase_ip'   => '1.2.3.4',
			'billing_address'        => '123 Main St',
			'receipt'                => 'file_123',
			'customer_signature'     => 'file_456',
			'shipping_documentation' => 'file_789',
		];
		$submit     = true;
		$metadata   = [ 'order_id' => '123' ];

		// Mock the dispute cache to avoid errors.
		$mock_cache = $this->createMock( \WCPay\Database_Cache::class );
		$mock_cache->method( 'delete_dispute_caches' )
			->willReturn( null );

		// Replace the database cache in the container.
		wcpay_get_test_container()->replace( \WCPay\Database_Cache::class, $mock_cache );

		// Mock the HTTP client to first return dispute details, then accept the update.
		$this->mock_http_client
			->expects( $this->exactly( 2 ) )
			->method( 'remote_request' )
			->willReturnCallback(
				function ( $data, $body ) use ( $dispute_id, $evidence, $metadata, $dispute_reason, $should_have_flag ) {
					// First call: GET dispute to check the reason.
					if ( strpos( $data['url'], '/disputes/' . $dispute_id ) !== false && 'GET' === $data['method'] ) {
						return [
							'body'     => wp_json_encode(
								[
									'id'     => $dispute_id,
									'charge' => 'ch_test123',
									'reason' => $dispute_reason,
									'status' => 'needs_response',
								]
							),
							'response' => [
								'code'    => 200,
								'message' => 'OK',
							],
						];
					}

					// Second call: POST to update the dispute.
					if ( strpos( $data['url'], '/disputes/' . $dispute_id ) !== false && 'POST' === $data['method'] ) {
						// Validate the request parameters.
						$this->validate_default_remote_request_params(
							$data,
							'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/disputes/' . $dispute_id,
							'POST'
						);

						// Validate the body contains or doesn't contain the Visa compliance flag.
						$decoded = json_decode( $body, true );

						// Verify the standard evidence is present.
						$this->assertArrayHasKey( 'evidence', $decoded );
						// Verify the Visa compliance flag presence based on dispute reason.
						if ( $should_have_flag ) {
							$this->assertArrayHasKey( 'enhanced_evidence', $decoded['evidence'] );
							$this->assertArrayHasKey( 'visa_compliance', $decoded['evidence']['enhanced_evidence'] );
							$this->assertArrayHasKey( 'fee_acknowledged', $decoded['evidence']['enhanced_evidence']['visa_compliance'] );
							$this->assertEquals( 'true', $decoded['evidence']['enhanced_evidence']['visa_compliance']['fee_acknowledged'] );
							$evidence_without_flag = $decoded['evidence'];
							unset( $evidence_without_flag['enhanced_evidence'] );
							$this->assertEquals( $evidence, $evidence_without_flag );
						} else {
							// Evidence shouldn't be modified.
							$this->assertEquals( $evidence, $decoded['evidence'] );
						}

						// Verify the submit flag is set.
						$this->assertArrayHasKey( 'submit', $decoded );
						$this->assertTrue( $decoded['submit'] );

						// Verify the metadata is present.
						$this->assertArrayHasKey( 'metadata', $decoded );
						$this->assertEquals( $metadata, $decoded['metadata'] );

						return [
							'body'     => wp_json_encode(
								[
									'id'       => $dispute_id,
									'charge'   => 'ch_test123',
									'reason'   => $dispute_reason,
									'status'   => 'needs_response',
									'evidence' => $evidence,
								]
							),
							'response' => [
								'code'    => 200,
								'message' => 'OK',
							],
						];
					}

					return [
						'body'     => wp_json_encode( [] ),
						'response' => [
							'code'    => 404,
							'message' => 'Not Found',
						],
					];
				}
			);

		// Call the method under test.
		$result = $this->payments_api_client->update_dispute( $dispute_id, $evidence, $submit, $metadata );

		// Assert the response is correct.
		$this->assertEquals( $dispute_id, $result['id'] );

		// Clean up.
		wcpay_get_test_container()->reset_all_replacements();
	}

	/**
	 * Data provider for test_update_dispute_visa_compliance_flag.
	 */
	public function data_update_dispute_visa_compliance() {
		return [
			'noncompliant_dispute_should_have_flag'   => [
				'dispute_reason'   => 'noncompliant',
				'should_have_flag' => true,
			],
			'fraudulent_dispute_should_not_have_flag' => [
				'dispute_reason'   => 'fraudulent',
				'should_have_flag' => false,
			],
			'product_unacceptable_dispute_should_not_have_flag' => [
				'dispute_reason'   => 'product_unacceptable',
				'should_have_flag' => false,
			],
		];
	}

	/**
	 * Data provider for test_determine_suggested_product_type.
	 */
	public function data_determine_suggested_product_type() {
		return [
			'empty_order'                              => [
				'order_items'           => [],
				'expected_product_type' => 'physical_product',
			],
			'single_physical_product'                  => [
				'order_items'           => [
					$this->create_mock_order_item_product( false ), // not virtual.
				],
				'expected_product_type' => 'physical_product',
			],
			'single_virtual_product'                   => [
				'order_items'           => [
					$this->create_mock_order_item_product( true ), // virtual.
				],
				'expected_product_type' => 'digital_product_or_service',
			],
			'multiple_products_mixed'                  => [
				'order_items'           => [
					$this->create_mock_order_item_product( false ), // physical.
					$this->create_mock_order_item_product( true ),  // virtual.
				],
				'expected_product_type' => 'multiple',
			],
			'multiple_physical_products'               => [
				'order_items'           => [
					$this->create_mock_order_item_product( false ), // physical.
					$this->create_mock_order_item_product( false ), // physical.
				],
				'expected_product_type' => 'multiple',
			],
			'multiple_virtual_products'                => [
				'order_items'           => [
					$this->create_mock_order_item_product( true ), // virtual.
					$this->create_mock_order_item_product( true ), // virtual.
				],
				'expected_product_type' => 'multiple',
			],
			'order_with_non_product_items'             => [
				'order_items'           => [
					$this->create_mock_order_item_product( true ), // virtual product.
					$this->create_mock_order_item_shipping(), // shipping item (not a product).
				],
				'expected_product_type' => 'digital_product_or_service',
			],
			'order_with_invalid_product'               => [
				'order_items'           => [
					$this->create_mock_order_item_product( true, false ), // virtual but invalid product.
				],
				'expected_product_type' => 'physical_product',
			],
			'single_booking_product'                   => [
				'order_items'                 => [
					$this->create_mock_order_item_product( true, true, 'booking' ), // booking product.
				],
				'expected_product_type'       => 'booking_reservation',
				'evidence_types_flag_enabled' => true,
			],
			'single_booking_product_flag_off'          => [
				'order_items'                 => [
					$this->create_mock_order_item_product( true, true, 'booking' ), // booking product (virtual).
				],
				'expected_product_type'       => 'digital_product_or_service', // Falls back to virtual detection.
				'evidence_types_flag_enabled' => false,
			],
			'single_booking_product_physical_flag_off' => [
				'order_items'                 => [
					$this->create_mock_order_item_product( false, true, 'booking' ), // booking product (not virtual).
				],
				'expected_product_type'       => 'physical_product', // Falls back to physical detection.
				'evidence_types_flag_enabled' => false,
			],
			'multiple_booking_products'                => [
				'order_items'                 => [
					$this->create_mock_order_item_product( true, true, 'booking' ), // booking.
					$this->create_mock_order_item_product( true, true, 'booking' ), // booking.
				],
				'expected_product_type'       => 'multiple',
				'evidence_types_flag_enabled' => true,
			],
			'booking_physical_mixed'                   => [
				'order_items'                 => [
					$this->create_mock_order_item_product( true, true, 'booking' ), // booking.
					$this->create_mock_order_item_product( false, true, 'simple' ), // physical.
				],
				'expected_product_type'       => 'multiple',
				'evidence_types_flag_enabled' => true,
			],
		];
	}

	/**
	 * Create a mock order item product for testing.
	 *
	 * @param bool   $is_virtual Whether the product is virtual.
	 * @param bool   $is_valid Whether the product is valid (can be retrieved).
	 * @param string $product_type The product type (e.g., 'simple', 'booking', 'variable').
	 * @return MockObject
	 */
	private function create_mock_order_item_product( $is_virtual = false, $is_valid = true, $product_type = 'simple' ) {
		$mock_product = $this->getMockBuilder( 'WC_Product' )
			->disableOriginalConstructor()
			->setMethods( [ 'is_virtual', 'get_type' ] )
			->getMock();

		$mock_product->method( 'is_virtual' )->willReturn( $is_virtual );
		$mock_product->method( 'get_type' )->willReturn( $product_type );

		$mock_order_item = $this->getMockBuilder( 'WC_Order_Item_Product' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_product' ] )
			->getMock();

		$mock_order_item->method( 'get_product' )->willReturn( $is_valid ? $mock_product : false );

		return $mock_order_item;
	}

	/**
	 * Create a mock order item that is not a product (e.g., shipping).
	 *
	 * @return MockObject
	 */
	private function create_mock_order_item_shipping() {
		$mock_order_item = $this->getMockBuilder( 'WC_Order_Item_Shipping' )
			->disableOriginalConstructor()
			->getMock();

		return $mock_order_item;
	}

	/**
	 * Delete test posts that were created during a unit test.
	 *
	 * @param array $post_ids Array of post IDs to delete.
	 */
	private function delete_test_posts( array $post_ids = [] ) {
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}

	/**
	 * Tests that get_dispute_summary returns correct data for a valid dispute ID.
	 */
	public function test_get_dispute_summary_success(): void {
		$dispute_id = 'dp_123456789';

		// Mock the expected response from the API.
		$expected_response = [
			'id'              => $dispute_id,
			'fee'             => 1500,
			'network_cost'    => 500,
			'currency'        => 'usd',
			'disputed_amount' => 5000,
			'exchange_rate'   => 1,
		];

		$this->set_http_mock_response( 200, $expected_response );

		// Act: Call the method.
		$result = $this->payments_api_client->get_dispute_summary( $dispute_id );

		// Assert: Check that the response matches the expected data.
		$this->assertEquals( $expected_response, $result );
	}

	/**
	 * Tests that get_dispute_summary throws exception for invalid dispute ID.
	 */
	public function test_get_dispute_summary_invalid_id(): void {
		$dispute_id = 'invalid_id_with_special_chars!';

		// Expect an API exception to be thrown.
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Route param validation failed.' );

		// Act: Call the method with invalid ID.
		$this->payments_api_client->get_dispute_summary( $dispute_id );
	}

	/**
	 * Tests that get_dispute_summary handles API errors correctly.
	 */
	public function test_get_dispute_summary_api_error(): void {
		$dispute_id = 'dp_123456789';

		// Mock an API error response.
		$error_response = [
			'error' => [
				'code'    => 'resource_missing',
				'message' => 'No such dispute',
			],
		];

		$this->set_http_mock_response( 404, $error_response );

		// Expect an API exception to be thrown.
		$this->expectException( API_Exception::class );

		// Act: Call the method.
		$this->payments_api_client->get_dispute_summary( $dispute_id );
	}

	public function test_request_uses_caller_supplied_idempotency_key_and_strips_it_from_body(): void {
		$captured_headers = [];
		$captured_body    = null;

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $args ) use ( &$captured_headers ) {
						$captured_headers = $args['headers'];
						return true;
					}
				),
				$this->callback(
					function ( $body ) use ( &$captured_body ) {
						$captured_body = $body;
						return true;
					}
				)
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'id' => 're_1' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[
				[
					'charge'          => 'ch_1',
					'idempotency_key' => 'ik_agent_123',
				],
				'refunds',
				'POST',
			]
		);

		$this->assertSame( 'ik_agent_123', $captured_headers['Idempotency-Key'] );
		$this->assertStringNotContainsString( 'idempotency_key', (string) $captured_body );
	}

	public function test_request_auto_generates_idempotency_key_when_caller_omits_it(): void {
		$captured_headers = [];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $args ) use ( &$captured_headers ) {
						$captured_headers = $args['headers'];
						return true;
					}
				),
				$this->callback(
					function () {
						return true;
					}
				)
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'id' => 're_1' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[
				[ 'charge' => 'ch_1' ],
				'refunds',
				'POST',
			]
		);

		$this->assertArrayHasKey( 'Idempotency-Key', $captured_headers );
		$this->assertIsString( $captured_headers['Idempotency-Key'] );
		$this->assertNotEmpty( $captured_headers['Idempotency-Key'] );
	}

	public function test_upload_evidence_file_contents_posts_base64_payload_to_files_api(): void {
		$captured_url  = '';
		$captured_body = null;

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ) use ( &$captured_url ): bool {
						$captured_url = $data['url'];
						return true;
					}
				),
				$this->callback(
					function ( $body ) use ( &$captured_body ): bool {
						$captured_body = $body;
						return true;
					}
				)
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'id' => 'file_1' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->upload_evidence_file_contents(
			'YmFzZTY0ZGF0YQ==',
			'receipt.pdf',
			'application/pdf',
			'dispute_evidence',
			false
		);

		$this->assertSame( [ 'id' => 'file_1' ], $result );
		$this->assertStringContainsString( '/wcpay/files', $captured_url );
		$this->assertStringContainsString( 'YmFzZTY0ZGF0YQ==', (string) $captured_body );
		$this->assertStringContainsString( 'receipt.pdf', (string) $captured_body );
		$this->assertStringContainsString( 'dispute_evidence', (string) $captured_body );
	}

	/** Typed retrieval context survives deserialization without entering shopper JSON. */
	public function test_deserialize_payment_intention_reporting_context() {
		$source  = [
			'id'            => 'pi_context',
			'amount'        => 3600,
			'currency'      => 'gbp',
			'created'       => 1700000000,
			'status'        => 'succeeded',
			'client_secret' => 'fixture',
			'metadata'      => [],
			'charges'       => [
				'total_count' => 0,
				'data'        => [],
			],
		];
		$context = [
			'version'    => 1,
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'test_mode'  => false,
		];
		foreach ( [ null, $context, array_merge( $context, [ 'test_mode' => true ] ), array_merge( $context, [ 'site_id' => '123' ] ), array_merge( $context, [ 'version' => 2 ] ), array_merge( $context, [ 'test_mode' => 0 ] ) ] as $value ) {
			$source['wcpay_reporting_context'] = $value;
			$intent                            = $this->payments_api_client->deserialize_payment_intention_object_from_array( $source );
			$expected                          = is_array( $value ) && 1 === $value['version'] && is_int( $value['site_id'] ) && is_bool( $value['test_mode'] ) ? $value : null;
			$this->assertSame( $expected, $intent->get_reporting_context() );
			$this->assertArrayNotHasKey( 'wcpay_reporting_context', $intent->jsonSerialize() );
		}
	}

	/**
	 * A charge can have more than one dispute, so the additive `disputes` array
	 * on the server response must survive deserialization onto the charge model.
	 */
	public function test_deserialize_payment_intention_carries_charge_disputes() {
		$disputes = [
			[
				'id'     => 'dp_1',
				'status' => 'needs_response',
			],
			[
				'id'     => 'dp_2',
				'status' => 'under_review',
			],
		];

		$intention_array = [
			'id'            => 'pi_mock',
			'amount'        => 1500,
			'currency'      => Currency_Code::UNITED_STATES_DOLLAR,
			'created'       => ( new DateTime() )->getTimestamp(),
			'status'        => Intent_Status::SUCCEEDED,
			'client_secret' => 'pi_mock_secret',
			'metadata'      => [],
			'charges'       => [
				'total_count' => 1,
				'data'        => [
					[
						'id'       => 'ch_mock',
						'amount'   => 1500,
						'created'  => ( new DateTime() )->getTimestamp(),
						'dispute'  => [ 'id' => 'dp_1' ],
						'disputed' => true,
						'disputes' => $disputes,
					],
				],
			],
		];

		$intent = $this->payments_api_client->deserialize_payment_intention_object_from_array( $intention_array );
		$charge = $intent->get_charge();

		$this->assertSame(
			[
				[
					'id'     => 'dp_1',
					'status' => 'needs_response',
				],
				[
					'id'     => 'dp_2',
					'status' => 'under_review',
				],
			],
			$charge->get_disputes()
		);

		$this->assertSame( [ 'id' => 'dp_1' ], $charge->get_dispute() );

		$this->assertTrue( $charge->get_disputed() );
	}

	/**
	 * Back-compat: a charge with no `disputes` array still deserializes, and the
	 * singular dispute fields are untouched.
	 */
	public function test_deserialize_payment_intention_without_charge_disputes() {
		$intention_array = [
			'id'            => 'pi_mock',
			'amount'        => 1500,
			'currency'      => Currency_Code::UNITED_STATES_DOLLAR,
			'created'       => ( new DateTime() )->getTimestamp(),
			'status'        => Intent_Status::SUCCEEDED,
			'client_secret' => 'pi_mock_secret',
			'metadata'      => [],
			'charges'       => [
				'total_count' => 1,
				'data'        => [
					[
						'id'      => 'ch_mock',
						'amount'  => 1500,
						'created' => ( new DateTime() )->getTimestamp(),
						'dispute' => [ 'id' => 'dp_1' ],
					],
				],
			],
		];

		$intent = $this->payments_api_client->deserialize_payment_intention_object_from_array( $intention_array );
		$charge = $intent->get_charge();

		$this->assertNull( $charge->get_disputes() );

		$this->assertSame( [ 'id' => 'dp_1' ], $charge->get_dispute() );
	}
}
