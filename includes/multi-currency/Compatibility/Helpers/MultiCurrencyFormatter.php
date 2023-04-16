<?php
/**
 * Class MultiCurrencyFormatter
 *
 * @package WCPay\MultiCurrency\Compatibility\Helpers
 */

namespace WCPay\MultiCurrency\Compatibility\Helpers;

use Automattic\WooCommerce\StoreApi\Formatters\CurrencyFormatter;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * Class that controls currency conversion in StoreAPI.
 */
class MultiCurrencyFormatter {
	/**
	 * Format a given value and return the result.
	 *
	 * @param array $value Value to format.
	 * @param array $options Options that influence the formatting.
	 * @return array
	 */
	public function format( $value, array $options = [] ) {
		$original_formatter = new CurrencyFormatter();
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $original ) {
				if ( is_string( $original ) ) {
					$value[ $key ] = (string) MultiCurrency::instance()->get_price( $original, 'product' );
				}
			}
		}
		return $original_formatter->format( $value, $options );
	}
}
