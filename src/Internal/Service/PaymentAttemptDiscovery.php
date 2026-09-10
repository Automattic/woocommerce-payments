<?php
/**
 * Bounded candidate discovery from the server transaction cache.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Core\Server\Request\List_Transactions;
use WCPay\Core\Server\Response;

/** Candidates require independent historical identity and amount qualification. */
class PaymentAttemptDiscovery {
	/**
	 * Resolve the connected account without mutating account cache or mode defaults.
	 *
	 * Charge response provenance must still match this expected context.
	 *
	 * @param bool $test_mode Explicit historical payment mode.
	 * @return array Expected context, or empty when unavailable/changed.
	 */
	public function resolve_context( bool $test_mode ): array {
		try {
			$request = \WCPay\Core\Server\Request\Get_Account::create();
			$site_id = $request->get_connection_site_id();
			if ( ! is_int( $site_id ) || $site_id <= 0 ) {
				return []; }
			$response   = $request->send();
			$data       = $response instanceof Response ? $response->to_array() : [];
			$account_id = $data['account_id'] ?? null;
			if ( ! is_string( $account_id ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $account_id ) || $request->get_connection_site_id() !== $site_id ) {
				return []; }
			return [
				'version'    => 1,
				'account_id' => $account_id,
				'site_id'    => $site_id,
				'test_mode'  => $test_mode,
			];
		} catch ( \Throwable $exception ) {
			return [];
		}
	}

	/**
	 * Run one bounded capture/dispatch step under a revalidated connection.
	 *
	 * The authorized caller supplies freshly resolved server connection context.
	 * This does not discover disconnected accounts or certify complete history.
	 *
	 * @param array                         $scope Discovery generation scope.
	 * @param string                        $currency Reporting currency.
	 * @param array                         $current_context Current account/site/mode.
	 * @param PaymentEventRepository        $repository Durable progress.
	 * @param PaymentEventRecoveryScheduler $scheduler Candidate queue.
	 * @return array Step outcome with unknown coverage.
	 */
	public function run_step( array $scope, string $currency, array $current_context, PaymentEventRepository $repository, PaymentEventRecoveryScheduler $scheduler ): array {
		$expected = [
			'version'    => 1,
			'account_id' => $scope['account_id'] ?? null,
			'site_id'    => $scope['site_id'] ?? null,
			'test_mode'  => $scope['test_mode'] ?? null,
		];
		ksort( $expected );
		ksort( $current_context );
		if ( $expected !== $current_context ) {
			return [
				'state'    => 'incomplete',
				'coverage' => 'unknown',
				'reason'   => 'context_changed',
			];
		}
		$saved = $repository->read( $scope );
		if ( 'missing' === $saved['state'] ) {
			$captured = $this->capture_page( $scope, $currency, 1, '', $repository );
		} elseif ( 'found' === $saved['state'] ) {
			$progress = $saved['event'];
			if ( 'queued' === ( $progress['phase'] ?? null ) && is_int( $progress['next_page'] ?? null ) ) {
				$captured = $this->capture_page( $scope, $currency, $progress['next_page'], $saved['revision'], $repository );
			} else {
				return $this->dispatch_stored_page( $scope, $currency, $repository, $scheduler );
			}
		} else {
			return [
				'state'    => 'incomplete',
				'coverage' => 'unknown',
			];
		}
		if ( 'captured' !== $captured['state'] ) {
			return $captured;
		}
		return $this->dispatch_stored_page( $scope, $currency, $repository, $scheduler );
	}

