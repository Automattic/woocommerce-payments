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
		add_filter( 'posts_clauses', [ $this, 'convert_prices_if_min_max_price' ], 9, 2 );
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
		/**
		 * Context:
		 * The `price_filter_post_clauses` method checks the $_GET['min_price'] or $_GET['max_price'] and limits the
		 * product display to those limits by directly applying the parameters to the DB query, which is always saved
		 * in the store currency, and when MC is involved, the prices on the querystring are sent as the customer currency.
		 * To match the price limits on the querystring to the saved values on the DB, the filters need to be converted
		 * back to the store currency.
		 *
		 * Permalink: https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-query.php#L670-L673
		 */

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! $wp_query->is_main_query() || isset( $_GET['are_prices_converted'] ) || ( ! isset( $_GET['max_price'] ) && ! isset( $_GET['min_price'] ) ) ) {
			return $args;
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

		return $args;
	}
}
