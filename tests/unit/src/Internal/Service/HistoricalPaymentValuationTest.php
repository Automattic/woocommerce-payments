<?php
/**
 * A complete customer total is published only from terminal local records.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\HistoricalPaymentValuation;
use WCPay\Internal\Service\PaymentActivityReader;
use WCPay\Internal\Service\PaymentEventRepository;
use WCPay\Internal\Service\PaymentReceiptIndex;
use WCPay\Internal\Service\PaymentReportRepository;

/** Observing activity is not coverage; only attested membership qualifies. */
class HistoricalPaymentValuationTest extends \WCPAY_UnitTestCase {
	/**
	 * Canonical live scope for the order under test.
	 *
	 * @param int $order_id Order being valued.
	 * @return array Historical intent scope.
	 */
	private function scope( int $order_id ): array {
		return [
			'account_id' => 'acct_test123',
			'site_id'    => 1,
			'test_mode'  => false,
			'order_id'   => $order_id,
			'event_id'   => 'pi_first',
		];
	}

	/**
	 * Build an observed report for one captured, partly refunded attempt.
	 *
	 * @param bool $with_net Whether fee and net evidence is present.
	 * @param bool  $with_collection Whether an observed refund collection exists.
	 * @param array $refund_ids Refunds the observed collection declares.
	 * @param bool  $disputed Whether the capture records a dispute.
	 * @return array Report as published observations.
	 */
	private function report( bool $with_net = true, bool $with_collection = true, array $refund_ids = [ 're_first' ], bool $disputed = false ): array {
		$events = [
			'pi_first' => [
				'revision' => str_repeat( 'a', 64 ),
				'event'    => [
					'kind'              => 'intent_context',
					'charge_id'         => 'ch_first',
					'original_amount'   => 5235,
					'original_currency' => 'EUR',
				],
			],
			'txn_cap'  => [
				'revision' => str_repeat( 'b', 64 ),
				'event'    => [
					'kind'     => 'capture',
					'evidence' => array_merge(
						[
							'charge_id'              => 'ch_first',
							'balance_transaction_id' => 'txn_cap',
							'source_created'         => 1700000000,
							'amount'                 => 5235,
							'currency'               => 'EUR',
							'state'                  => 'ready',
							'basis'                  => 'captured_payment_gross',
							'original_amount'        => 5235,
							'original_currency'      => 'EUR',
							'funds_status'           => 'available',
							'dispute_state'          => $disputed ? 'disputed' : 'none',
						],
						$with_net ? [
							'net_state'  => 'ready',
							'fee_amount' => 235,
							'net_amount' => 5000,
						] : []
					),
				],
			],
			'txn_ref'  => [
				'revision' => str_repeat( 'c', 64 ),
				'event'    => [
					'kind'              => 'refund',
					'charge_id'         => 'ch_first',
					'refund_id'         => 're_first',
					'original_amount'   => 1000,
					'original_currency' => 'EUR',
					'evidence'          => array_merge(
						[
							'kind'         => 'refund',
							'id'           => 'txn_ref',
							'created'      => 1700000100,
							'amount'       => -1000,
							'currency'     => 'EUR',
							'funds_status' => 'available',
						],
						$with_net ? [
							'fee' => -50,
							'net' => -950,
						] : []
					),
				],
			],
		];
		$events[ 'membership_' . hash( 'sha256', 'ch_first' ) ] = [
			'revision' => str_repeat( '9', 64 ),
			'event'    => [
				'kind'       => 'refund_membership',
				'refund_ids' => $refund_ids,
			],
		];
		if ( $with_collection ) {
			$events['collection_one'] = [
				'revision' => str_repeat( 'd', 64 ),
				'event'    => [
					'kind'               => 'refund_collection',
					'charge_id'          => 'ch_first',
					'reporting_currency' => 'EUR',
					'refund_ids'         => [ 're_first' ],
					'coverage'           => 'observed_collection',
				],
			];
		}
		return [
			'state'    => 'observed',
			'revision' => str_repeat( 'e', 64 ),
			'events'   => $events,
		];
	}

