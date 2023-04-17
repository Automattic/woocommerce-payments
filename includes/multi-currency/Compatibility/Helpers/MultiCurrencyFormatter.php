<?php
/**
 * Class MultiCurrencyFormatter
 *
 * @package WCPay\MultiCurrency\Compatibility\Helpers
 */

namespace WCPay\MultiCurrency\Compatibility\Helpers;

use Automattic\WooCommerce\StoreApi\Formatters\CurrencyFormatter;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls currency conversion in StoreAPI.
 */
class MultiCurrencyFormatter {

	/**
	 * Multicurrency Utility class.
	 *
	 * @var WCPay\MultiCurrency\Utils
	 */
	protected $utils;

	/**
	 * Constructor.
	 *
	 * @return  void
	 */
	public function __construct() {
		$this->utils = new Utils();
	}

	/**
	 * Format a given value and return the result.
	 *
	 * @param array $value Value to format.
	 * @param array $options Options that influence the formatting.
	 * @return array
	 */
	public function format( $value, array $options = [] ) {
		$is_call_for_price_filter = $this->utils->is_call_in_backtrace( [ 'Automattic\WooCommerce\StoreApi\Schemas\V1\ProductCollectionDataSchema->get_item_response' ] );
		$original_formatter       = new CurrencyFormatter();
		if ( $is_call_for_price_filter ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $key => $original ) {
					if ( is_string( $original ) ) {
						$value[ $key ] = (string) MultiCurrency::instance()->get_price( $original, 'product' );
					}
				}
			}
		}
		return $original_formatter->format( $value, $options );
	}
}
