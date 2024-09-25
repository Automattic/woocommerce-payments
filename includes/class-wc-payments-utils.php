<?php
/**
 * Class WC_Payments_Utils
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\{ Amount_Too_Small_Exception, API_Exception, Connection_Exception };
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

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
	 * Force disconnected flag name.
	 */
	const FORCE_DISCONNECTED_FLAG_NAME = 'wcpaydev_force_disconnected';

	/**
	 * The Store API route patterns that should be handled by the WooPay session handler.
	 */
	const STORE_API_ROUTE_PATTERNS = [
		'@^\/wc\/store(\/v[\d]+)?\/cart$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/add-item$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/remove-item$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/apply-coupon$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/remove-coupon$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/select-shipping-rate$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/update-customer$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/update-item$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/extensions$@',
		'@^\/wc\/store(\/v[\d]+)?\/checkout\/(?P<id>[\d]+)@',
		'@^\/wc\/store(\/v[\d]+)?\/checkout$@',
		'@^\/wc\/store(\/v[\d]+)?\/order\/(?P<id>[\d]+)@',
		// The route below is not a Store API route. However, this REST endpoint is used by WooPay to indirectly reach the Store API.
		// By adding it to this list, we're able to identify the user and load the correct session for this route.
		'@^\/payments\/woopay\/session$@',
	];

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
	public static function prepare_amount( $amount, $currency = Currency_Code::UNITED_STATES_DOLLAR ): int {
		$conversion_rate = 100;

		if ( self::is_zero_decimal_currency( strtolower( $currency ) ) ) {
			$conversion_rate = 1;
		}

		return (int) round( (float) $amount * $conversion_rate );
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

		if ( self::is_zero_decimal_currency( $currency ) ) {
			$conversion_rate = 1;
		}

		return (float) $amount / $conversion_rate;
	}

	/**
	 * Interprets an exchange rate from the Stripe API.
	 *
	 * @param float  $exchange_rate        The exchange rate returned from the stripe API.
	 * @param string $presentment_currency The currency the customer was charged in.
	 * @param string $base_currency        The Stripe account currency.
	 * @return float
	 */
	public static function interpret_string_exchange_rate(
		float $exchange_rate,
		string $presentment_currency,
		string $base_currency
	): float {
		$is_presentment_currency_zero_decimal = self::is_zero_decimal_currency( strtolower( $presentment_currency ) );
		$is_base_currency_zero_decimal        = self::is_zero_decimal_currency( strtolower( $base_currency ) );

		if ( $is_presentment_currency_zero_decimal && ! $is_base_currency_zero_decimal ) {
			return $exchange_rate / 100;
		} elseif ( ! $is_presentment_currency_zero_decimal && $is_base_currency_zero_decimal ) {
			return $exchange_rate * 100;
		} else {
			return $exchange_rate;
		}
	}

	/**
	 * Check whether a given currency is in the list of zero-decimal currencies supported by Stripe.
	 *
	 * @param string $currency The currency code.
	 *
	 * @return bool
	 */
	public static function is_zero_decimal_currency( string $currency ): bool {
		if ( in_array( strtolower( $currency ), self::zero_decimal_currencies(), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * List of currencies supported by Stripe, the amounts for which are already in the smallest unit.
	 * Sourced directly from https://stripe.com/docs/currencies#zero-decimal
	 *
	 * @return string[]
	 */
	public static function zero_decimal_currencies(): array {
		return [
			strtolower( Currency_Code::BURUNDIAN_FRANC ), // Burundian Franc.
			strtolower( Currency_Code::CHILEAN_PESO ), // Chilean Peso.
			strtolower( Currency_Code::DJIBOUTIAN_FRANC ), // Djiboutian Franc.
			strtolower( Currency_Code::GUINEAN_FRANC ), // Guinean Franc.
			strtolower( Currency_Code::JAPANESE_YEN ), // Japanese Yen.
			strtolower( Currency_Code::COMORIAN_FRANC ), // Comorian Franc.
			strtolower( Currency_Code::SOUTH_KOREAN_WON ), // South Korean Won.
			strtolower( Currency_Code::MALAGASY_ARIARY ), // Malagasy Ariary.
			strtolower( Currency_Code::PARAGUAYAN_GUARANI ), // Paraguayan Guaraní.
			strtolower( Currency_Code::RWANDAN_FRANC ), // Rwandan Franc.
			strtolower( Currency_Code::VIETNAMESE_DONG ), // Vietnamese Đồng.
			strtolower( Currency_Code::VANUATU_VATU ), // Vanuatu Vatu.
			strtolower( Currency_Code::CENTRAL_AFRICAN_CFA_FRANC ), // Central African CFA Franc.
			strtolower( Currency_Code::WEST_AFRICAN_CFA_FRANC ), // West African CFA Franc.
			strtolower( Currency_Code::CFP_FRANC ), // CFP Franc.
		];
	}

	/**
	 * List of countries enabled for Stripe platform account. See also this URL:
	 * https://woocommerce.com/document/woopayments/compatibility/countries/#supported-countries
	 *
	 * @return string[]
	 */
	public static function supported_countries(): array {
		return [
			Country_Code::UNITED_ARAB_EMIRATES => __( 'United Arab Emirates', 'woocommerce-payments' ),
			Country_Code::AUSTRIA              => __( 'Austria', 'woocommerce-payments' ),
			Country_Code::AUSTRALIA            => __( 'Australia', 'woocommerce-payments' ),
			Country_Code::BELGIUM              => __( 'Belgium', 'woocommerce-payments' ),
			Country_Code::BULGARIA             => __( 'Bulgaria', 'woocommerce-payments' ),
			Country_Code::CANADA               => __( 'Canada', 'woocommerce-payments' ),
			Country_Code::SWITZERLAND          => __( 'Switzerland', 'woocommerce-payments' ),
			Country_Code::CYPRUS               => __( 'Cyprus', 'woocommerce-payments' ),
			Country_Code::CZECHIA              => __( 'Czech Republic', 'woocommerce-payments' ),
			Country_Code::GERMANY              => __( 'Germany', 'woocommerce-payments' ),
			Country_Code::DENMARK              => __( 'Denmark', 'woocommerce-payments' ),
			Country_Code::ESTONIA              => __( 'Estonia', 'woocommerce-payments' ),
			Country_Code::FINLAND              => __( 'Finland', 'woocommerce-payments' ),
			Country_Code::SPAIN                => __( 'Spain', 'woocommerce-payments' ),
			Country_Code::FRANCE               => __( 'France', 'woocommerce-payments' ),
			Country_Code::CROATIA              => __( 'Croatia', 'woocommerce-payments' ),
			Country_Code::JAPAN                => __( 'Japan', 'woocommerce-payments' ),
			Country_Code::LUXEMBOURG           => __( 'Luxembourg', 'woocommerce-payments' ),
			Country_Code::UNITED_KINGDOM       => __( 'United Kingdom (UK)', 'woocommerce-payments' ),
			Country_Code::GREECE               => __( 'Greece', 'woocommerce-payments' ),
			Country_Code::HONG_KONG            => __( 'Hong Kong', 'woocommerce-payments' ),
			Country_Code::HUNGARY              => __( 'Hungary', 'woocommerce-payments' ),
			Country_Code::IRELAND              => __( 'Ireland', 'woocommerce-payments' ),
			Country_Code::ITALY                => __( 'Italy', 'woocommerce-payments' ),
			Country_Code::LITHUANIA            => __( 'Lithuania', 'woocommerce-payments' ),
			Country_Code::LATVIA               => __( 'Latvia', 'woocommerce-payments' ),
			Country_Code::MALTA                => __( 'Malta', 'woocommerce-payments' ),
			Country_Code::NETHERLANDS          => __( 'Netherlands', 'woocommerce-payments' ),
			Country_Code::NORWAY               => __( 'Norway', 'woocommerce-payments' ),
			Country_Code::NEW_ZEALAND          => __( 'New Zealand', 'woocommerce-payments' ),
			Country_Code::POLAND               => __( 'Poland', 'woocommerce-payments' ),
			Country_Code::PORTUGAL             => __( 'Portugal', 'woocommerce-payments' ),
			Country_Code::ROMANIA              => __( 'Romania', 'woocommerce-payments' ),
			Country_Code::SWEDEN               => __( 'Sweden', 'woocommerce-payments' ),
			Country_Code::SLOVENIA             => __( 'Slovenia', 'woocommerce-payments' ),
			Country_Code::SLOVAKIA             => __( 'Slovakia', 'woocommerce-payments' ),
			Country_Code::SINGAPORE            => __( 'Singapore', 'woocommerce-payments' ),
			Country_Code::UNITED_STATES        => __( 'United States (US)', 'woocommerce-payments' ),
			Country_Code::PUERTO_RICO          => __( 'Puerto Rico', 'woocommerce-payments' ),
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
			$term_parts = explode( $order_term, $term, 2 );
			$order_id   = isset( $term_parts[1] ) ? $term_parts[1] : '';
			$order      = wc_get_order( $order_id );
			if ( $order ) {
				return [ $order->get_meta( '_charge_id' ) ];
			}
		}

		$subscription_term = __( 'Subscription #', 'woocommerce-payments' );
		if ( function_exists( 'wcs_get_subscription' ) && substr( $term, 0, strlen( $subscription_term ) ) === $subscription_term ) {
			$term_parts      = explode( $subscription_term, $term, 2 );
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
	 * Redacts the provided array, removing the sensitive information, and limits its depth to LOG_MAX_RECURSION.
	 *
	 * @param object|array $array          The array to redact.
	 * @param array        $keys_to_redact The keys whose values need to be redacted.
	 * @param integer      $level          The current recursion level.
	 *
	 * @return string|array The redacted array.
	 */
	public static function redact_array( $array, array $keys_to_redact, int $level = 0 ) {
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
	 * Apply a callback on every value in an array, regardless of the number of array dimensions.
	 *
	 * @param array    $array    The array to map.
	 * @param callable $callback The callback to apply.
	 *
	 * @return array The mapped array.
	 */
	public static function array_map_recursive( array $array, callable $callback ): array {
		foreach ( $array as $key => $value ) {
			if ( \is_array( $value ) ) {
				$value = self::array_map_recursive( $value, $callback );
			} else {
				$value = $callback( $value, $key, $array );
			}

			$array[ $key ] = $value;
		}

		return $array;
	}

	/**
	 * Filter a multidimensional array.
	 *
	 * It works just like array_filter, but it also filters multidimensional/nested arrays, regardless of depth.
	 *
	 * @see https://www.php.net/manual/en/function.array-filter.php
	 *
	 * @param array         $array    The array to filter.
	 * @param callable|null $callback Optional. The callback to apply.
	 *                                The callback should return true to keep the value, false otherwise.
	 *                                If no callback is provided, all non-truthy values will be removed.
	 *
	 * @return array The filtered array.
	 */
	public static function array_filter_recursive( array $array, callable $callback = null ): array {
		foreach ( $array as $key => &$value ) { // Mind the use of a reference.
			if ( \is_array( $value ) ) {
				$value = self::array_filter_recursive( $value, $callback );
				if ( ! $value ) {
					unset( $array[ $key ] );
				}
			} elseif ( ! is_null( $callback ) ) {
				if ( ! $callback( $value ) ) {
					unset( $array[ $key ] );
				}
			} elseif ( ! $value ) {
				unset( $array[ $key ] );
			}
		}
		unset( $value ); // Kill the reference to avoid memory leaks.

		return $array;
	}

	/**
	 * Merge arrays recursively like array_merge.
	 *
	 * This method merges any number of arrays recursively, replacing entries with string keys with values from latter arrays.
	 * If the entry or the next value to be assigned is an array, then it automagically treats both arguments as an array.
	 * Numeric entries are appended, not replaced, but only if they are unique.
	 * If the entry or the next value to be assigned is null, it will not overwrite non-null entries.
	 *
	 * Note that this does not work the same as array_merge_recursive:
	 * array_merge_recursive has a behavior that is not quite helpful, especially around overwriting values
	 * with the same string keys (it will not overwrite, but gather them in an array).
	 *
	 * @link http://www.php.net/manual/en/function.array-merge-recursive.php#96201 (initial source)
	 *
	 * @return array
	 */
	public static function array_merge_recursive_distinct(): array {
		$arrays = func_get_args();
		$base   = array_shift( $arrays );

		// Make sure the base is an array.
		if ( ! is_array( $base ) ) {
			$base = empty( $base ) ? [] : [ $base ];
		}

		foreach ( $arrays as $append ) {
			// Coerce single values to array.
			if ( ! is_array( $append ) ) {
				$append = [ $append ];
			}

			foreach ( $append as $key => $value ) {
				if ( ! array_key_exists( $key, $base ) && ! is_numeric( $key ) ) {
					$base[ $key ] = $value;
					continue;
				}

				// We include null values only when using string keys that don't exist in the base.
				// For the rest of the scenarios, null entries are ignored.
				if ( is_null( $value ) ) {
					continue;
				}

				if ( is_array( $value ) || ( array_key_exists( $key, $base ) && is_array( $base[ $key ] ) ) ) {
					if ( ! isset( $base[ $key ] ) ) {
						$base[ $key ] = [];
					}
					$base[ $key ] = self::array_merge_recursive_distinct( $base[ $key ], $value );
				} elseif ( is_numeric( $key ) ) {
					if ( ! in_array( $value, $base, true ) ) {
						$base[] = $value;
					}
				} else {
					$base[ $key ] = $value;
				}
			}
		}

		return $base;
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
	 * Checks if the currently displayed page is the WooPayments
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

	/**
	 * Checks if the currently displayed page is the WooPayments onboarding page.
	 *
	 * @return bool
	 */
	public static function is_onboarding_page(): bool {
		return (
			is_admin()
			&& isset( $_GET['page'] ) && 'wc-admin' === $_GET['page']  // phpcs:ignore WordPress.Security.NonceVerification
			&& isset( $_GET['path'] ) && '/payments/onboarding' === $_GET['path']  // phpcs:ignore WordPress.Security.NonceVerification
		);
	}

	/**
	 * Converts a locale to the closest supported by Stripe.js.
	 *
	 * Stripe.js supports only a subset of IETF language tags, if a country specific locale is not supported we use
	 * the default for that language (https://stripe.com/docs/js/appendix/supported_locales).
	 * If no match is found we return 'auto' so Stripe.js uses the browser locale.
	 *
	 * @param string $locale The locale to convert.
	 *
	 * @return string Closest locale supported by Stripe ('auto' if NONE)
	 */
	public static function convert_to_stripe_locale( string $locale ): string {
		// List copied from: https://stripe.com/docs/js/appendix/supported_locales.
		$supported = [
			'ar',     // Arabic.
			'bg',     // Bulgarian (Bulgaria).
			'cs',     // Czech (Czech Republic).
			'da',     // Danish.
			'de',     // German (Germany).
			'el',     // Greek (Greece).
			'en',     // English.
			'en-GB',  // English (United Kingdom).
			'es',     // Spanish (Spain).
			'es-419', // Spanish (Latin America).
			'et',     // Estonian (Estonia).
			'fi',     // Finnish (Finland).
			'fr',     // French (France).
			'fr-CA',  // French (Canada).
			'he',     // Hebrew (Israel).
			'hu',     // Hungarian (Hungary).
			'id',     // Indonesian (Indonesia).
			'it',     // Italian (Italy).
			'ja',     // Japanese.
			'lt',     // Lithuanian (Lithuania).
			'lv',     // Latvian (Latvia).
			'ms',     // Malay (Malaysia).
			'mt',     // Maltese (Malta).
			'nb',     // Norwegian Bokmål.
			'nl',     // Dutch (Netherlands).
			'pl',     // Polish (Poland).
			'pt-BR',  // Portuguese (Brazil).
			'pt',     // Portuguese (Brazil).
			'ro',     // Romanian (Romania).
			'ru',     // Russian (Russia).
			'sk',     // Slovak (Slovakia).
			'sl',     // Slovenian (Slovenia).
			'sv',     // Swedish (Sweden).
			'th',     // Thai.
			'tr',     // Turkish (Turkey).
			'zh',     // Chinese Simplified (China).
			'zh-HK',  // Chinese Traditional (Hong Kong).
			'zh-TW',  // Chinese Traditional (Taiwan).
		];

		// Stripe uses '-' instead of '_' (used in WordPress).
		$locale = str_replace( '_', '-', $locale );

		if ( in_array( $locale, $supported, true ) ) {
			return $locale;
		}

		// For the Latin America and Caribbean region Stripe uses the locale.
		// For now we only support Spanish (Spain) in the extension, if/when support for Latin America and the Caribbean
		// locales is added we will need to group all locales for 'UN M49' under 'es_419' (52 countries in total).
		// https://en.wikipedia.org/wiki/UN_M49.

		// Remove the country code and try with that.
		$base_locale = substr( $locale, 0, 2 );
		if ( in_array( $base_locale, $supported, true ) ) {
			return $base_locale;
		}

		// Return 'auto' so Stripe.js uses the browser locale.
		return 'auto';
	}

	/**
	 * Returns redacted customer-facing error messages for notices.
	 *
	 * This function tries to filter out API exceptions that should not be displayed to customers.
	 * Generally, only Stripe exceptions with type of `card_error` should be displayed.
	 * Other API errors should be redacted (https://stripe.com/docs/api/errors#errors-message).
	 *
	 * @param Exception $e                      Exception to get the message from.
	 * @param boolean   $blocked_by_fraud_rules Whether the payment was blocked by the fraud rules. Defaults to false.
	 *
	 * @return string
	 */
	public static function get_filtered_error_message( Exception $e, bool $blocked_by_fraud_rules = false ) {
		$error_message = method_exists( $e, 'getLocalizedMessage' ) ? $e->getLocalizedMessage() : $e->getMessage();

		// These notices can be shown when placing an order or adding a new payment method, so we aim for
		// more generic messages instead of specific order/payment messages when the API Exception is redacted.
		if ( $e instanceof Connection_Exception ) {
			$error_message = __( 'There was an error while processing this request. If you continue to see this notice, please contact the admin.', 'woocommerce-payments' );
		} elseif ( $e instanceof Amount_Too_Small_Exception ) {
			$minimum_amount = $e->get_minimum_amount();
			$currency       = $e->get_currency();

			// Cache the result.
			static::cache_minimum_amount( $currency, $minimum_amount );
			$interpreted_amount = self::interpret_stripe_amount( $minimum_amount, $currency );
			$price              = wc_price( $interpreted_amount, [ 'currency' => strtoupper( $currency ) ] );

			return sprintf(
				// translators: %s a formatted price.
				__(
					'The selected payment method requires a total amount of at least %s.',
					'woocommerce-payments'
				),
				wp_strip_all_tags( html_entity_decode( $price ) )
			);
		} elseif ( $e instanceof API_Exception && 'amount_too_large' === $e->get_error_code() ) {
			$error_message = $e->getMessage();
		} elseif ( $e instanceof API_Exception && 'wcpay_bad_request' === $e->get_error_code() ) {
			$error_message = __( 'We\'re not able to process this request. Please refresh the page and try again.', 'woocommerce-payments' );
		} elseif ( $e instanceof API_Exception && ! empty( $e->get_error_type() ) && 'card_error' !== $e->get_error_type() ) {
			$error_message = __( 'We\'re not able to process this request. Please refresh the page and try again.', 'woocommerce-payments' );
		} elseif ( $e instanceof API_Exception && 'card_error' === $e->get_error_type() && 'incorrect_zip' === $e->get_error_code() && ! $blocked_by_fraud_rules ) {
			$error_message = __( 'We couldn’t verify the postal code in your billing address. Make sure the information is current with your card issuing bank and try again.', 'woocommerce-payments' );
		}

		return $error_message;
	}

	/**
	 * Returns the customer facing HTTP status codes for an exception.
	 *
	 * @param   Exception $e  Exception to get the HTTP status code for.
	 *
	 * @return  int
	 */
	public static function get_filtered_error_status_code( Exception $e ): int {
		$status_code = null;
		if ( $e instanceof API_Exception ) {
			$status_code = $e->get_http_code();
		}

		// Hosting companies might use the 402 status code to return a custom error page.
		// When 402 is returned by Stripe, let's return 400 instead.
		// The frontend doesn't make use of the status code.
		if ( 402 === $status_code ) {
			$status_code = 400;
		}

		return $status_code ?? 400;
	}

	/**
	 * Retrieves Stripe minimum order value authorized per currency.
	 * The values are based on Stripe's recommendations.
	 * See https://docs.stripe.com/currencies#minimum-and-maximum-charge-amounts.
	 *
	 * @param string $currency The currency.
	 *
	 * @return int The minimum amount.
	 */
	public static function get_stripe_minimum_amount( $currency ) {
		switch ( $currency ) {
			case 'AED':
			case 'MYR':
			case 'PLN':
			case 'RON':
				$minimum_amount = 200;
				break;
			case 'BGN':
				$minimum_amount = 100;
				break;
			case 'CZK':
				$minimum_amount = 1500;
				break;
			case 'DKK':
				$minimum_amount = 250;
				break;
			case 'GBP':
				$minimum_amount = 30;
				break;
			case 'HKD':
				$minimum_amount = 400;
				break;
			case 'HUF':
				$minimum_amount = 17500;
				break;
			case 'JPY':
				$minimum_amount = 5000;
				break;
			case 'MXN':
			case 'THB':
				$minimum_amount = 1000;
				break;
			case 'NOK':
			case 'SEK':
				$minimum_amount = 300;
				break;
			default:
				$minimum_amount = 50;
				break;
		}

		self::cache_minimum_amount( $currency, $minimum_amount );

		return $minimum_amount;
	}

	/**
	 * Saves the minimum amount required for transactions in a given currency.
	 *
	 * @param string $currency The currency.
	 * @param int    $amount   The minimum amount.
	 */
	public static function cache_minimum_amount( $currency, $amount ) {
		set_transient( 'wcpay_minimum_amount_' . strtolower( $currency ), $amount, DAY_IN_SECONDS );
	}

	/**
	 * Checks if there is a minimum amount required for transactions in a given currency.
	 *
	 * @param string $currency The currency to check for.
	 * @param bool   $fallback_to_local_list Whether to fallback to the local Stripe list if the cached value is not available.
	 *
	 * @return int|null Either the minimum amount, or `null` if not available.
	 */
	public static function get_cached_minimum_amount( $currency, $fallback_to_local_list = false ) {
		$cached = get_transient( 'wcpay_minimum_amount_' . strtolower( $currency ) );

		if ( (int) $cached ) {
			return (int) $cached;
		} elseif ( $fallback_to_local_list ) {
			return self::get_stripe_minimum_amount( $currency );
		}

		return null;
	}

	/**
	 * Check if order is locked for payment processing
	 *
	 * @param WC_Order $order  The order that is being paid.
	 * @param string   $intent_id The id of the intent that is being processed.
	 * @return bool    A flag that indicates whether the order is already locked.
	 */
	public static function is_order_locked( $order, $intent_id = null ) {
		$order_id       = $order->get_id();
		$transient_name = 'wcpay_processing_intent_' . $order_id;
		$processing     = get_transient( $transient_name );

		// Block the process if the same intent is already being handled.
		return ( '-1' === $processing || ( isset( $intent_id ) && $processing === $intent_id ) );
	}

	/**
	 * Lock an order for payment intent processing for 5 minutes.
	 *
	 * @param WC_Order $order  The order that is being paid.
	 * @param string   $intent_id The id of the intent that is being processed.
	 * @return void
	 */
	public static function lock_order_payment( $order, $intent_id = null ) {
		$order_id       = $order->get_id();
		$transient_name = 'wcpay_processing_intent_' . $order_id;

		set_transient( $transient_name, empty( $intent_id ) ? '-1' : $intent_id, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 *
	 * @param WC_Order $order The order that is being unlocked.
	 */
	public static function unlock_order_payment( $order ) {
		$order_id = $order->get_id();
		delete_transient( 'wcpay_processing_intent_' . $order_id );
	}

	/**
	 * Returns the correct id to be used on the transaction URL
	 * The primary ID is prioritized and it fallbacks to the fallback ID
	 *
	 * @param string $primary_id  Usually the Payment Intent ID, but can be an order ID.
	 * @param string $fallback_id Usually the Charge ID.
	 *
	 * @return string
	 */
	public static function get_transaction_url_id( $primary_id, $fallback_id ) {
		return ! empty( $primary_id ) ? $primary_id : $fallback_id;
	}

	/**
	 * Composes url for transaction details page.
	 *
	 * @param string $primary_id  Usually the Payment Intent ID, but can be an order ID.
	 * @param string $fallback_id Usually the Charge ID.
	 * @param array  $query_args  Optional additonal query args to append to the URL.
	 *
	 * @return string Transaction details page url.
	 */
	public static function compose_transaction_url( $primary_id, $fallback_id, $query_args = [] ) {
		if ( empty( $fallback_id ) && empty( $primary_id ) ) {
			return '';
		}

		if ( strpos( $primary_id, 'seti_' ) !== false ) {
			return '';
		}

		return add_query_arg( // nosemgrep: audit.php.wp.security.xss.query-arg -- server generated url is passed in.
			array_merge(
				[
					'page' => 'wc-admin',
					'path' => '/payments/transactions/details',
					'id'   => self::get_transaction_url_id( $primary_id, $fallback_id ),
				],
				$query_args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Retrieve last WC refund from order ID.
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @return null|WC_Order_Refund
	 */
	public static function get_last_refund_from_order_id( $order_id ) {
		$wc_refunds = wc_get_orders(
			[
				'type'    => 'shop_order_refund',
				'parent'  => $order_id,
				'limit'   => 1,
				'orderby' => 'ID',
				'order'   => 'DESC',
			]
		);

		if ( is_array( $wc_refunds ) && ! empty( $wc_refunds ) && is_a( $wc_refunds[0], WC_Order_Refund::class ) ) {
			return $wc_refunds[0];
		}

		return null;
	}

	/**
	 * Check to see if the current user is in Core Payments task onboarding flow experiment treatment mode.
	 *
	 * @return bool
	 */
	public static function is_in_core_payments_task_onboarding_flow_treatment_mode(): bool {
		if ( ! isset( $_COOKIE['tk_ai'] ) ) {
			return false;
		}

		$abtest = new \WCPay\Experimental_Abtest(
			sanitize_text_field( wp_unslash( $_COOKIE['tk_ai'] ) ),
			'woocommerce',
			'yes' === get_option( 'woocommerce_allow_tracking', 'no' )
		);

		return 'treatment' === $abtest->get_variation( 'woopayments_core_payments_task_onboarding_flow_2024_v1' );
	}

	/**
	 * Helper function to check whether to show default new onboarding flow or as an exception disable it (if specific constant is set) .
	 *
	 * @return boolean
	 */
	public static function should_use_new_onboarding_flow(): bool {
		if ( apply_filters( 'wcpay_disable_new_onboarding', defined( 'WCPAY_DISABLE_NEW_ONBOARDING' ) && WCPAY_DISABLE_NEW_ONBOARDING ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether the Force disconnected option is enabled.
	 *
	 * @return bool
	 */
	public static function force_disconnected_enabled(): bool {
		return '1' === get_option( self::FORCE_DISCONNECTED_FLAG_NAME, '0' );
	}

	/**
	 * Return the currency format based on the symbol position.
	 * Similar to get_woocommerce_price_format but with an input.
	 *
	 * @param string $currency_pos currency symbol position.
	 *
	 * @return string The currency format.
	 */
	public static function get_woocommerce_price_format( string $currency_pos ): string {
		$default_left = '%1$s%2$s';

		switch ( $currency_pos ) {
			case 'left':
				return $default_left;
			case 'right':
				return '%2$s%1$s';
			case 'left_space':
				return '%1$s %2$s';
			case 'right_space':
				return '%2$s %1$s';
			default:
				return $default_left;
		}
	}

	/**
	 * Transform the currency format returned from localization service into
	 * the format that can be used by wc_price
	 *
	 * @param string $currency the currency code.
	 *
	 * @return array The currency format.
	 */
	public static function get_currency_format_for_wc_price( string $currency ): array {
		$currency = strtoupper( $currency );

		$currency_data = WC_Payments::get_localization_service()->get_currency_format( $currency );

		$currency_format_for_wc_price = [];
		foreach ( $currency_data as $key => $format ) {
			switch ( $key ) {
				case 'thousand_sep':
					$currency_format_for_wc_price['thousand_separator'] = $format;
					break;
				case 'decimal_sep':
					$currency_format_for_wc_price['decimal_separator'] = $format;
					break;
				case 'num_decimals':
					$currency_format_for_wc_price['decimals'] = $format;
					break;
				case 'currency_pos':
					$currency_format_for_wc_price['price_format'] = self::get_woocommerce_price_format( $format );
					break;
			}
		}
		$currency_format_for_wc_price['currency'] = $currency;

		return $currency_format_for_wc_price;
	}

	/**
	 * Format an amount according to the given currency format.
	 *
	 * @param  float  $amount   Amount to format.
	 * @param  string $currency 3-letter currency code.
	 *
	 * @return string
	 */
	public static function format_currency( float $amount, string $currency ): string {
		$currency = strtoupper( $currency );

		$formatted = html_entity_decode(
			wp_strip_all_tags(
				wc_price(
					$amount,
					self::get_currency_format_for_wc_price( $currency )
				)
			)
		);

		return $formatted;
	}

	/**
	 * Format amount according to the given currency with the currency code in the right.
	 *
	 * @param  float  $amount          Amount.
	 * @param  string $currency       3-letter currency code.
	 * @param  bool   $skip_symbol      Optional. If true, trims off the short currency symbol. Default false.
	 * @param  array  $currency_format Optional. Additional currency format for wc_price.
	 *
	 * @return string Formatted currency representation
	 */
	public static function format_explicit_currency(
		float $amount,
		string $currency,
		bool $skip_symbol = false,
		array $currency_format = []
	): string {
		$currency = strtoupper( $currency );

		$formatted_amount = wc_price(
			$amount,
			wp_parse_args( $currency_format, self::get_currency_format_for_wc_price( $currency ) )
		);

		$formatted_amount = html_entity_decode( wp_strip_all_tags( $formatted_amount ) );

		if ( $skip_symbol ) {
			$formatted_amount = preg_replace( '/[^0-9,\.]+/', '', $formatted_amount );
		}

		if ( false === strpos( $formatted_amount, $currency ) ) {
			return $formatted_amount . ' ' . $currency;
		}

		return $formatted_amount;
	}

	/**
	 * Checks if the HPOS order tables are being used.
	 *
	 * @return bool True if HPOS tables are enabled and being used.
	 */
	public static function is_hpos_tables_usage_enabled() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Get the core request class name as WordPress 6.2 introduces a breaking namespace change.
	 *
	 * @see https://github.com/WordPress/wordpress-develop/commit/d7dd42d72fe5b10460072e7c78d36c130857e427
	 *
	 * @return string The request class name.
	 */
	public static function get_wpcore_request_class(): string {
		return version_compare( get_bloginfo( 'version' ), '6.2', '>=' )
			? '\\WpOrg\\Requests\\Requests'
			: '\\Requests';
	}

	/**
	 * Returns a merchant-friendly description of the dispute reason.
	 *
	 * This mapping is duplicated in client/disputes/strings.ts and on Server.
	 *
	 * @param string $reason The dispute reason.
	 *
	 * @return string
	 */
	public static function get_dispute_reason_description( string $reason ): string {
		switch ( $reason ) {
			case 'bank_cannot_process':
				return __( 'Bank cannot process', 'woocommerce-payments' );
			case 'check_returned':
				return __( 'Check returned', 'woocommerce-payments' );
			case 'credit_not_processed':
				return __( 'Credit not processed', 'woocommerce-payments' );
			case 'customer_initiated':
				return __( 'Customer initiated', 'woocommerce-payments' );
			case 'debit_not_authorized':
				return __( 'Debit not authorized', 'woocommerce-payments' );
			case 'duplicate':
				return __( 'Duplicate', 'woocommerce-payments' );
			case 'fraudulent':
				return __( 'Transaction unauthorized', 'woocommerce-payments' );
			case 'incorrect_account_details':
				return __( 'Incorrect account details', 'woocommerce-payments' );
			case 'insufficient_funds':
				return __( 'Insufficient funds', 'woocommerce-payments' );
			case 'product_not_received':
				return __( 'Product not received', 'woocommerce-payments' );
			case 'product_unacceptable':
				return __( 'Product unacceptable', 'woocommerce-payments' );
			case 'subscription_canceled':
				return __( 'Subscription canceled', 'woocommerce-payments' );
			case 'unrecognized':
				return __( 'Unrecognized', 'woocommerce-payments' );
			default:
			case 'general':
				return __( 'General', 'woocommerce-payments' );
		}
	}

	/**
	 * Register a style for use.
	 *
	 * @uses   wp_register_style()
	 * @param  string   $handle  Name of the stylesheet. Should be unique.
	 * @param  string   $path    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
	 * @param  string[] $deps    An array of registered stylesheet handles this stylesheet depends on.
	 * @param  string   $version String specifying stylesheet version number, if it has one, which is added to the URL as a query string for cache busting purposes. If version is set to false, a version number is automatically added equal to current installed WordPress version. If set to null, no version is added.
	 * @param  string   $media   The media for which this stylesheet has been defined. Accepts media types like 'all', 'print' and 'screen', or media queries like '(orientation: portrait)' and '(max-width: 640px)'.
	 * @param  boolean  $has_rtl If has RTL version to load too.
	 */
	public static function register_style( $handle, $path, $deps = [], $version = WC_VERSION, $media = 'all', $has_rtl = true ) {
		wp_register_style( $handle, $path, $deps, $version, $media );

		if ( $has_rtl ) {
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}
	}


	/**
	 * Register and enqueue a styles for use.
	 *
	 * @uses   wp_enqueue_style()
	 * @param  string   $handle  Name of the stylesheet. Should be unique.
	 * @param  string   $path    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
	 * @param  string[] $deps    An array of registered stylesheet handles this stylesheet depends on.
	 * @param  string   $version String specifying stylesheet version number, if it has one, which is added to the URL as a query string for cache busting purposes. If version is set to false, a version number is automatically added equal to current installed WordPress version. If set to null, no version is added.
	 * @param  string   $media   The media for which this stylesheet has been defined. Accepts media types like 'all', 'print' and 'screen', or media queries like '(orientation: portrait)' and '(max-width: 640px)'.
	 * @param  boolean  $has_rtl If has RTL version to load too.
	 */
	public static function enqueue_style( $handle, $path = '', $deps = [], $version = WC_VERSION, $media = 'all', $has_rtl = true ) {
		if ( '' !== $path ) {
			self::register_style( $handle, $path, $deps, $version, $media, $has_rtl );
		}
		wp_enqueue_style( $handle );
	}

	/**
	 * Returns language data: english name and native name
	 *
	 * @param string $language Language code.
	 *
	 * @return array
	 */
	public static function get_language_data( $language ) {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		$translations = wp_get_available_translations();

		if ( isset( $translations[ $language ] ) ) {
			return [
				'code'         => self::convert_to_server_locale( $language ),
				'english_name' => $translations[ $language ]['english_name'] ?? $language,
				'native_name'  => $translations[ $language ]['native_name'] ?? $language,
			];
		}

		return [
			'code'         => 'en_US',
			'english_name' => 'English (United States)',
			'native_name'  => 'English (United States)',
		];
	}

	/**
	 * Converts a locale to the server supported languages.
	 *
	 * @param string $locale The locale to convert.
	 *
	 * @return string Closest locale supported ('en' if NONE)
	 */
	public static function convert_to_server_locale( string $locale ): string {
		$supported = [
			'ar',     // Arabic.
			'de',     // German (Germany).
			'es',     // Spanish (Spain).
			'fr',     // French (France).
			'he',     // Hebrew (Israel).
			'id',     // Indonesian (Indonesia).
			'it',     // Italian (Italy).
			'ja',     // Japanese.
			'ko',     // Korean.
			'nl',     // Dutch (Netherlands).
			'pt-br',  // Portuguese (Brazil).
			'ru',     // Russian (Russia).
			'sv',     // Swedish (Sweden).
			'tr',     // Turkish (Turkey).
			'zh-cn',  // Simplified, Singapore).
			'zh-tw',  // Chinese Traditional (Taiwan).
		];

		// Replace '-' with '_' (used in WordPress).
		$locale = str_replace( '_', '-', $locale );

		if ( in_array( $locale, $supported, true ) ) {
			return $locale;
		}

		// Remove the country code and try with that.
		$base_locale = substr( $locale, 0, 2 );
		if ( in_array( $base_locale, $supported, true ) ) {
			return $base_locale;
		}

		// Return 'en_US' to match the default site language.
		return 'en_US';
	}

	/**
	 * Check if the current page is the cart page.
	 *
	 * @return bool True if the current page is the cart page, false otherwise.
	 */
	public static function is_cart_page(): bool {
		return is_cart() || has_block( 'woocommerce/cart' );
	}

	/**
	 * Block based themes display the cart block even when the cart shortcode is used. has_block() isn't effective
	 * in this case because it checks the page content for the block, which isn't present.
	 *
	 * @return bool
	 *
	 * @psalm-suppress UndefinedFunction
	 */
	public static function is_cart_block(): bool {
		return has_block( 'woocommerce/cart' ) || ( wp_is_block_theme() && is_cart() );
	}

	/**
	 * Returns true if the request that's currently being processed is a Store API request, false
	 * otherwise.
	 *
	 * @return bool True if request is a Store API request, false otherwise.
	 */
	public static function is_store_api_request(): bool {
		if ( isset( $_REQUEST['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$rest_route = sanitize_text_field( $_REQUEST['rest_route'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification
		} else {
			$url_parts    = wp_parse_url( esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$request_path = $url_parts ? rtrim( $url_parts['path'], '/' ) : '';
			$rest_route   = str_replace( trailingslashit( rest_get_url_prefix() ), '', $request_path );
		}

		foreach ( self::STORE_API_ROUTE_PATTERNS as $pattern ) {
			if ( 1 === preg_match( $pattern, $rest_route ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the current active theme transient for a given location
	 * Falls back to 'stripe' if no transients are set.
	 *
	 * @param string $location The theme location.
	 * @param string $context The theme location to fall back to if both transients are set.
	 * @return string
	 */
	public static function get_active_upe_theme_transient_for_location( string $location = 'checkout', string $context = 'blocks' ) {
		$themes       = \WC_Payment_Gateway_WCPay::APPEARANCE_THEME_TRANSIENTS;
		$active_theme = false;

		// If an invalid location is sent, we fallback to trying $themes[ 'checkout' ][ 'block' ].
		if ( ! isset( $themes[ $location ] ) ) {
			$active_theme = get_transient( $themes['checkout']['blocks'] );
		} elseif ( ! isset( $themes[ $location ][ $context ] ) ) {
			// If the location is valid but the context is invalid, we fallback to trying $themes[ $location ][ 'block' ].
			$active_theme = get_transient( $themes[ $location ]['blocks'] );
		} else {
			$active_theme = get_transient( $themes[ $location ][ $context ] );
		}

		// If $active_theme is still false here, that means that $themes[ $location ][ $context ] is not set, so we try $themes[ $location ][ 'classic' ].
		if ( ! $active_theme ) {
			$active_theme = get_transient( $themes[ $location ][ 'blocks' === $context ? 'classic' : 'blocks' ] );
		}

		// If $active_theme is still false here, nothing at the location is set so we'll try all locations.
		if ( ! $active_theme ) {
			foreach ( $themes as $location_const => $contexts ) {
				// We don't need to check the same location again.
				if ( $location_const === $location ) {
					continue;
				}

				foreach ( $contexts as $context => $transient ) {
					$active_theme = get_transient( $transient );
					if ( $active_theme ) {
						break 2; // This will break both loops.
					}
				}
			}
		}

		// If $active_theme is still false, we don't have any theme set in the transients, so we fallback to 'stripe'.
		if ( $active_theme ) {
			return $active_theme;
		}

		// Fallback to 'stripe' if no transients are set.
		return 'stripe';
	}

	/**
	 * Returns the list of countries in the European Economic Area (EEA).
	 *
	 * Based on the list documented at https://www.gov.uk/eu-eea.
	 *
	 * @return string[]
	 */
	public static function get_european_economic_area_countries() {
		return [
			Country_Code::AUSTRIA,
			Country_Code::BELGIUM,
			Country_Code::BULGARIA,
			Country_Code::CROATIA,
			Country_Code::CYPRUS,
			Country_Code::CZECHIA,
			Country_Code::DENMARK,
			Country_Code::ESTONIA,
			Country_Code::FINLAND,
			Country_Code::FRANCE,
			Country_Code::GERMANY,
			Country_Code::GREECE,
			Country_Code::HUNGARY,
			Country_Code::IRELAND,
			Country_Code::ICELAND,
			Country_Code::ITALY,
			Country_Code::LATVIA,
			Country_Code::LIECHTENSTEIN,
			Country_Code::LITHUANIA,
			Country_Code::LUXEMBOURG,
			Country_Code::MALTA,
			Country_Code::NORWAY,
			Country_Code::NETHERLANDS,
			Country_Code::POLAND,
			Country_Code::PORTUGAL,
			Country_Code::ROMANIA,
			Country_Code::SLOVAKIA,
			Country_Code::SLOVENIA,
			Country_Code::SPAIN,
			Country_Code::SWEDEN,
		];
	}
}