	/**
	 * Assemble the service with controllable evidence.
	 *
	 * @param array $receipts Live receipt locators returned by the index.
	 * @param array $report Published observations for the attempt.
	 * @param array $stored Evidence keyed by event_id for direct reads.
	 * @return HistoricalPaymentValuation Service under test.
	 */
	private function service( array $receipts, array $report, array $stored ): HistoricalPaymentValuation {
		$index = $this->createMock( PaymentReceiptIndex::class );
		$index->method( 'find_for_order' )->willReturn(
			[
				'state'       => $receipts ? 'known' : 'unknown',
				'receipts'    => $receipts,
				'has_more'    => false,
				'next_cursor' => '',
			]
		);
		$reports = $this->createMock( PaymentReportRepository::class );
		$reports->method( 'read_observations' )->willReturn( $report );
		$events = $this->createMock( PaymentEventRepository::class );
		$events->method( 'read' )->willReturnCallback(
			function ( array $scope ) use ( $stored ) {
				return $stored[ $scope['event_id'] ] ?? [ 'state' => 'missing' ];
			}
		);
		return new HistoricalPaymentValuation( $index, $reports, $events, new PaymentActivityReader( $index, $reports ) );
	}

	/**
	 * Stored evidence for a fully attested order.
	 *
	 * @param int   $order_id Order being valued.
	 * @param array $intent_ids Attempts this site originated.
	 * @return array Evidence keyed by event_id.
	 */
	private function stored( int $order_id, array $intent_ids = [ 'pi_first' ] ): array {
		return [
			'attempts_' . hash( 'sha256', (string) $order_id ) => [
				'state'    => 'found',
				'revision' => str_repeat( 'f', 64 ),
				'event'    => [
					'kind'       => 'order_attempts',
					'order_id'   => $order_id,
					'intent_ids' => $intent_ids,
					'origin'     => 'site_originated',
				],
			],
		];
	}

	/**
	 * Value a real recorded order end to end, then render it through core.
	 *
	 * The valuation half always runs. The render half is skipped, never silently
	 * passed, when the companion core branch is absent.
	 *
	 * Uses task-owned event tables and the production recorder rather than
	 * mocked evidence, so the attempt attestation, capture, refund collection
	 * and core aggregation are exercised together. The core metabox assertions
	 * opt in with WCPAY_TEST_CUSTOMER_HISTORY_INTEGRATION=1 against the
	 * companion core branch; the valuation itself always runs.
	 */
	public function test_recorded_order_values_and_renders() {
		global $wpdb;
		$db          = clone $wpdb;
		$db->prefix .= 'task_payment_valuation_test_';
		$repository  = new PaymentEventRepository( $db );
		$this->assertTrue( $repository->create_schema() );
		$db->options = $db->prefix . 'options';
		$this->assertNotFalse( $db->query( "CREATE TABLE {$db->options} (option_name varchar(191) NOT NULL PRIMARY KEY, option_value longtext NOT NULL, autoload varchar(20) NOT NULL)" ) );
		$index    = new PaymentReceiptIndex( $db, $repository );
		$recorder = new \WCPay\Internal\Service\PaymentEventRecorder( $repository, $index );
		$reports  = new PaymentReportRepository( $repository );
		$service  = new HistoricalPaymentValuation( $index, $reports, $repository, new PaymentActivityReader( $index, $reports ) );

		$previous_user     = get_current_user_id();
		$previous_currency = get_option( 'woocommerce_currency' );
		$admin             = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context           = [
			'version'    => 1,
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'test_mode'  => false,
		];
		$order             = new \WC_Order();
		$order->set_payment_method( \WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->set_billing_email( 'valuation@example.invalid' );
		$order->set_currency( 'GBP' );
		$order->set_total( 36 );
		$order->update_meta_data( '_charge_id', 'ch_fixture' );
		$order->update_meta_data( '_intent_id', 'pi_fixture' );
		$order->update_meta_data( '_wcpay_mode', 'prod' );
		$order->save();
		$id           = $order->get_id();
		$charge       = [
			'id'                      => 'ch_fixture',
			'paid'                    => true,
			'captured'                => true,
			'status'                  => 'succeeded',
			'amount'                  => 3600,
			'amount_captured'         => 3600,
			'currency'                => 'gbp',
			'amount_refunded'         => 0,
			'disputed'                => false,
			'livemode'                => true,
			'wcpay_reporting_context' => $context,
			'balance_transaction'     => [
				'id'       => 'txn_capture',
				'amount'   => 4190,
				'currency' => 'eur',
				'created'  => 1700000000,
				'fee'      => 193,
				'net'      => 3997,
				'status'   => 'available',
			],
		];
		$buffer_level = ob_get_level();
		try {
			wp_set_current_user( $admin );
			update_option( 'woocommerce_currency', 'EUR' );
			$intent = \WC_Helper_Intention::create_intention(
				[
					'id'       => 'pi_fixture',
					'amount'   => 3600,
					'currency' => 'gbp',
					'charge'   => [
						'id'       => 'ch_fixture',
						'amount'   => 3600,
						'currency' => 'gbp',
					],
				]
			);
			$intent->set_reporting_context( $context );

			// Before any receipt, a gateway order is unknown rather than zero.
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );

			$this->assertSame( 'recorded', $recorder->record_intent( $order, $intent )['state'] );
			$attempts = $repository->read(
				[
					'account_id' => 'acct_fixture',
					'site_id'    => 123,
					'test_mode'  => false,
					'order_id'   => $id,
					'event_id'   => 'attempts_' . hash( 'sha256', (string) $id ),
				]
			);
			$this->assertSame( 'found', $attempts['state'] );
			$this->assertSame( [ 'pi_fixture' ], $attempts['event']['intent_ids'] );

			// An attested attempt with no capture evidence still cannot be totalled.
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );

			$receipt = $recorder->record_intent( $order, $intent );
			$capture = $recorder->record_capture( $order, $charge, 'EUR', $context );
			$this->assertSame( 'recorded', $capture['state'] );

			// A capture with no observed refund retrieval leaves refunds unknown.
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );

