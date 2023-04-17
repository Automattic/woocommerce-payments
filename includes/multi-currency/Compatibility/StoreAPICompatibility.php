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
		// Convert GET parameter prices before it is applied to posts query.
		add_filter( 'pre_get_posts', [ $this, 'modify_query_vars_currency_for_price_filter' ] );
	}

	/**
	 * Converts the min_price and max_price filters back to the store currency.
	 *
	 * @param   WP_Query $wp_query  The WP query vars to modify.
	 *
	 * @return  array
	 */
	public function modify_query_vars_currency_for_price_filter( $wp_query ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $wp_query->query_vars['wc_query'] )
			|| 'product_query' !== $wp_query->query_vars['wc_query']
			|| isset( $_GET['are_prices_converted'] )
			|| ( ! isset( $_GET['max_price'] ) && ! isset( $_GET['min_price'] ) )
		) {
			return $wp_query;
		}

		$currency_rate = $this->multi_currency->get_selected_currency()->get_rate();
		if ( isset( $_GET['min_price'] ) ) {
			$_GET['min_price'] = floatval( wp_unslash( $_GET['min_price'] ) ) / $currency_rate;
		}
		if ( isset( $_GET['max_price'] ) ) {
			$_GET['max_price'] = floatval( wp_unslash( $_GET['max_price'] ) ) / $currency_rate;
		}
		$_GET['are_prices_converted'] = true;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $wp_query;
	}
}
