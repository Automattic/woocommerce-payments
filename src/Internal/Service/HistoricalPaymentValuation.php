<?php
/**
 * Complete historical payment valuation for one order, or nothing.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Publishes a total only where this site's own records are terminal. */
class HistoricalPaymentValuation {
	/**
	 * Receipt locators.
	 *
	 * @var PaymentReceiptIndex
	 */
	private $index;
	/**
	 * Published observations.
	 *
	 * @var PaymentReportRepository
	 */
	private $reports;
	/**
	 * Immutable evidence, including attempt membership.
	 *
	 * @var PaymentEventRepository
	 */
	private $events;
	/**
	 * Row qualification shared with the activity view.
	 *
	 * @var PaymentActivityReader
	 */
	private $reader;

	/**
	 * Construct from the shared payment evidence services.
	 *
	 * @param PaymentReceiptIndex     $index Receipt locators for an order.
	 * @param PaymentReportRepository $reports Published observations.
	 * @param PaymentEventRepository  $events Immutable evidence store.
	 * @param PaymentActivityReader   $reader Shared row qualification.
	 */
	public function __construct( PaymentReceiptIndex $index, PaymentReportRepository $reports, PaymentEventRepository $events, PaymentActivityReader $reader ) {
		$this->index   = $index;
		$this->reports = $reports;
		$this->events  = $events;
		$this->reader  = $reader;
	}

	/** Register the optional complete-valuation provider on a shared instance. */
	public function init_hooks(): void {
		add_filter( 'woocommerce_customer_history_payment_valuation', [ $this, 'provide_customer_history' ], 10, 2 );
	}

