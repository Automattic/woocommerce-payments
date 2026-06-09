<?php
/**
 * Class DisputeServiceTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;
use WCPay\Internal\Service\DisputeService;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \WCPay\Internal\Service\DisputeService
 */
class DisputeServiceTest extends WCPAY_UnitTestCase {

	/**
	 * @var DisputeService
	 */
	private $sut;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	protected function setUp(): void {
		parent::setUp();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->sut             = new DisputeService( $this->mock_api_client );
	}

	public function test_submit_evidence_forwards_to_api_client(): void {
		$evidence = [ 'customer_communication' => 'file_1' ];
		$metadata = [ 'k' => 'v' ];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_dispute' )
			->with( 'dp_1', $evidence, true, $metadata )
			->willReturn(
				[
					'id'     => 'dp_1',
					'status' => 'under_review',
				]
			);

		$result = $this->sut->submit_evidence( 'dp_1', $evidence, true, $metadata );

		$this->assertSame(
			[
				'id'     => 'dp_1',
				'status' => 'under_review',
			],
			$result
		);
	}

	public function test_accept_forwards_to_api_client(): void {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'close_dispute' )
			->with( 'dp_2' )
			->willReturn(
				[
					'id'     => 'dp_2',
					'status' => 'lost',
				]
			);

		$result = $this->sut->accept( 'dp_2' );

		$this->assertSame(
			[
				'id'     => 'dp_2',
				'status' => 'lost',
			],
			$result
		);
	}

	public function test_submit_evidence_returns_wp_error_when_api_client_throws(): void {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_dispute' )
			->willThrowException( new API_Exception( 'boom', 'test_err', 500 ) );

		$result = $this->sut->submit_evidence( 'dp_1', [ 'customer_communication' => 'file_1' ], true );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_dispute_evidence_failed', $result->get_error_code() );
	}

	public function test_accept_returns_wp_error_when_api_client_throws(): void {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'close_dispute' )
			->willThrowException( new API_Exception( 'boom', 'test_err', 500 ) );

		$result = $this->sut->accept( 'dp_2' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_dispute_close_failed', $result->get_error_code() );
	}

	public function test_submit_evidence_passes_through_wp_error_from_api_client(): void {
		$wp_error = new \WP_Error( 'wcpay_prefetch_failed', 'prefetch failed' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_dispute' )
			->willReturn( $wp_error );

		$result = $this->sut->submit_evidence( 'dp_1', [ 'customer_communication' => 'file_1' ], true );

		$this->assertSame( $wp_error, $result );
	}

	public function test_accept_passes_through_wp_error_from_api_client(): void {
		$wp_error = new \WP_Error( 'wcpay_prefetch_failed', 'prefetch failed' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'close_dispute' )
			->willReturn( $wp_error );

		$result = $this->sut->accept( 'dp_2' );

		$this->assertSame( $wp_error, $result );
	}
}
