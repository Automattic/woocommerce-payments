<?php
/**
 * Class StoreAPICompatibility
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use Automattic\WooCommerce\StoreApi\Formatters;
use Automattic\WooCommerce\StoreApi\StoreApi;
use WP_Query;

/**
 * Class that controls Multi Currency Compatibility with StoreAPI currency formatter.
 */
class StoreAPICompatibility extends BaseCompatibility {

	/**
	 * Init the class.
	 *
	 * @return void
	 */
	protected function init() {
		// Override existing currency formatter to get converted prices.
		$container  = StoreApi::container();
		$formatters = $container->get( Formatters::class );
		$formatters->register( 'currency', Helpers\MultiCurrencyFormatter::class );
		add_action( 'posts_clauses', [ $this, 'convert_prices_if_min_max_price' ], 9, 2 );
	}

	/**
	 * Converts the min_price and max_price filters back to the store currency.
	 *
	 * @param   array    $args      Arguments array.
	 * @param   WP_Query $wp_query  The query object to modify args.
	 *
	 * @return  array
	 */
	public function convert_prices_if_min_max_price( $args, $wp_query ) {
		// Check first if it has min_price or max_price.
		if ( ! $wp_query->get( 'min_price', false ) && ! $wp_query->get( 'max_price', false ) ) {
			return $args;
		}
		$currency_rate = $this->multi_currency->get_selected_currency()->get_rate();
		if ( $wp_query->get( 'min_price' ) && ! $wp_query->get( 'is_min_price_converted', false ) ) {
			$converted_price   = $wp_query->get( 'min_price' ) / $currency_rate;
			$_GET['min_price'] = $converted_price;
			$wp_query->set( 'is_min_price_converted', true );
		}
		if ( $wp_query->get( 'max_price' ) && ! $wp_query->get( 'is_max_price_converted', false ) ) {
			$converted_price   = $wp_query->get( 'max_price' ) / $currency_rate;
			$_GET['max_price'] = $converted_price;
			$wp_query->set( 'is_max_price_converted', true );
		}
		return $args;
	}
}
