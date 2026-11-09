<?php
/**
 * Class WC_Payments_Http_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\Jetpack\Connection\Manager;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Http unit tests.
 */
class WC_Payments_Http_Test extends WCPAY_UnitTestCase {

	/**
	 * A merchant's Connect click must never be held back by a remembered registration failure.
	 */
	public function test_start_connection_forces_the_registration_attempt() {
		$connection_manager = $this->createMock( Manager::class );
		$connection_manager->method( 'is_connected' )->willReturn( false );
		$connection_manager
			->expects( $this->once() )
			->method( 'try_registration' )
			->with( true, true )
			->willReturn( new WP_Error( 'siteurl_private_ip', 'The site URL is a private IP address.' ) );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'The site URL is a private IP address.' );

		( new WC_Payments_Http( $connection_manager ) )->start_connection( 'https://example.com/return' );
	}
}
