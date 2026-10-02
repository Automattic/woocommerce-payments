<?php
/**
 * Class SessionServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Session;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\SessionService;
use WCPAY_UnitTestCase;

/**
 * Order service unit tests.
 */
class SessionServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var SessionService
	 */
	private $sut;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_legacy_proxy = $this->createMock( LegacyProxy::class );
		$this->sut               = new SessionService( $this->mock_legacy_proxy );
	}

	public function test_get_with_wc_session_returns_value() {
		$mock_wc_session = $this->createMock( WC_Session::class );

		$this->mock_legacy_proxy->expects( $this->exactly( 2 ) )
			->method( 'call_function' )
			->with( 'WC' )
			->willReturn( (object) [ 'session' => $mock_wc_session ] );

		$mock_wc_session->expects( $this->exactly( 1 ) )
			->method( 'get' )
			->with( 'foo' )
			->willReturn( 'bar' );

		$result = $this->sut->get( 'foo' );
		$this->assertSame( 'bar', $result );
	}

	public function test_get_without_wc_session_returns_default_value() {
		$default_value = 'DEFAULT VALUE';

		$this->mock_legacy_proxy->expects( $this->exactly( 1 ) )
			->method( 'call_function' )
			->with( 'WC' )
			->willReturn( (object) [ 'session' => null ] );

		$result = $this->sut->get( 'foo', $default_value );
		$this->assertSame( $default_value, $result );
	}

	public function test_set_with_wc_session() {
		$mock_wc_session = $this->createMock( WC_Session::class );

		$this->mock_legacy_proxy->expects( $this->exactly( 2 ) )
			->method( 'call_function' )
			->with( 'WC' )
			->willReturn( (object) [ 'session' => $mock_wc_session ] );

		$mock_wc_session->expects( $this->exactly( 1 ) )
			->method( 'set' )
			->with( 'foo', 'bar' );

		$this->sut->set( 'foo', 'bar' );
	}

	public function test_set_without_wc_session() {
		$this->mock_legacy_proxy->expects( $this->exactly( 1 ) )
			->method( 'call_function' )
			->with( 'WC' )
			->willReturn( (object) [ 'session' => null ] );

		$this->sut->set( 'foo', 'bar' );
	}
}
