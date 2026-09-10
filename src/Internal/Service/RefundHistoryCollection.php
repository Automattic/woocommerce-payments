<?php
/**
 * Retrieves refund pages without qualifying their accounting values.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Core\Server\Request\List_Charge_Refunds;
use WCPay\Core\Server\Response;

/**
 * Bounded traversal. A listed history is not a reconciled payment history.
 */
class RefundHistoryCollection {
	/**
	 * Retrieve a charge's refund list using its original payment mode.
	 *
	 * The worker supplies its queued expected server context for every page. This class
	 * does not persist data, schedule work, or qualify currency/balance evidence.
	 *
	 * @param string $charge_id Historical charge ID.
	 * @param bool   $test_mode Original payment mode.
	 * @param array  $expected_context Queued account/site/mode and protocol version.
	 * @return array Retrieval state and unqualified source records.
	 */
	public function collect( string $charge_id, bool $test_mode, array $expected_context ): array {
		if ( 4 !== count( $expected_context ) || 1 !== ( $expected_context['version'] ?? null ) || ! is_string( $expected_context['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $expected_context['account_id'] ) || ! is_int( $expected_context['site_id'] ?? null ) || $expected_context['site_id'] <= 0 || ( $expected_context['test_mode'] ?? null ) !== $test_mode ) {
			return $this->incomplete( 'refund_expected_context_invalid', [] );
		}
		$started_at                  = time();
		$result                      = $this->collect_pages( $charge_id, $test_mode, $expected_context );
		$result['reporting_context'] = $expected_context;
		$result['charge_id']         = $charge_id;
		$result['retrieval']         = [
			'started_at'   => $started_at,
			'completed_at' => time(),
		];
		if ( $result['retrieval']['completed_at'] < $started_at ) {
			$result['state']  = 'incomplete';
			$result['reason'] = 'refund_observation_clock_invalid';
		}
		return $result;
	}

	/**
	 * Traverse pages whose server provenance matches the queued context.
	 *
	 * @param string $charge_id Historical charge ID.
	 * @param bool   $test_mode Original payment mode.
	 * @param array  $expected_context Validated queued context.
	 * @return array Retrieval result.
	 */
	private function collect_pages( string $charge_id, bool $test_mode, array $expected_context ): array {
		$refunds = [];
		$cursor  = null;
		for ( $page = 0; $page < 20; ++$page ) {
			try {
				$request = List_Charge_Refunds::create();
				$request->set_charge( $charge_id );
				$request->set_test_mode( $test_mode );
				$request->set_expand_balance_transactions();
				$request->set_include_reporting_context();
				if ( null !== $cursor ) {
					$request->set_starting_after( $cursor );
				}
				$response = $request->send();
			} catch ( \Throwable $exception ) {
				return $this->incomplete( 'refund_retrieval_failed', $refunds );
			}
			if ( ! $response instanceof Response ) {
				return $this->incomplete( 'refund_response_invalid', $refunds );
			}
			$data    = $response->to_array();
			$context = $data['wcpay_reporting_context'] ?? null;
			foreach ( $expected_context as $key => $expected_value ) {
				if ( ! is_array( $context ) || ( $context[ $key ] ?? null ) !== $expected_value ) {
					return $this->incomplete( 'refund_context_mismatch', $refunds );
				}
			}
			if ( 'list' !== ( $data['object'] ?? null ) || ! is_bool( $data['has_more'] ?? null ) || ! is_array( $data['data'] ?? null ) || count( $data['data'] ) > 100 || array_values( $data['data'] ) !== $data['data'] ) {
				return $this->incomplete( 'refund_response_invalid', $refunds );
			}
			foreach ( $data['data'] as $refund ) {
				$id            = is_array( $refund ) ? ( $refund['id'] ?? null ) : null;
				$refund_charge = is_array( $refund ) ? ( $refund['charge'] ?? null ) : null;
				if ( ! is_string( $id ) || ! preg_match( '/^[a-z]+_[a-zA-Z0-9]+$/D', $id ) || 'refund' !== ( $refund['object'] ?? null ) || $charge_id !== $refund_charge ) {
					return $this->incomplete( 'refund_identity_invalid', $refunds );
				}
				if ( isset( $refunds[ $id ] ) ) {
					return $this->incomplete( 'refund_pagination_repeated', $refunds );
				}
				$refunds[ $id ] = $refund;
				$cursor         = $id;
			}
			if ( ! $data['has_more'] ) {
				return [
					'state'   => 'listed',
					'refunds' => array_values( $refunds ),
				];
			}
			if ( [] === $data['data'] ) {
				return $this->incomplete( 'refund_pagination_empty', $refunds );
			}
		}
		return $this->incomplete( 'refund_page_limit', $refunds );
	}

	/**
	 * Preserve partial source evidence without claiming a complete list.
	 *
	 * @param string $reason Reason traversal stopped.
	 * @param array  $refunds Unqualified source records collected so far.
	 * @return array Incomplete retrieval result.
	 */
	private function incomplete( string $reason, array $refunds ): array {
		return [
			'state'   => 'incomplete',
			'reason'  => $reason,
			'refunds' => array_values( $refunds ),
		];
	}
}
