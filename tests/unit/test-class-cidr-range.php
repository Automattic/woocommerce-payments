<?php
/**
 * Class CIDR_Range_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\CIDR_Range;

/**
 * CIDR_Range unit tests.
 */
class CIDR_Range_Test extends WCPAY_UnitTestCase {
	const IPV4_MOCK = '179.219.105.31';
	const IPV6_MOCK = '2804:14d:4c84:1317:3115:59af:377f:8e09';

	public function test_it_should_return_ipv4_range_prefix_16() {
		$range    = CIDR_Range::from_ipv4( self::IPV4_MOCK, 16 );
		$expected = [ '179.219.0.0', '179.219.255.255' ];

		$this->assertEquals( $expected, $range );
	}

	public function test_it_should_return_ipv6_range_prefix_32() {
		$range    = CIDR_Range::from_ipv6( self::IPV6_MOCK, 32 );
		$expected = [ '2804:14d::', '2804:14d:ffff:ffff:ffff:ffff:ffff:ffff' ];

		$this->assertEquals( $expected, $range );
	}

	public function test_it_should_return_ipv4_range_from_ip() {
		$range    = CIDR_Range::from_ip( self::IPV4_MOCK );
		$expected = [ '179.219.96.0', '179.219.111.255' ];

		$this->assertEquals( $expected, $range );
	}

	public function test_it_should_return_ipv6_range_from_ip() {
		$range    = CIDR_Range::from_ip( self::IPV6_MOCK );
		$expected = [ '2804:14d:4c84::', '2804:14d:4c84:ffff:ffff:ffff:ffff:ffff' ];

		$this->assertEquals( $expected, $range );
	}
}
