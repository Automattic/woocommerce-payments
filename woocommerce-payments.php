<?php
/**
 * Plugin Name: WooCommerce Payments
 * Plugin URI: https://github.com/Automattic/woocommerce-payments
 * Description: Feature plugin for accepting payments via a WooCommerce-branded payment gateway.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-payments
 * Domain Path: /languages
 * WC requires at least: 3.9
 * WC tested up to: 3.9
 * Requires WP: 5.3
 * Version: 0.8.2
 *
 * @package WooCommerce\Payments
 *
 * Woo: 5278104:8ed5c1451e548223478370a6b0652bd4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WCPAY_PLUGIN_FILE', __FILE__ );
define( 'WCPAY_ABSPATH', dirname( WCPAY_PLUGIN_FILE ) . '/' );
define( 'WCPAY_MIN_WC_ADMIN_VERSION', '0.23.2' );

/**
 * Initialize the extension. Note that this gets called on the "plugins_loaded" filter,
 * so WooCommerce classes are guaranteed to exist at this point (if WooCommerce is enabled).
 */
function wcpay_init() {
	include_once dirname( __FILE__ ) . '/includes/class-wc-payments.php';
	WC_Payments::init();
}

// Make sure this is run *after* WooCommerce has a chance to initialize its packages (wc-admin, etc). That is run with priority 10.
add_action( 'plugins_loaded', 'wcpay_init', 11 );

/**
 * Attempts to mirror JS's createInterpolateElement. Doesn't really interpolate elements, but escapes all of the text except for the elements in $element_map.
 *
 * @param string $string string to process.
 * @param array  $element_map map of elements to not escape.
 *
 * @return string String where all of the html was escaped, except for the tags specified in element map.
 */
function create_interpolate_element( $string, $element_map ) {
	$tokenizer    = '/<(\/)?(\w+)\s*(\/)?>/';
	$string_queue = [];
	$token_queue  = [];
	$last_mapped  = true;
	$processed    = $string;

	while ( preg_match( $tokenizer, $processed, $matches ) ) {
		$matched        = $matches[0];
		$token          = $matches[2];
		$is_closing     = ! empty( $matches[1] );
		$is_self_closed = ! empty( $matches[3] );

		$split = explode( $matched, $processed, 2 );
		if ( $last_mapped ) {
			$string_queue[] = $split[0];
		} else {
			$string_queue[ count( $string_queue ) - 1 ] .= $split[0];
		}
		$processed = $split[1];

		if ( isset( $element_map[ $token ] ) ) {
			$map_matched = preg_match( '/<(\w+)(\s.+)?\/?>/', $element_map[ $token ], $map_matches );
			if ( ! $map_matches ) {
				return esc_html( $string );
			}
			$tag   = $map_matches[1];
			$attrs = isset( $map_matches[2] ) ? $map_matches[2] : '';
			if ( $is_closing ) {
				$token_queue[] = '</' . $tag . '>';
			} elseif ( $is_self_closed ) {
				$token_queue[] = '<' . $tag . $attrs . '/>';
			} else {
				$token_queue[] = '<' . $tag . $attrs . '>';
			}

			$last_mapped = true;
		} else {
			$string_queue[ count( $string_queue ) - 1 ] .= $matched;
			$last_mapped                                 = false;
		}
	}

	if ( empty( $token_queue ) || count( $token_queue ) !== count( $string_queue ) ) {
		return esc_html( $string );
	}

	$result = '';
	while ( ! empty( $token_queue ) ) {
		$result .= esc_html( array_shift( $string_queue ) ) . array_shift( $token_queue );
	}
	$result .= esc_html( $processed );

	return $result;
}