			$refunds = $recorder->record_refunds(
				$order,
				[
					'state'             => 'listed',
					'charge_id'         => 'ch_fixture',
					'reporting_context' => $context,
					'refunds'           => [],
					'retrieval'         => [
						'started_at'   => 1700000010,
						'completed_at' => 1700000020,
					],
				],
				'EUR',
				$context
			);
			$this->assertSame( 'recorded', $refunds['state'] );

			// Publish the observation exactly as the recovery path assembles it.
			$intent_scope = array_merge(
				$context,
				[
					'order_id' => $id,
					'event_id' => 'pi_fixture',
				]
			);
			unset( $intent_scope['version'] );
			$dependencies                              = $refunds['revisions'];
			$dependencies['pi_fixture']                = $receipt['revision'];
			$dependencies['txn_capture']               = $capture['revision'];
			$dependencies[ $refunds['collection_id'] ] = $refunds['collection_revision'];
			$dependencies[ $refunds['membership_id'] ] = $refunds['membership_revision'];
			$this->assertSame(
				'published',
				$reports->publish(
					$intent_scope,
					'EUR',
					'',
					$dependencies,
					[
						'started_at'   => 1700000010,
						'completed_at' => 1700000020,
					]
				)['state']
			);

			$gross = $service->value_order( $id, 'EUR', 'payments' );
			$this->assertSame( 'qualified', $gross['state'] );
			$this->assertSame( 'complete', $gross['coverage'] );
			$this->assertSame( 4190, $gross['total_minor'] );
			$net = $service->value_order( $id, 'EUR', 'net' );
			$this->assertSame( 'qualified', $net['state'] );
			$this->assertSame( 3997, $net['total_minor'] );

			if ( '1' !== getenv( 'WCPAY_TEST_CUSTOMER_HISTORY_INTEGRATION' ) || ! class_exists( \Automattic\WooCommerce\Internal\Admin\Orders\MetaBoxes\CustomerHistory::class ) ) {
				// Never a silent pass: the render half needs the companion core branch.
				$this->markTestSkipped( 'Set WCPAY_TEST_CUSTOMER_HISTORY_INTEGRATION=1 against the companion core branch to assert the metabox render.' );
			}
			// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercises the core provider contract.
			$service->init_hooks();
			update_user_option( $admin, 'user-settings', 'wc_history_basis=payments', false );
			unset( $GLOBALS['_updated_user_settings'] );
			$history = new \Automattic\WooCommerce\Internal\Admin\Orders\MetaBoxes\CustomerHistory();
			ob_start();
			$history->output( wc_get_order( $id ) );
			$html = ob_get_clean();
			$this->assertStringContainsString( 'order-attribution-total-payments', $html );
			$this->assertStringContainsString( '41.90', $html );
			$this->assertStringContainsString( 'of which 1 had a recorded payment', $html );

