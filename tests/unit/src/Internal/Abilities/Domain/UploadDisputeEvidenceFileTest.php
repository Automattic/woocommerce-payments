<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\UploadDisputeEvidenceFile.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\UploadDisputeEvidenceFile;
use WCPay\Internal\Service\FileService;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\UploadDisputeEvidenceFile
 */
class UploadDisputeEvidenceFileTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/upload-dispute-evidence-file', UploadDisputeEvidenceFile::get_name() );
	}

	public function test_registration_args_has_non_destructive_write_annotations(): void {
		$args = UploadDisputeEvidenceFile::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['annotations']['destructive'] );
		$this->assertFalse( $args['meta']['annotations']['idempotent'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertSame( [ 'file_name', 'file_type', 'file_contents' ], $args['input_schema']['required'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
		$this->assertContains( 'application/pdf', $args['input_schema']['properties']['file_type']['enum'] );
	}

	public function test_execute_returns_error_when_input_missing(): void {
		$result = UploadDisputeEvidenceFile::execute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_file_input', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_input_not_array(): void {
		$result = UploadDisputeEvidenceFile::execute( null );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_file_input', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_file_type_unsupported(): void {
		$result = UploadDisputeEvidenceFile::execute(
			[
				'file_name'     => 'evil.exe',
				'file_type'     => 'application/x-msdownload',
				'file_contents' => base64_encode( 'data' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			]
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_unsupported_file_type', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_contents_not_base64(): void {
		$result = UploadDisputeEvidenceFile::execute(
			[
				'file_name'     => 'receipt.pdf',
				'file_type'     => 'application/pdf',
				'file_contents' => '@@@not-base64@@@',
			]
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_invalid_file_contents', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_file_too_large(): void {
		$reflection = new \ReflectionClass( UploadDisputeEvidenceFile::class );
		$max        = $reflection->getReflectionConstant( 'MAX_FILE_SIZE_BYTES' )->getValue();

		$result = UploadDisputeEvidenceFile::execute(
			[
				'file_name'     => 'receipt.pdf',
				'file_type'     => 'application/pdf',
				'file_contents' => base64_encode( str_repeat( 'A', $max + 1 ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			]
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_file_too_large', $result->get_error_code() );
	}

	public function test_execute_delegates_to_file_service(): void {
		$contents     = base64_encode( 'file-bytes' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$mock_service = $this->createMock( FileService::class );
		$mock_service->expects( $this->once() )->method( 'upload_evidence_file' )
			->with( $contents, 'receipt.pdf', 'application/pdf', 'dispute_evidence' )
			->willReturn( [ 'id' => 'file_1' ] );
		wcpay_get_test_container()->replace( FileService::class, $mock_service );
		try {
			$result = UploadDisputeEvidenceFile::execute(
				[
					'file_name'     => 'receipt.pdf',
					'file_type'     => 'application/pdf',
					'file_contents' => $contents,
				]
			);
		} finally {
			wcpay_get_test_container()->reset_all_replacements();
		}
		$this->assertSame( [ 'id' => 'file_1' ], $result );
	}
}