	/**
	 * Answer the core valuation request for exactly one order and basis.
	 *
	 * Parameters stay untyped: a filter chain can hand any value to a callback,
	 * and a TypeError here would be fatal rather than an unavailable state.
	 *
	 * @param mixed $providers Existing provider responses, preserved by key.
	 * @param mixed $request Canonical order ID, reporting currency and basis.
	 * @return mixed Provider responses. Invalid or unauthorized requests add nothing.
	 */
	public function provide_customer_history( $providers, $request ) {
		if ( ! is_array( $providers ) || ! is_array( $request ) ) {
			return $providers;
		}
		$id       = $request['order_id'] ?? null;
		$currency = $request['currency'] ?? null;
		$basis    = $request['basis'] ?? null;
		if ( ! is_int( $id ) || $id <= 0 || ! is_string( $currency ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || ! in_array( $basis, [ 'payments', 'net' ], true ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return $providers;
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce order-specific capability.
		if ( ! current_user_can( 'edit_shop_order', $id ) ) {
			return $providers;
		}
		try {
			$order = wc_get_order( $id );
			if ( ! $order instanceof \WC_Order || 'shop_order' !== $order->get_type() ) {
				return $providers;
			}
			$providers['woocommerce_payments'] = $this->value_order( $id, $currency, $basis );
		} catch ( \Throwable $error ) {
			$providers['woocommerce_payments'] = [ 'state' => 'unavailable' ];
		}
		return $providers;
	}

	/**
	 * Value one order only when every live attempt it originated is fully recorded.
	 *
	 * Test-mode receipts never contribute to a merchant total and never make one
	 * unavailable. Every live receipt must be listed by this site's own attempt
	 * membership for its account, must carry qualified rows, and must carry an
	 * observed refund collection: an order with no recorded refund retrieval has
	 * unknown refunds, not zero. No network read or order mutation occurs.
	 *
	 * @param int    $order_id Authorized canonical order ID.
	 * @param string $currency Required reporting currency.
	 * @param string $basis Selected amount basis, payments or net.
	 * @return array Qualified complete valuation, no_payment, or unavailable.
	 */
	public function value_order( int $order_id, string $currency, string $basis ): array {
		$missing   = [ 'state' => 'unavailable' ];
		$precision = \WC_Payments_Utils::get_stripe_minor_unit_for_currency( $currency );
		$receipts  = $this->live_receipts( $order_id );
		if ( ! is_array( $receipts ) ) {
			return $missing;
		}
		if ( ! $receipts ) {
			return $this->has_payment_identity( $order_id ) ? $missing : [ 'state' => 'no_payment' ];
		}
		if ( ! $this->membership_is_terminal( $receipts ) ) {
			return $missing;
		}
		$total = 0;
		foreach ( $receipts as $locator ) {
			$scope  = $locator['scope'];
			$report = $this->reports->read_observations( $scope, $currency );
			if ( 'observed' !== ( $report['state'] ?? null ) ) {
				return $missing;
			}
			$rows = $this->reader->qualified_rows( $report, $scope, $currency );
			if ( null === $rows || ! $this->refunds_are_terminal( $report, $scope, $currency, $rows ) ) {
				return $missing;
			}
			foreach ( $rows as $row ) {
				// A dispute withdraws captured money without producing a refund, and
				// this evidence model has no dispute row. Only positive evidence that
				// none occurred can support a complete total; older captures recorded
				// before that evidence existed are unknown, not undisputed.
				if ( 'capture' === $row['kind'] && 'none' !== ( $report['events'][ $row['source_id'] ]['event']['evidence']['dispute_state'] ?? null ) ) {
					return $missing;
				}
				if ( 'net' === $basis && ( 'ready' !== $row['net_state'] || ! is_int( $row['net_amount'] ) ) ) {
					return $missing;
				}
				$amount = 'net' === $basis ? $row['net_amount'] : $row['amount'];
				if ( abs( $total ) > PHP_INT_MAX - abs( $amount ) ) {
					return $missing;
				}
				$total += $amount;
			}
		}
		return [
			'state'       => 'qualified',
			'coverage'    => 'complete',
			'order_id'    => $order_id,
			'currency'    => $currency,
			'basis'       => $basis,
			'precision'   => $precision,
			'total_minor' => $total,
			'source'      => 'site_originated_receipts',
		];
	}

	/**
	 * Collect every live receipt for the order across all discovery pages.
	 *
	 * @param int $order_id Canonical order ID.
	 * @return array|null Live receipt locators, or null when discovery failed.
	 */
	private function live_receipts( int $order_id ): ?array {
		$found = [];
		$after = '';
		// Bound the traversal; an order with more attempts than this is not summarised here.
		for ( $page = 0; $page < 20; $page++ ) {
			$batch = $this->index->find_for_order( $order_id, 100, $after );
			if ( ! in_array( $batch['state'] ?? null, [ 'known', 'unknown' ], true ) ) {
				return null;
			}
			foreach ( $batch['receipts'] as $locator ) {
				if ( false === ( $locator['scope']['test_mode'] ?? null ) ) {
					$found[] = $locator;
				}
			}
			if ( empty( $batch['has_more'] ) ) {
				return $found;
			}
			$after = $batch['next_cursor'];
		}
		return null;
	}

	/**
	 * Require this site's own attempt membership to list exactly these receipts.
	 *
	 * A receipt that membership does not list was discovered rather than
	 * originated, so the attempt set is not terminal and no total is publishable.
	 *
	 * @param array $receipts Live receipt locators for one order.
	 * @return bool Whether every account's membership exactly matches.
	 */
	private function membership_is_terminal( array $receipts ): bool {
		$accounts = [];
		foreach ( $receipts as $locator ) {
			$scope              = $locator['scope'];
			$key                = $scope['account_id'] . '|' . $scope['site_id'] . '|' . $scope['order_id'];
			$accounts[ $key ][] = $scope;
		}
		foreach ( $accounts as $scopes ) {
			$membership = $this->events->read(
				array_merge(
					$scopes[0],
					[ 'event_id' => 'attempts_' . hash( 'sha256', (string) $scopes[0]['order_id'] ) ]
				)
			);
			if ( 'found' !== ( $membership['state'] ?? null ) || 'order_attempts' !== ( $membership['event']['kind'] ?? null ) || ( $membership['event']['order_id'] ?? null ) !== $scopes[0]['order_id'] || 'site_originated' !== ( $membership['event']['origin'] ?? null ) || ! is_array( $membership['event']['intent_ids'] ?? null ) ) {
				return false;
			}
			$listed = $membership['event']['intent_ids'];
			$held   = array_column( $scopes, 'event_id' );
			sort( $listed, SORT_STRING );
			sort( $held, SORT_STRING );
			if ( $listed !== $held ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Require an observed refund collection matching exactly the refunds present.
	 *
	 * Both the collection and its membership are report dependencies, so they are
	 * already pinned to the revisions this observation was published against.
	 *
	 * @param array  $report Published observations for one attempt.
	 * @param array  $scope Historical intent scope.
	 * @param string $currency Reporting currency.
	 * @param array  $rows Qualified rows for this attempt.
	 * @return bool Whether refund coverage for this attempt is terminal.
	 */
	private function refunds_are_terminal( array $report, array $scope, string $currency, array $rows ): bool {
		$charge = $report['events'][ $scope['event_id'] ]['event']['charge_id'] ?? null;
		if ( ! is_string( $charge ) ) {
			return false;
		}
		$membership = $report['events'][ 'membership_' . hash( 'sha256', $charge ) ]['event'] ?? null;
		if ( ! is_array( $membership ) || 'refund_membership' !== ( $membership['kind'] ?? null ) || ! is_array( $membership['refund_ids'] ?? null ) ) {
			return false;
		}
		$collection = null;
		foreach ( $report['events'] as $source ) {
			$event = $source['event'];
			if ( 'refund_collection' !== ( $event['kind'] ?? null ) || ( $event['charge_id'] ?? null ) !== $charge ) {
				continue;
			}
			if ( strtoupper( (string) ( $event['reporting_currency'] ?? '' ) ) !== $currency || 'observed_collection' !== ( $event['coverage'] ?? null ) || ! is_array( $event['refund_ids'] ?? null ) ) {
				continue;
			}
			// Two collections for one charge leave no single terminal refund set.
			if ( null !== $collection ) {
				return false;
			}
			$collection = $event;
		}
		if ( null === $collection ) {
			return false;
		}
		$declared = $collection['refund_ids'];
		$listed   = $membership['refund_ids'];
		sort( $declared, SORT_STRING );
		sort( $listed, SORT_STRING );
		if ( $declared !== $listed ) {
			return false;
		}
		// Every refund row present must belong to the collection this site observed.
		$observed = [];
		foreach ( $rows as $row ) {
			if ( in_array( $row['kind'], [ 'refund', 'refund_failure_reversal' ], true ) ) {
				$refund = $report['events'][ $row['source_id'] ]['event']['refund_id'] ?? null;
				if ( ! is_string( $refund ) || ! in_array( $refund, $declared, true ) ) {
					return false;
				}
				$observed[ $refund ] = true;
			}
		}
		return count( $observed ) === count( $declared );
	}

	/**
	 * Detect whether this gateway ever produced a charge for the order.
	 *
	 * Used only to refuse a no_payment claim. A charge identifier means money
	 * moved and its absent record is unknown. An intent alone does not: WooCommerce
	 * creates the order before payment, so an abandoned or declined attempt leaves
	 * a payment method and intent behind with nothing ever captured. Treating those
	 * as unknown would withhold the customer total for every repeat customer who
	 * once abandoned a checkout.
	 *
	 * @param int $order_id Canonical order ID.
	 * @return bool Whether the order carries evidence of a captured payment.
	 */
	private function has_payment_identity( int $order_id ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return true;
		}
		// A charge identifier is read for existence only. Order meta can hold arrays,
		// so casting to string would both warn and yield a misleading non-empty value.
		$charge = $order->get_meta( '_charge_id', true, 'edit' );
		if ( is_string( $charge ) && '' !== $charge ) {
			return true;
		}
		// The transaction ID has a getter; reading it as internal meta is doing_it_wrong.
		return '' !== (string) $order->get_transaction_id();
	}
}