			// A second order that took a charge this site never recorded withdraws the
			// customer total. It carries a charge ID, so it is unknown rather than an
			// abandoned attempt that legitimately contributes nothing.
			$sibling = new \WC_Order();
			$sibling->set_payment_method( \WC_Payment_Gateway_WCPay::GATEWAY_ID );
			$sibling->set_billing_email( 'valuation@example.invalid' );
			$sibling->set_currency( 'EUR' );
			$sibling->set_total( 10 );
			$sibling->update_meta_data( '_charge_id', 'ch_unrecorded' );
			$sibling->save();
			ob_start();
			$history->output( wc_get_order( $id ) );
			$html = ob_get_clean();
			$this->assertStringNotContainsString( 'order-attribution-total-payments', $html );
			$this->assertStringContainsString( 'no complete captured payment and refund record', $html );
			$sibling->delete( true );

			// The same sibling without a charge is an abandoned attempt: the total stands.
			$abandoned = new \WC_Order();
			$abandoned->set_payment_method( \WC_Payment_Gateway_WCPay::GATEWAY_ID );
			$abandoned->set_billing_email( 'valuation@example.invalid' );
			$abandoned->set_currency( 'EUR' );
			$abandoned->set_total( 10 );
			$abandoned->update_meta_data( '_intent_id', 'pi_abandoned' );
			$abandoned->save();
			ob_start();
			$history->output( wc_get_order( $id ) );
			$html = ob_get_clean();
			$this->assertStringContainsString( 'order-attribution-total-payments', $html );
			$this->assertStringContainsString( 'of which 1 had a recorded payment', $html );
			$abandoned->delete( true );
			// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			remove_filter( 'woocommerce_customer_history_payment_valuation', [ $service, 'provide_customer_history' ], 10 );
			unset( $GLOBALS['_updated_user_settings'] );
			$order->delete( true );
			wp_set_current_user( $previous_user );
			update_option( 'woocommerce_currency', $previous_currency );
			wp_delete_user( $admin );
			foreach ( [ 'wcpay_event_revisions', 'wcpay_event_heads', 'options' ] as $suffix ) {
				$db->query( "DROP TABLE IF EXISTS {$db->prefix}{$suffix}" );
			}
		}
	}

	/** A fully attested order publishes gross and net totals after refunds. */
	public function test_complete_order_qualifies_for_both_bases() {
		$order = new \WC_Order();
		$order->save();
		$id       = $order->get_id();
		$receipts = [ [ 'scope' => $this->scope( $id ) ] ];
		$service  = $this->service( $receipts, $this->report(), $this->stored( $id ) );
		try {
			$gross = $service->value_order( $id, 'EUR', 'payments' );
			$this->assertSame( 'qualified', $gross['state'] );
			$this->assertSame( 'complete', $gross['coverage'] );
			$this->assertSame( 4235, $gross['total_minor'] );
			$this->assertSame( 2, $gross['precision'] );
			$this->assertSame( 'site_originated_receipts', $gross['source'] );
			$net = $service->value_order( $id, 'EUR', 'net' );
			$this->assertSame( 'qualified', $net['state'] );
			$this->assertSame( 4050, $net['total_minor'] );
		} finally {
			$order->delete( true );
		}
	}

	/** Missing fee evidence blocks net without blocking gross. */
	public function test_missing_net_evidence_blocks_only_net() {
		$order = new \WC_Order();
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $this->report( false ), $this->stored( $id ) );
		try {
			$this->assertSame( 'qualified', $service->value_order( $id, 'EUR', 'payments' )['state'] );
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'net' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** Without an attempt attestation, observed activity is not coverage. */
	public function test_absent_attempt_membership_is_unavailable() {
		$order = new \WC_Order();
		$order->save();
		$id     = $order->get_id();
		$stored = $this->stored( $id );
		unset( $stored[ 'attempts_' . hash( 'sha256', (string) $id ) ] );
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $this->report(), $stored );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** A receipt the site did not originate leaves the attempt set non-terminal. */
	public function test_receipt_outside_membership_is_unavailable() {
		$order = new \WC_Order();
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $this->report(), $this->stored( $id, [ 'pi_other' ] ) );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** An unrecorded attempt listed by membership cannot be silently dropped. */
	public function test_unreceipted_listed_attempt_is_unavailable() {
		$order = new \WC_Order();
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $this->report(), $this->stored( $id, [ 'pi_first', 'pi_second' ] ) );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** No observed refund retrieval means unknown refunds, never zero refunds. */
	public function test_absent_refund_collection_is_unavailable() {
		$order = new \WC_Order();
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $this->report( true, false ), $this->stored( $id ) );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** A collection that omits an observed refund is not terminal. */
	public function test_refund_outside_collection_is_unavailable() {
		$order = new \WC_Order();
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $this->report( true, true, [ 're_other' ] ), $this->stored( $id ) );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** An order this gateway never touched declares no payment instead of blocking. */
	public function test_unrelated_order_declares_no_payment() {
		$order = new \WC_Order();
		$order->set_payment_method( 'bacs' );
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [], [ 'state' => 'unknown' ], [] );
		try {
			$this->assertSame( 'no_payment', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** A gateway order that produced a charge but holds no receipt is unknown. */
	public function test_charged_order_without_receipts_is_unavailable() {
		$order = new \WC_Order();
		$order->set_payment_method( \WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->update_meta_data( '_charge_id', 'ch_missing' );
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [], [ 'state' => 'unknown' ], [] );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** An abandoned or declined attempt never captured, so it contributes nothing. */
	public function test_gateway_order_that_never_charged_declares_no_payment() {
		$order = new \WC_Order();
		$order->set_payment_method( \WC_Payment_Gateway_WCPay::GATEWAY_ID );
		// WooCommerce creates the order before payment, so an abandoned 3DS challenge
		// leaves the gateway and its intent behind with nothing ever captured. Treating
		// that as unknown would withhold the total for every repeat customer.
		$order->update_meta_data( '_intent_id', 'pi_abandoned' );
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [], [ 'state' => 'unknown' ], [] );
		try {
			$this->assertSame( 'no_payment', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** A disputed capture moved money that no refund row records, so refuse. */
	public function test_disputed_capture_is_unavailable() {
		$order = new \WC_Order();
		$order->save();
		$id      = $order->get_id();
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $this->report( true, true, [ 're_first' ], true ), $this->stored( $id ) );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** Captures recorded before dispute evidence existed are unknown, not undisputed. */
	public function test_capture_without_dispute_evidence_is_unavailable() {
		$order = new \WC_Order();
		$order->save();
		$id     = $order->get_id();
		$report = $this->report();
		unset( $report['events']['txn_cap']['event']['evidence']['dispute_state'] );
		$service = $this->service( [ [ 'scope' => $this->scope( $id ) ] ], $report, $this->stored( $id ) );
		try {
			$this->assertSame( 'unavailable', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** Test-mode receipts never contribute to and never block a merchant total. */
	public function test_test_mode_receipts_are_excluded() {
		$order = new \WC_Order();
		$order->set_payment_method( 'bacs' );
		$order->save();
		$id      = $order->get_id();
		$test    = array_merge( $this->scope( $id ), [ 'test_mode' => true ] );
		$service = $this->service( [ [ 'scope' => $test ] ], $this->report(), $this->stored( $id ) );
		try {
			$this->assertSame( 'no_payment', $service->value_order( $id, 'EUR', 'payments' )['state'] );
		} finally {
			$order->delete( true );
		}
	}

	/** The provider authorizes, answers one basis, and preserves other providers. */
	public function test_provider_authorizes_and_preserves_peers() {
		$order = new \WC_Order();
		$order->set_payment_method( 'bacs' );
		$order->save();
		$id            = $order->get_id();
		$previous_user = get_current_user_id();
		$user          = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$service       = $this->service( [], [ 'state' => 'unknown' ], [] );
		$other         = [ 'other_provider' => [ 'state' => 'unavailable' ] ];
		$request       = [
			'order_id' => $id,
			'currency' => 'EUR',
			'basis'    => 'payments',
		];
		// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercises the provider contract; introduces no public hook.
		try {
			$service->init_hooks();
			wp_set_current_user( 0 );
			$this->assertSame( $other, apply_filters( 'woocommerce_customer_history_payment_valuation', $other, $request ) );
			wp_set_current_user( $user );
			$this->assertSame( $other, apply_filters( 'woocommerce_customer_history_payment_valuation', $other, array_merge( $request, [ 'basis' => 'sales' ] ) ) );
			$result = apply_filters( 'woocommerce_customer_history_payment_valuation', $other, $request );
			$this->assertSame( $other['other_provider'], $result['other_provider'] );
			$this->assertSame( 'no_payment', $result['woocommerce_payments']['state'] );
		} finally {
			remove_filter( 'woocommerce_customer_history_payment_valuation', [ $service, 'provide_customer_history' ], 10 );
			wp_set_current_user( $previous_user );
			$order->delete( true );
			wp_delete_user( $user );
		}
		// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
	}
}