	/**
	 * Capture a page durably before any candidate dispatch.
	 *
	 * Caller authorizes the generation and revalidates its connection context.
	 * Existing progress can advance only from its queued continuation.
	 *
	 * @param array                  $scope Discovery generation identity.
	 * @param string                 $currency Reporting currency.
	 * @param int                    $page Requested page.
	 * @param string                 $expected_revision Previously observed progress revision.
	 * @param PaymentEventRepository $repository Durable revision store.
	 * @return array Captured revision or incomplete/invalid state.
	 */
	public function capture_page( array $scope, string $currency, int $page, string $expected_revision, PaymentEventRepository $repository ): array {
		if ( ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^discovery_[a-zA-Z0-9]+$/D', $scope['event_id'] ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || $page < 1 || $page > 20 ) {
			return [ 'state' => 'invalid' ];
		}
		$before = $repository->read( $scope );
		if ( 'missing' === $before['state'] ) {
			if ( 1 !== $page ) {
				return [ 'state' => 'invalid' ]; }
			if ( '' !== $expected_revision ) {
				return [ 'state' => 'incomplete' ]; }
		} elseif ( 'found' === $before['state'] ) {
			$progress = $before['event'];
			if ( $before['revision'] !== $expected_revision || 'discovery_progress' !== ( $progress['kind'] ?? null ) || ( $progress['currency'] ?? null ) !== $currency || 'queued' !== ( $progress['phase'] ?? null ) || ( $progress['next_page'] ?? null ) !== $page ) {
				return [ 'state' => 'incomplete' ];
			}
		} else {
			return [ 'state' => 'incomplete' ];
		}
		$captured = $this->read_page( $scope['order_id'], $scope['test_mode'], $page );
		if ( 'candidates' !== $captured['state'] ) {
			return [ 'state' => 'incomplete' ]; }
		$saved = $repository->publish(
			$scope,
			$expected_revision,
			[
				'kind'         => 'discovery_progress',
				'currency'     => $currency,
				'page'         => $page,
				'pending_page' => $captured,
			]
		);
		if ( 'published' !== $saved['state'] ) {
			return [ 'state' => 'incomplete' ]; }
		$current = $repository->read( $scope );
		if ( 'found' !== $current['state'] || $current['revision'] !== $saved['revision'] ) {
			return [ 'state' => 'incomplete' ]; }
		return [
			'state'    => 'captured',
			'coverage' => 'unknown',
			'revision' => $saved['revision'],
		];
	}

	/**
	 * Dispatch captured candidates and conditionally persist progress.
	 *
	 * Caller must authorize and revalidate the generation connection context.
	 * This method never fetches a replacement page or creates initial progress.
	 *
	 * @param array                         $scope Immutable discovery generation scope.
	 * @param string                        $currency Generation reporting currency.
	 * @param PaymentEventRepository        $repository Durable revision store.
	 * @param PaymentEventRecoveryScheduler $scheduler Candidate queue.
	 * @return array Dispatch state with unknown coverage.
	 */
	public function dispatch_stored_page( array $scope, string $currency, PaymentEventRepository $repository, PaymentEventRecoveryScheduler $scheduler ): array {
		if ( ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^discovery_[a-zA-Z0-9]+$/D', $scope['event_id'] ) ) {
			return [ 'state' => 'invalid' ];
		}
		$saved = $repository->read( $scope );
		if ( 'found' !== $saved['state'] ) {
			return [
				'state'    => 'incomplete',
				'coverage' => 'unknown',
			];
		}
		$progress = $saved['event'];
		if ( 'discovery_progress' !== ( $progress['kind'] ?? null ) || ( $progress['currency'] ?? null ) !== $currency || ! is_int( $progress['page'] ?? null ) || $progress['page'] < 1 || $progress['page'] > 20 || ! preg_match( '/^[A-Z]{3}$/D', $currency ) ) {
			return [ 'state' => 'invalid' ];
		}
		if ( 'queued' === ( $progress['phase'] ?? null ) ) {
			if ( ! array_key_exists( 'pending_page', $progress ) || null !== $progress['pending_page'] || ! is_bool( $progress['has_more'] ?? null ) || ( $progress['next_page'] ?? null ) !== ( $progress['has_more'] && $progress['page'] < 20 ? $progress['page'] + 1 : null ) ) {
				return [ 'state' => 'invalid' ];
			}
			return [
				'state'     => 'queued',
				'coverage'  => 'unknown',
				'has_more'  => $progress['has_more'],
				'next_page' => $progress['next_page'],
			];
		}
		if ( ! is_array( $progress['pending_page'] ?? null ) ) {
			return [ 'state' => 'invalid' ];
		}
		$context = [
			'version'    => 1,
			'account_id' => $scope['account_id'],
			'site_id'    => $scope['site_id'],
			'test_mode'  => $scope['test_mode'],
		];
		$result  = $this->dispatch_page( $scope['order_id'], $context, $currency, $progress['page'], $scheduler, $progress['pending_page'] );
		if ( 'queued' === $result['state'] ) {
			$progress['phase']        = 'queued';
			$progress['pending_page'] = null;
			$progress['has_more']     = $result['has_more'];
			$progress['next_page']    = $result['next_page'];
		} elseif ( isset( $result['pending_page'] ) ) {
			$progress['phase']        = 'pending';
			$progress['pending_page'] = $result['pending_page'];
		} else {
			return $result;
		}
		$progress['coverage'] = 'unknown';
		$published            = $repository->publish( $scope, $saved['revision'], $progress );
		if ( 'published' !== $published['state'] ) {
			return [
				'state'    => 'incomplete',
				'coverage' => 'unknown',
				'reason'   => 'progress_not_published',
			];
		}
		$current = $repository->read( $scope );
		if ( 'found' !== $current['state'] || $current['revision'] !== $published['revision'] ) {
			return [
				'state'    => 'incomplete',
				'coverage' => 'unknown',
				'reason'   => 'concurrent_progress',
			];
		}
		return $result;
	}

