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
	 * Max depth used when processing arrays, for example in redact_array.
	 */
	const MAX_ARRAY_DEPTH = 10;

	/**
	 * Order meta data key that holds the currency of order's intent transaction.
	 */
	const ORDER_INTENT_CURRENCY_META_KEY = '_wcpay_intent_currency';

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
		$conversion_rate = 100;

		if ( in_array( strtolower( $currency ), self::zero_decimal_currencies(), true ) ) {
			$conversion_rate = 1;
		}

		return round( (float) $amount * $conversion_rate );
	}

	/**
	 * Interprets amount from Stripe API.
	 *
	 * @param int    $amount   The amount returned by Stripe API.
	 * @param string $currency The currency we get from Stripe API for the amount.
	 *
	 * @return float The interpreted amount.
	 */
	public static function interpret_stripe_amount( int $amount, string $currency = 'usd' ): float {
		$conversion_rate = 100;

		if ( in_array( $currency, self::zero_decimal_currencies(), true ) ) {
			$conversion_rate = 1;
		}

		return (float) $amount / $conversion_rate;
	}

	/**
	 * List of currencies supported by Stripe, the amounts for which are already in the smallest unit.
	 * Sourced directly from https://stripe.com/docs/currencies#zero-decimal
	 *
	 * @return array $currencies
	 */
	public static function zero_decimal_currencies() {
		return [
			'bif', // Burundian Franc.
			'clp', // Chilean Peso.
			'djf', // Djiboutian Franc.
			'gnf', // Guinean Franc.
			'jpy', // Japanese Yen.
			'kmf', // Comorian Franc.
			'krw', // South Korean Won.
			'mga', // Malagasy Ariary.
			'pyg', // Paraguayan Guaraní.
			'rwf', // Rwandan Franc.
			'ugx', // Ugandan Shilling.
			'vnd', // Vietnamese Đồng.
			'vuv', // Vanuatu Vatu.
			'xaf', // Central African Cfa Franc.
			'xof', // West African Cfa Franc.
			'xpf', // Cfp Franc.
		];
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
	 * Returns the charge_id for an "Order #" search term
	 * or all charge_ids for a "Subscription #" search term.
	 *
	 * @param string $term Search term.
	 *
	 * @return array The charge_id(s) for the order or subscription.
	 */
	public static function get_charge_ids_from_search_term( $term ) {
		$order_term = __( 'Order #', 'woocommerce-payments' );
		if ( substr( $term, 0, strlen( $order_term ) ) === $order_term ) {
			$term_parts = explode( '#', $term, 2 );
			$order_id   = isset( $term_parts[1] ) ? $term_parts[1] : '';
			$order      = wc_get_order( $order_id );
			if ( $order ) {
				return [ $order->get_meta( '_charge_id' ) ];
			}
		}

		$subscription_term = __( 'Subscription #', 'woocommerce-payments' );
		if ( function_exists( 'wcs_get_subscription' ) && substr( $term, 0, strlen( $subscription_term ) ) === $subscription_term ) {
			$term_parts      = explode( '#', $term, 2 );
			$subscription_id = isset( $term_parts[1] ) ? $term_parts[1] : '';
			$subscription    = wcs_get_subscription( $subscription_id );
			if ( $subscription ) {
				return array_map(
					function ( $order ) {
						return $order->get_meta( '_charge_id' );
					},
					$subscription->get_related_orders( 'all' )
				);
			}
		}

		return [];
	}

	/**
	 * Swaps "Order #" and "Subscription #" search terms with available charge_ids.
	 *
	 * @param array $search Raw search query terms.
	 *
	 * @return array Processed search strings.
	 */
	public static function map_search_orders_to_charge_ids( $search ) {
		// Map Order # and Subscription # terms to the actual charge IDs to be used in the server.
		$terms = [];
		foreach ( $search as $term ) {
			$charge_ids = self::get_charge_ids_from_search_term( $term );
			if ( ! empty( $charge_ids ) ) {
				foreach ( $charge_ids as $charge_id ) {
					$terms[] = $charge_id;
				}
			} else {
				$terms[] = $term;
			}
		}
		return $terms;
	}

	/**
	 * Extract the billing details from the WC order
	 *
	 * @param WC_Order $order Order to extract the billing details from.
	 *
	 * @return array
	 */
	public static function get_billing_details_from_order( $order ) {
		$billing_details = [
			'address' => [
				'city'        => $order->get_billing_city(),
				'country'     => $order->get_billing_country(),
				'line1'       => $order->get_billing_address_1(),
				'line2'       => $order->get_billing_address_2(),
				'postal_code' => $order->get_billing_postcode(),
				'state'       => $order->get_billing_state(),
			],
			'email'   => $order->get_billing_email(),
			'name'    => trim( $order->get_formatted_billing_full_name() ),
			'phone'   => $order->get_billing_phone(),
		];

		$remove_empty_entries = function ( $value ) {
			return ! empty( $value );
		};

		$billing_details['address'] = array_filter( $billing_details['address'], $remove_empty_entries );
		return array_filter( $billing_details, $remove_empty_entries );
	}

	/**
	 * Redacts the provided array, removing the sensitive information, and limits its depth to LOG_MAX_RECURSION.
	 *
	 * @param array   $array The array to redact.
	 * @param array   $keys_to_redact The keys whose values need to be redacted.
	 * @param integer $level The current recursion level.
	 *
	 * @return array The redacted array.
	 */
	public static function redact_array( $array, $keys_to_redact, $level = 0 ) {
		if ( is_object( $array ) ) {
			// TODO: if we ever want to log objects, they could implement a method returning an array or a string.
			return get_class( $array ) . '()';
		}

		if ( ! is_array( $array ) ) {
			return $array;
		}

		if ( $level >= self::MAX_ARRAY_DEPTH ) {
			return '(recursion limit reached)';
		}

		$result = [];

		foreach ( $array as $key => $value ) {
			if ( in_array( $key, $keys_to_redact, true ) ) {
				$result[ $key ] = '(redacted)';
				continue;
			}

			$result[ $key ] = self::redact_array( $value, $keys_to_redact, $level + 1 );
		}

		return $result;
	}

	/**
	 * Gets order intent currency from meta data or order currency.
	 *
	 * @param WC_Order $order The order whose intent currency we want to get.
	 *
	 * @return string The currency.
	 */
	public static function get_order_intent_currency( WC_Order $order ): string {
		$intent_currency = $order->get_meta( self::ORDER_INTENT_CURRENCY_META_KEY );
		if ( ! empty( $intent_currency ) ) {
			return $intent_currency;
		}
		return $order->get_currency();
	}

	/**
	 * Saves intent currency in order meta data.
	 *
	 * @param WC_Order $order The order whose intent currency we want to set.
	 * @param string   $currency The intent currency.
	 */
	public static function set_order_intent_currency( WC_Order $order, string $currency ) {
		$order->update_meta_data( self::ORDER_INTENT_CURRENCY_META_KEY, $currency );
	}

	/**
	 * Checks if the currently displayed page is the WooCommerce Payments
	 * settings page or a payment method settings page.
	 *
	 * @return bool
	 */
	public static function is_payments_settings_page(): bool {
		global $current_section, $current_tab;

		return (
			is_admin()
			&& $current_tab && $current_section
			&& 'checkout' === $current_tab
			&& 0 === strpos( $current_section, 'woocommerce_payments' )
		);
	}
}
