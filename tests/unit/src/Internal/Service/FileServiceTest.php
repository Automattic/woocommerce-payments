<?php
/**
 * Class FileServiceTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;
use WCPay\Internal\Service\FileService;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \WCPay\Internal\Service\FileService
 */
class FileServiceTest extends WCPAY_UnitTestCase {

	/**
	 * @var FileService
	 */
	private $sut;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	protected function setUp(): void {
		parent::setUp();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->sut             = new FileService( $this->mock_api_client );
	}

	public function test_upload_evidence_file_forwards_to_api_client(): void {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'upload_evidence_file_contents' )
			->with( 'YmFzZTY0', 'receipt.pdf', 'application/pdf', 'dispute_evidence', false )
			->willReturn( [ 'id' => 'file_1' ] );

		$result = $this->sut->upload_evidence_file( 'YmFzZTY0', 'receipt.pdf', 'application/pdf' );

		$this->assertSame( [ 'id' => 'file_1' ], $result );
	}

	public function test_upload_evidence_file_returns_wp_error_when_api_client_throws(): void {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'upload_evidence_file_contents' )
			->willThrowException( new API_Exception( 'boom', 'test_err', 500 ) );

		$result = $this->sut->upload_evidence_file( 'YmFzZTY0', 'receipt.pdf', 'application/pdf' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_file_upload_failed', $result->get_error_code() );
	}
}
