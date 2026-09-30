<?php
/**
 * Class FileService
 *
 * @package WooCommerce\Payments
 */

declare( strict_types=1 );

namespace WCPay\Internal\Service;

use WC_Payments_API_Client;

/**
 * File-upload service shared by the file-upload ability. Thin typed wrapper
 * over the same `WC_Payments_API_Client` Files API call the dispute-evidence
 * UI uses, so the agent upload path and the UI upload path do not drift.
 */
class FileService {

	/**
	 * WooPayments API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * FileService constructor.
	 *
	 * @param WC_Payments_API_Client $api_client WooPayments API client.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Upload a dispute-evidence file from base64 contents and return the file object.
	 *
	 * @see \WCPay\Internal\Abilities\Domain\UploadDisputeEvidenceFile
	 *
	 * @param string $file_content_base64 Base64-encoded file contents.
	 * @param string $file_name           File name including extension.
	 * @param string $file_type           File MIME type (e.g. `image/png`).
	 * @param string $purpose             Stripe file purpose. Defaults to `dispute_evidence`.
	 * @return array|\WP_Error File object (includes the file `id`) on success, WP_Error on failure.
	 */
	public function upload_evidence_file( string $file_content_base64, string $file_name, string $file_type, string $purpose = 'dispute_evidence' ) {
		try {
			return $this->api_client->upload_evidence_file_contents( $file_content_base64, $file_name, $file_type, $purpose, false );
		} catch ( \Throwable $e ) {
			wc_get_logger()->error(
				sprintf( 'Dispute evidence file upload failed (%s): %s', $file_name, $e->getMessage() ),
				[ 'source' => 'woopayments-abilities' ]
			);
			return new \WP_Error( 'wcpay_file_upload_failed', $e->getMessage(), [ 'exception' => $e ] );
		}
	}
}
