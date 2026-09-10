<?php
/**
 * Retrieves payment evidence anchored to a persisted intent receipt.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Core\Server\Request\Get_Charge;

/** One bounded recovery attempt; successful observations do not prove coverage. */
class PaymentEventRecovery {
	/**
	 * Receipt storage.
	 *
	 * @var PaymentEventRepository
	 */
	private $repository;
	/**
	 * Event recorder.
	 *
	 * @var PaymentEventRecorder
	 */
	private $recorder;
	/**
	 * Refund collector.
	 *
	 * @var RefundHistoryCollection
	 */
	private $refunds;
	/**
	 * Conditional report observations.
	 *
	 * @var PaymentReportRepository
	 */
	private $reports;

	/**
	 * Construct the worker without fetching or scheduling.
	 *
	 * @param PaymentEventRepository       $repository Receipt storage.
	 * @param PaymentEventRecorder         $recorder Qualified event storage.
	 * @param RefundHistoryCollection      $refunds Refund retrieval.
	 * @param PaymentReportRepository|null $reports Report storage, sharing the event repository by default.
	 */
	public function __construct( PaymentEventRepository $repository, PaymentEventRecorder $recorder, RefundHistoryCollection $refunds, ?PaymentReportRepository $reports = null ) {
		$this->repository = $repository;
		$this->recorder   = $recorder;
		$this->refunds    = $refunds;
		$this->reports    = $reports ?? new PaymentReportRepository( $repository );
	}