	/**
	 * Dispatch one authorized page; advance only after all candidates are queued.
	 *
	 * The caller owns authorization, connection revalidation and durable progress.
	 * A completed cache page still does not establish complete payment membership.
	 *
	 * @param int                           $order_id Canonical order ID.
	 * @param array                         $context Expected server context.
	 * @param string                        $currency Reporting currency.
	 * @param int                           $page Current offset page.
	 * @param PaymentEventRecoveryScheduler $scheduler Candidate queue.
	 * @param array|null                    $pending_page Previously captured page remainder.
	 * @return array Queue outcome, never qualified payment totals.
	 */
	public function dispatch_page( int $order_id, array $context, string $currency, int $page, PaymentEventRecoveryScheduler $scheduler, ?array $pending_page = null ): array {
		if ( $order_id <= 0 || $page < 1 || $page > 20 || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || 4 !== count( $context ) || 1 !== ( $context['version'] ?? null ) || ! is_string( $context['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $context['account_id'] ) || ! is_int( $context['site_id'] ?? null ) || $context['site_id'] <= 0 || ! is_bool( $context['test_mode'] ?? null ) ) {
			return [ 'state' => 'invalid' ];
		}
		$result = $pending_page ?? $this->read_page( $order_id, $context['test_mode'], $page );
		if ( 'candidates' !== ( $result['state'] ?? null ) ) {
			return [
				'state'      => 'incomplete',
				'retry_page' => $page,
				'coverage'   => 'unknown',
			];
		}
		if ( ! is_array( $result['charge_ids'] ?? null ) || array_values( $result['charge_ids'] ) !== $result['charge_ids'] || count( $result['charge_ids'] ) > 100 || ! is_bool( $result['has_more'] ?? null ) || ( $result['next_page'] ?? null ) !== ( $result['has_more'] && $page < 20 ? $page + 1 : null ) ) {
			return [ 'state' => 'invalid' ];
		}
		foreach ( $result['charge_ids'] as $id ) {
			if ( ! is_string( $id ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $id ) ) {
				return [ 'state' => 'invalid' ];
			}
		}
		try {
			foreach ( $result['charge_ids'] as $index => $charge_id ) {
				$scheduler->enqueue_candidate(
					[
						'account_id' => $context['account_id'],
						'site_id'    => $context['site_id'],
						'test_mode'  => $context['test_mode'],
						'order_id'   => $order_id,
						'event_id'   => $charge_id,
					],
					$currency
				);
			}
		} catch ( \Throwable $exception ) {
			$result['charge_ids'] = array_slice( $result['charge_ids'], $index );
			return [
				'pending_page' => $result,
				'state'        => 'incomplete',
				'retry_page'   => $page,
				'coverage'     => 'unknown',
			];
		}
		return [
			'state'     => 'queued',
			'coverage'  => 'unknown',
			'has_more'  => $result['has_more'],
			'next_page' => $result['next_page'],
		];
	}

	/**
	 * Read one page for a background worker, never during admin rendering.
	 *
	 * The endpoint uses the currently linked account. Neither a terminal page
	 * nor an empty cache establishes complete historical order membership.
	 * Callers must qualify fetched charges against historical site/account/order
	 * evidence before binding receipts. Local intent decoration is ignored.
	 *
	 * @param int  $order_id Canonical order identifier used only as a search key.
	 * @param bool $test_mode Explicit payment mode.
	 * @param int  $page Bounded offset page; not a stable snapshot cursor.
	 * @return array Candidate charge IDs with unknown coverage, or unavailable.
	 */
	public function read_page( int $order_id, bool $test_mode, int $page = 1 ): array {
		if ( $order_id <= 0 || $page < 1 || $page > 20 ) {
			return [ 'state' => 'invalid' ];
		}
		try {
			$request = List_Transactions::create();
			$request->set_filters(
				[
					'match'       => 'all',
					'order_id_is' => (string) $order_id,
					'test_mode'   => $test_mode ? 1 : 0,
				]
			);
			$request->set_page( $page );
			$request->set_page_size( 100 );
			$response = $request->send();
			$data     = $response instanceof Response ? $response->to_array() : [];
		} catch ( \Throwable $exception ) {
			return [ 'state' => 'unavailable' ];
		}
		$rows = $data['data'] ?? null;
		if ( ! is_array( $rows ) || array_values( $rows ) !== $rows || count( $rows ) > 100 ) {
			return [ 'state' => 'unavailable' ];
		}
		$ids = [];
		foreach ( $rows as $row ) {
			$id = is_array( $row ) ? ( $row['charge_id'] ?? null ) : null;
			if ( ! is_string( $id ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $id ) ) {
				return [ 'state' => 'unavailable' ];
			}
			$ids[ $id ] = $id;
		}
		return [
			'state'      => 'candidates',
			'coverage'   => 'unknown',
			'charge_ids' => array_values( $ids ),
			'has_more'   => 100 === count( $rows ),
			'next_page'  => 100 === count( $rows ) && $page < 20 ? $page + 1 : null,
		];
	}
	/**
	 * Match fetched original facts, without claiming capture or complete coverage.
	 *
	 * Legacy missing keys and changed site URLs require separate recovery evidence.
	 * The caller must supply trusted expected server context and recheck order
	 * identity before persistence. This method does not authorize a fetch or write.
	 *
	 * @param \WC_Order $order Canonical order under recovery.
	 * @param string    $charge_id Requested candidate ID.
	 * @param array     $charge Actual charge response, not list decoration.
	 * @param array     $context Trusted expected account/site/mode context.
	 * @return array Qualified identity facts, never a qualified monetary total.
	 */
	public function qualify_charge( \WC_Order $order, string $charge_id, array $charge, array $context ): array {
		$unknown = [ 'state' => 'unqualified' ];
		if ( 4 !== count( $context ) || 1 !== ( $context['version'] ?? null ) || ! is_string( $context['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $context['account_id'] ) || ! is_int( $context['site_id'] ?? null ) || $context['site_id'] <= 0 || ! is_bool( $context['test_mode'] ?? null ) ) {
			return $unknown;
		}
		$actual = $charge['wcpay_reporting_context'] ?? null;
		foreach ( $context as $key => $value ) {
			if ( ! is_array( $actual ) || ( $actual[ $key ] ?? null ) !== $value ) {
				return $unknown;
			}
		}
		$metadata = $charge['metadata'] ?? null;
		if ( $order->get_id() <= 0 || 'shop_order' !== $order->get_type() || '' === $order->get_order_key() || ! is_array( $metadata ) || ( $metadata['order_id'] ?? null ) !== (string) $order->get_id() || ( $metadata['order_key'] ?? null ) !== $order->get_order_key() || ( $metadata['site_url'] ?? null ) !== esc_url( get_site_url() ) ) {
			return $unknown;
		}
		if ( ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $charge_id ) || ( $charge['id'] ?? null ) !== $charge_id || ( $charge['livemode'] ?? null ) !== ! $context['test_mode'] || ! is_string( $charge['payment_intent'] ?? null ) || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $charge['payment_intent'] ) || ! is_int( $charge['amount'] ?? null ) || $charge['amount'] <= 0 || ! is_string( $charge['currency'] ?? null ) || ! preg_match( '/^[a-z]{3}$/D', $charge['currency'] ) ) {
			return $unknown;
		}
		return [
			'state'   => 'qualified_identity',
			'scope'   => [
				'account_id' => $context['account_id'],
				'site_id'    => $context['site_id'],
				'test_mode'  => $context['test_mode'],
				'order_id'   => $order->get_id(),
				'event_id'   => $charge['payment_intent'],
			],
			'receipt' => [
				'kind'              => 'intent_context',
				'charge_id'         => $charge_id,
				'original_amount'   => $charge['amount'],
				'original_currency' => strtoupper( $charge['currency'] ),
			],
		];
	}
}
