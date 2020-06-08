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

	/**
	 * Updates the order when the payment authorization has expired without being captured.
	 * It updates the order status, adds an order note, and updates the metadata so the "Capture" action
	 * button isn't displayed anymore.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function mark_payment_expired( $order ) {
		$order->update_meta_data( '_intention_status', 'canceled' );
		$order->update_status(
			'cancelled',
			sprintf(
				self::esc_interpolated_html(
				/* translators: %1: transaction ID of the payment */
					__( 'Payment authorization has <strong>expired</strong> (<code>%1$s</code>).', 'woocommerce-payments' ),
					[
						'strong' => '<strong>',
						'code'   => '<code>',
					]
				),
				$order->get_transaction_id()
			)
		);
	}

	/**
	 * Returns a charge_id for an "Order #" search term.
	 *
	 * @param string $term Search term.
	 *
	 * @return string The charge_id for the order, or empty if no order is found.
	 */
	public static function get_charge_id_from_search_term( $term ) {
		$order_term = 'Order #';
		if ( substr( $term, 0, strlen( $order_term ) ) === $order_term ) {
			$term_parts = explode( '#', $term, 2 );
			$order_id   = isset( $term_parts[1] ) ? $term_parts[1] : '';
		}
		if ( ! isset( $order_id ) || empty( $order_id ) ) {
			return '';
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return '';
		}

		return $order->get_meta( '_charge_id' );
	}

	/**
	 * Swaps "Order #" search terms with available charge_ids.
	 *
	 * @param string $search Search query.
	 *
	 * @return string Processed search string.
	 */
	public static function map_search_orders_to_charge_ids( $search ) {
		// Map Order # terms to the actual charge id to be used in the server.
		$terms = explode( ',', $search );
		$terms = array_map(
			function ( $term ) {
				$charge_id = self::get_charge_id_from_search_term( $term );
				if ( ! empty( $charge_id ) ) {
					return $charge_id;
				} else {
					return $term;
				}
			},
			$terms
		);
		return implode( ',', $terms );
	}
}