	/**
	 * Recover one candidate found by order-based discovery.
	 *
	 * Caller authorizes the job and provides trusted expected server context.
	 * A second charge retrieval uses the persisted receipt and the normal report
	 * invalidation path, avoiding a separate monetary publication implementation.
	 *
	 * @param int    $order_id Canonical order ID.
	 * @param string $charge_id Candidate charge ID, not trusted identity.
	 * @param array  $context Expected account/site/mode context.
	 * @param string $currency Historical reporting currency requested.
	 * @return array Recovery result; observed does not mean complete coverage.
	 */
	public function recover_discovered( int $order_id, string $charge_id, array $context, string $currency ): array {
		if ( $order_id <= 0 || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $charge_id ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || 4 !== count( $context ) || 1 !== ( $context['version'] ?? null ) || ! is_string( $context['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $context['account_id'] ) || ! is_int( $context['site_id'] ?? null ) || $context['site_id'] <= 0 || ! is_bool( $context['test_mode'] ?? null ) ) {
			return [ 'state' => 'invalid' ];
		}
		try {
			$request = Get_Charge::create( $charge_id );
			$request->set_hook_args( $charge_id );
			$request->set_test_mode( $context['test_mode'] );
			$request->set_include_reporting_context();
			$charge = $request->send();
		} catch ( \Throwable $exception ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'discovered_charge_retrieval_failed',
			];
		}
		if ( ! is_array( $charge ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'discovered_charge_invalid',
			];
		}
		$recorded = $this->recorder->record_discovered_intent( $order_id, $charge_id, $charge, $context );
		if ( 'recorded' !== $recorded['state'] ) {
			return $recorded;
		}
		$scope = [
			'account_id' => $context['account_id'],
			'site_id'    => $context['site_id'],
			'test_mode'  => $context['test_mode'],
			'order_id'   => $order_id,
			'event_id'   => $charge['payment_intent'],
		];
		return $this->recover( $scope, $currency );
	}

	/**
	 * Retrieve charge and refunds against one unchanged historical receipt.
	 *
	 * @param array  $scope Persisted intent account/site/mode/order/intent scope.
	 * @param string $currency Reporting currency.
	 * @return array Observed persistence results or incomplete state.
	 */
	public function recover( array $scope, string $currency ): array {
		if ( ! preg_match( '/^[a-zA-Z]{3}$/D', $currency ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'reporting_currency_invalid',
			];
		}
		$receipt = $this->qualified_receipt( $scope );
		if ( null === $receipt ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'intent_receipt_unqualified',
			];
		}
		$report = $this->reports->read( $scope, $currency );
		if ( 'found' !== $receipt['state'] || ! in_array( $report['state'], [ 'missing', 'observed', 'stale' ], true ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'report_storage_unavailable',
			];
		}
		$generation = $this->reports->invalidate( $scope, $currency, $report['revision'] ?? '' );
		if ( 'published' !== $generation['state'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'report_invalidation_conflict',
			];
		}
		$context    = [
			'version'    => 1,
			'account_id' => $scope['account_id'],
			'site_id'    => $scope['site_id'],
			'test_mode'  => $scope['test_mode'],
		];
		$charge_id  = $receipt['event']['charge_id'];
		$started_at = time();
		try {
			$request = Get_Charge::create( $charge_id );
			$request->set_hook_args( $charge_id );
			$request->set_test_mode( $scope['test_mode'] );
			$request->set_include_reporting_context();
			$charge = $request->send();
		} catch ( \Throwable $exception ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'charge_retrieval_failed',
			];
		}
		$current = $this->qualified_receipt( $scope );
		if ( null === $current || $current['revision'] !== $receipt['revision'] || ! is_array( $charge ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'intent_receipt_unqualified',
			];
		}
		if ( ! empty( $charge['payment_intent'] ) && $scope['event_id'] !== $charge['payment_intent'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'charge_intent_mismatch',
			];
		}
		$capture = $this->recorder->record_historical_capture( $scope, $receipt['revision'], $charge, $currency );
		if ( 'recorded' !== $capture['state'] ) {
			return $capture;
		}
		$collection = $this->refunds->collect( $charge_id, $scope['test_mode'], $context );
		$current    = $this->qualified_receipt( $scope );
		if ( null === $current || $current['revision'] !== $receipt['revision'] ) {
			return [
				'state'   => 'incomplete',
				'reason'  => 'intent_receipt_unqualified',
				'capture' => $capture,
			];
		}
		$refunds = $this->recorder->record_historical_refunds( $scope, $receipt['revision'], $collection, $currency );
		if ( 'recorded' !== $refunds['state'] ) {
			return [
				'state'   => 'incomplete',
				'capture' => $capture,
				'refunds' => $refunds,
			];
		}
		$dependencies                       = $refunds['revisions'];
		$dependencies[ $scope['event_id'] ] = $receipt['revision'];
		$dependencies[ $charge['balance_transaction']['id'] ] = $capture['revision'];
		$dependencies[ $refunds['collection_id'] ]            = $refunds['collection_revision'];
		$dependencies[ $refunds['membership_id'] ]            = $refunds['membership_revision'];
		$published = $this->reports->publish(
			$scope,
			$currency,
			$generation['revision'],
			$dependencies,
			[
				'started_at'   => $started_at,
				'completed_at' => time(),
			]
		);
		return [
			'state'   => 'published' === $published['state'] ? 'observed' : 'incomplete',
			'capture' => $capture,
			'refunds' => $refunds,
			'report'  => $published,
		];
	}

	/**
	 * Qualify immutable attempt facts without substituting current order metadata.
	 *
	 * @param array $scope Historical receipt partition.
	 * @return array|null Current qualified receipt, or null.
	 */
	private function qualified_receipt( array $scope ): ?array {
		$receipt = $this->repository->read( $scope );
		if ( 'found' !== $receipt['state'] || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $scope['event_id'] ) ) {
			return null;
		}
		$facts = $receipt['event'];
		if ( 'intent_context' !== ( $facts['kind'] ?? null ) || ! is_string( $facts['charge_id'] ?? null ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $facts['charge_id'] ) || ! is_int( $facts['original_amount'] ?? null ) || $facts['original_amount'] < 0 || ! is_string( $facts['original_currency'] ?? null ) || ! preg_match( '/^[A-Z]{3}$/D', $facts['original_currency'] ) ) {
			return null;
		}
		return $receipt;
	}
}
