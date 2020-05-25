<?php
/**
 * Class WC_Payments_Utils
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC Payments Utils class
 */
class WC_Payments_Utils {
	/**
	 * Mirrors JS's createInterpolateElement functionality.
	 * Returns a string where angle brackets expressions are replaced with unescaped html while the rest is escaped.
	 *
	 * @param string $string string to process.
	 * @param array  $element_map map of elements to not escape.
	 *
	 * @return string String where all of the html was escaped, except for the tags specified in element map.
	 */
	public static function esc_interpolated_html( $string, $element_map ) {
		// Regex to match string expressions wrapped in angle brackets.
		$tokenizer    = '/<(\/)?(\w+)\s*(\/)?>/';
		$string_queue = [];
		$token_queue  = [];
		$last_mapped  = true;
		// Start with a copy of the string.
		$processed = $string;

		// Match every angle bracket expression.
		while ( preg_match( $tokenizer, $processed, $matches ) ) {
			$matched = $matches[0];
			$token   = $matches[2];
			// Determine if the expression is closing (</a>) or self-closed (<img />).
			$is_closing     = ! empty( $matches[1] );
			$is_self_closed = ! empty( $matches[3] );

			// Split the string on the current matched token.
			$split = explode( $matched, $processed, 2 );
			if ( $last_mapped ) {
				// If the previous token was present in the element map, or we're at the start, put the string between it and the current token in the queue.
				$string_queue[] = $split[0];
			} else {
				// If the previous token was not found in the elements map, append it together with the string before the current token to the last item in the queue.
				$string_queue[ count( $string_queue ) - 1 ] .= $split[0];
			}
			// String is now the bit after the current token.
			$processed = $split[1];

			// Check if the current token is in the map.
			if ( isset( $element_map[ $token ] ) ) {
				$map_matched = preg_match( '/^<(\w+)(\s.+?)?\/?>$/', $element_map[ $token ], $map_matches );
				if ( ! $map_matches ) {
					// Should not happen with the properly formatted html as map value. Return the whole string escaped.
					return esc_html( $string );
				}
				// Add the matched token and its attributes into the token queue. It will not be escaped when constructing the final string.
				$tag   = $map_matches[1];
				$attrs = isset( $map_matches[2] ) ? $map_matches[2] : '';
				if ( $is_closing ) {
					$token_queue[] = '</' . $tag . '>';
				} elseif ( $is_self_closed ) {
					$token_queue[] = '<' . $tag . $attrs . '/>';
				} else {
					$token_queue[] = '<' . $tag . $attrs . '>';
				}

				// Mark the current token as found in the map.
				$last_mapped = true;
			} else {
				// Append the current token into the string queue. It will be escaped.
				$string_queue[ count( $string_queue ) - 1 ] .= $matched;
				// Mark the current token as not found in the map.
				$last_mapped = false;
			}
		}

		// No mapped tokens were found in the string, or token and string queues are not of equal length.
		// The latter should not happen - token queue and string queue should be the same length.
		if ( empty( $token_queue ) || count( $token_queue ) !== count( $string_queue ) ) {
			return esc_html( $string );
		}

		// Construct the final string by escaping the string queue values and not escaping the token queue.
		$result = '';
		while ( ! empty( $token_queue ) ) {
			$result .= esc_html( array_shift( $string_queue ) ) . array_shift( $token_queue );
		}
		$result .= esc_html( $processed );

		return $result;
	}

	/**
	 * Returns an API-ready amount based on a currency.
	 *
	 * @param float  $amount   The base amount.
	 * @param string $currency The currency for the amount.
	 *
	 * @return int The amount in cents.
	 */
	public static function prepare_amount( $amount, $currency = 'USD' ) {
		return round( (float) $amount * 100 );
	}

	/**
	 * Verifies whether a certain ZIP code is valid for the US, incl. 4-digit extensions.
	 *
	 * @param string $zip The ZIP code to verify.
	 * @return boolean
	 */
	public static function is_valid_us_zip_code( $zip ) {
		return ! empty( $zip ) && preg_match( '/^\d{5,5}(-\d{4,4})?$/', $zip );
	}
}
