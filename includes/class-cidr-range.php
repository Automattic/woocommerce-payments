<?php
/**
 * Class CIDR_Range
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class for CIDR range.
 */
class CIDR_Range {
	const IPV4_PREFIX = 20;
	const IPV6_PREFIX = 48;

	/**
	 * Returns the IPv6 range for a given IPv6 address and a CIDR prefix.
	 *
	 * @see https://stackoverflow.com/a/61883041
	 *
	 * @param string $ip IPv6 address.
	 * @param int    $prefix CIDR prefix. Defaults to 48 that should affect 65,793 IPs.
	 *
	 * @return array
	 */
	public static function from_ipv6( string $ip, int $prefix = self::IPV6_PREFIX ) {
		try {
			// Parse the address into a binary string.
			$address_binary = inet_pton( $ip );

			// Convert the binary string to a string with hexadecimal characters.
			$address_hexa = bin2hex( $address_binary );

			// Calculate the number of 'flexible' bits.
			$flexbits = 128 - $prefix;

			// Build the hexadecimal strings of the first and last addresses.
			$address_hexa_first = $address_hexa;
			$address_hexa_last  = $address_hexa;

			// We start at the end of the string (which is always 32 characters long).
			$pos = 31;

			while ( $flexbits > 0 ) {
				// Get the characters at this position and convert them to an integer.
				$char_pos_first = hexdec( substr( $address_hexa_first, $pos, 1 ) );
				$char_pos_last  = hexdec( substr( $address_hexa_last, $pos, 1 ) );

				// First address: calculate the subnet mask. min() prevents the comparison from being negative.
				$mask = 0xf << ( min( 4, $flexbits ) );

				// AND the original against its mask.
				$address_int_first = $char_pos_first & $mask;

				// Last address: OR it with (2 ^ flexbits) - 1, with flexbits limited to 4 at a time.
				$address_int_last = $char_pos_last | ( pow( 2, min( 4, $flexbits ) ) - 1 );

				// Convert them back to hexadecimal characters.
				$char_hexa_first = dechex( $address_int_first );
				$char_hexa_last  = dechex( $address_int_last );

				// And put those character back in their strings.
				$address_hexa_first = substr_replace( $address_hexa_first, $char_hexa_first, $pos, 1 );
				$address_hexa_last  = substr_replace( $address_hexa_last, $char_hexa_last, $pos, 1 );

				// We processed one nibble, move to previous position.
				$flexbits -= 4;
				--$pos;
			}

			// Convert the hexadecimal strings to a binary string and create an IPv6 address.
			$address_string_first = inet_ntop( hex2bin( $address_hexa_first ) );
			$address_string_last  = inet_ntop( hex2bin( $address_hexa_last ) );

			return [ $address_string_first, $address_string_last ];
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Returns the IPv4 range for a given IPv4 address and a CIDR prefix.
	 *
	 * @see https://stackoverflow.com/a/41673400
	 *
	 * @param string $ip IPv4 address.
	 * @param int    $prefix CIDR prefix. Defaults to 20 that should affect 4,096 IPs.
	 *
	 * @return array
	 */
	public static function from_ipv4( string $ip, int $prefix = self::IPV4_PREFIX ) {
		try {
			$first_ip = long2ip( ( ip2long( $ip ) ) & ( ( -1 << ( 32 - (int) $prefix ) ) ) );
			$last_ip  = long2ip( ( ip2long( $first_ip ) ) + pow( 2, ( 32 - (int) $prefix ) ) - 1 );

			return [ $first_ip, $last_ip ];
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Returns the IP range for a given IPv4 or IPv6 address and a CIDR prefix.
	 *
	 * @param string $ip IP address.
	 * @param array  $custom_prefixes Custom CIDR prefixes for IPv4 and IPv6.
	 *
	 * @return array
	 */
	public static function from_ip( string $ip, array $custom_prefixes = [] ) {
		$prefixes = array_merge(
			[
				'ipv4' => self::IPV4_PREFIX,
				'ipv6' => self::IPV6_PREFIX,
			],
			$custom_prefixes
		);

		$is_ipv4 = strpos( $ip, '.' ) !== false;
		$prefix  = $is_ipv4 ? $prefixes['ipv4'] : $prefixes['ipv6'];

		if ( $is_ipv4 ) {
			return self::from_ipv4( $ip, $prefix );
		}

		return self::from_ipv6( $ip, $prefix );
	}
}
