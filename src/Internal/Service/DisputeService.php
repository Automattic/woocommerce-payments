<?php
/**
 * Class DisputeService
 *
 * @package WooCommerce\Payments
 */

declare( strict_types=1 );

namespace WCPay\Internal\Service;

use WC_Payments_API_Client;

/**
 * Dispute write service shared by the dispute abilities. Thin typed wrapper
 * over the same WC_Payments_API_Client methods the REST controller calls, so
 * there is no logic drift between the agent path and the UI path.
 *
 * Note on error handling: each method returns a WP_Error when the api client
 * throws (caught below). But the api client may ALSO return a WP_Error without
 * throwing (e.g. an internal prefetch failure), which we pass straight through.
 * Callers must therefore handle both the thrown-and-caught path and a
 * passed-through WP_Error.
 */
class DisputeService {

	/**
	 * WooPayments API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * DisputeService constructor.
	 *
	 * @param WC_Payments_API_Client $api_client WooPayments API client.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Submit (or stage as draft) dispute evidence.
	 *
	 * @see \WCPay\Internal\Abilities\Domain\SubmitDisputeEvidence
	 *
	 * @param string $dispute_id Dispute ID (`dp_…`).
	 * @param array  $evidence   Evidence field map.
	 * @param bool   $submit     True submits to the card network (irreversible); false stages a draft.
	 * @param array  $metadata   Optional metadata.
	 * @return array|\WP_Error
	 */
	public function submit_evidence( string $dispute_id, array $evidence, bool $submit, array $metadata = [] ) {
		try {
			// May return a WP_Error without throwing (e.g. internal prefetch failure) — passed through to the caller.
			$result = $this->api_client->update_dispute( $dispute_id, $evidence, $submit, $metadata );
			if ( is_wp_error( $result ) ) {
				wc_get_logger()->error(
					sprintf( 'Dispute evidence submission returned an error for %s: %s', $dispute_id, $result->get_error_message() ),
					[ 'source' => 'woopayments-abilities' ]
				);
			}
			return $result;
		} catch ( \Throwable $e ) {
			wc_get_logger()->error(
				sprintf( 'Dispute evidence submission failed for %s: %s', $dispute_id, $e->getMessage() ),
				[ 'source' => 'woopayments-abilities' ]
			);
			return new \WP_Error( 'wcpay_dispute_evidence_failed', $e->getMessage(), [ 'exception' => $e ] );
		}
	}

	/**
	 * Accept (close) a dispute — concedes the dispute and forfeits the funds.
	 *
	 * @see \WCPay\Internal\Abilities\Domain\AcceptDispute
	 *
	 * @param string $dispute_id Dispute ID (`dp_…`).
	 * @return array|\WP_Error
	 */
	public function accept( string $dispute_id ) {
		try {
			// May return a WP_Error without throwing (e.g. internal prefetch failure) — passed through to the caller.
			$result = $this->api_client->close_dispute( $dispute_id );
			if ( is_wp_error( $result ) ) {
				wc_get_logger()->error(
					sprintf( 'Dispute close returned an error for %s: %s', $dispute_id, $result->get_error_message() ),
					[ 'source' => 'woopayments-abilities' ]
				);
			}
			return $result;
		} catch ( \Throwable $e ) {
			wc_get_logger()->error(
				sprintf( 'Dispute close failed for %s: %s', $dispute_id, $e->getMessage() ),
				[ 'source' => 'woopayments-abilities' ]
			);
			return new \WP_Error( 'wcpay_dispute_close_failed', $e->getMessage(), [ 'exception' => $e ] );
		}
	}
}
