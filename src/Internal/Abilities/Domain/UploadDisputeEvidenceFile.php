<?php
/**
 * Upload Dispute Evidence File ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Service\FileService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/upload-dispute-evidence-file` ability.
 *
 * Uploads a document (PDF/PNG/JPEG) to the Files API for use as dispute
 * evidence and returns the created file object. The file `id` it returns is
 * what `submit-dispute-evidence` expects in its document fields (receipt,
 * customer_communication, *_documentation, etc.). Not destructive (creates a
 * file, moves no money, changes no dispute) and not idempotent (each call
 * creates a new file). mcp.public is false: the whole evidence flow is
 * operator-gated, so this is not auto-invocable.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WCPay\Internal\Service\FileService::upload_evidence_file()
 */
class UploadDisputeEvidenceFile extends AbstractWCPayAbility implements AbilityDefinition {

	/**
	 * MIME types accepted as dispute evidence — mirrors the merchant UI's
	 * accept list (`client/disputes/new-evidence/file-upload-control.tsx`).
	 *
	 * @var string[]
	 */
	private const ACCEPTED_FILE_TYPES = [
		'application/pdf',
		'image/jpeg',
		'image/png',
	];

	/**
	 * Maximum decoded file size in bytes. Mirrors the dispute-evidence total
	 * size limit the merchant UI enforces (`client/disputes/new-evidence/index.tsx`).
	 *
	 * @var int
	 */
	private const MAX_FILE_SIZE_BYTES = 4500000;

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/upload-dispute-evidence-file';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Upload dispute evidence file', 'woocommerce-payments' ),
			'description'         => __( 'Upload a document (PDF, PNG, or JPEG) to use as dispute evidence. Returns a file object whose id can be passed to the document fields of woocommerce-payments/submit-dispute-evidence.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'required'             => [ 'file_name', 'file_type', 'file_contents' ],
				'properties'           => [
					'file_name'     => [
						'type'        => 'string',
						'description' => __( 'File name including extension (e.g. receipt.pdf).', 'woocommerce-payments' ),
					],
					'file_type'     => [
						'type'        => 'string',
						'enum'        => self::ACCEPTED_FILE_TYPES,
						'description' => __( 'File MIME type. One of application/pdf, image/jpeg, or image/png.', 'woocommerce-payments' ),
					],
					'file_contents' => [
						'type'        => 'string',
						'description' => __( 'Base64-encoded file contents. The decoded size must not exceed the dispute-evidence size limit.', 'woocommerce-payments' ),
					],
				],
				'additionalProperties' => false,
			],
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				],
				'show_in_rest' => true,
				'mcp'          => [
					'public' => false,
				],
			],
		];
	}

	/**
	 * Execute the upload-dispute-evidence-file ability.
	 *
	 * @param array<string,mixed> $input File parameters.
	 * @return array|\WP_Error File object on success, WP_Error on failure.
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) ) {
			$input = [];
		}

		$file_name     = ( isset( $input['file_name'] ) && is_string( $input['file_name'] ) ) ? sanitize_file_name( $input['file_name'] ) : '';
		$file_type     = ( isset( $input['file_type'] ) && is_string( $input['file_type'] ) ) ? $input['file_type'] : '';
		$file_contents = ( isset( $input['file_contents'] ) && is_string( $input['file_contents'] ) ) ? $input['file_contents'] : '';

		if ( '' === $file_name || '' === $file_type || '' === $file_contents ) {
			return new \WP_Error(
				'wcpay_missing_file_input',
				__( 'file_name, file_type, and file_contents are all required to upload a file.', 'woocommerce-payments' )
			);
		}

		if ( ! in_array( $file_type, self::ACCEPTED_FILE_TYPES, true ) ) {
			return new \WP_Error(
				'wcpay_unsupported_file_type',
				__( 'file_type must be one of application/pdf, image/jpeg, or image/png.', 'woocommerce-payments' )
			);
		}

		// Validate the payload is real base64 and enforce the size cap on the
		// decoded bytes before sending anything upstream.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- benign: validating caller-supplied base64.
		$decoded = base64_decode( $file_contents, true );
		if ( false === $decoded ) {
			return new \WP_Error(
				'wcpay_invalid_file_contents',
				__( 'file_contents must be base64-encoded.', 'woocommerce-payments' )
			);
		}

		if ( strlen( $decoded ) > self::MAX_FILE_SIZE_BYTES ) {
			return new \WP_Error(
				'wcpay_file_too_large',
				sprintf(
					/* translators: %d: maximum file size in bytes. */
					__( 'The file exceeds the %d-byte dispute-evidence size limit.', 'woocommerce-payments' ),
					self::MAX_FILE_SIZE_BYTES
				)
			);
		}

		return self::get_file_service()->upload_evidence_file( $file_contents, $file_name, $file_type, 'dispute_evidence' );
	}

	/**
	 * Resolve the shared file service from the container.
	 *
	 * @return FileService
	 */
	private static function get_file_service(): FileService {
		return \wcpay_get_container()->get( FileService::class );
	}
}
