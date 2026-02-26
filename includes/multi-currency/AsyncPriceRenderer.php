<?php
/**
 * Class AsyncPriceRenderer
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Renders skeleton price markup for cache-optimized mode.
 *
 * When cache mode is active and no WC session exists, this class replaces
 * server-side price conversion with skeleton placeholders that are converted
 * by JavaScript on the client side.
 */
class AsyncPriceRenderer {

	/**
	 * MultiCurrency instance.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;
	}

	/**
	 * Initializes hooks for async price rendering.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// is_cache_optimized_mode() checks both the feature flag and the rendering mode option.
		if ( ! $this->multi_currency->is_cache_optimized_mode() ) {
			return;
		}

		if ( is_admin() || defined( 'DOING_CRON' ) || Utils::is_admin_api_request() ) {
			return;
		}

		// If there's an active session, let FrontendPrices handle it.
		if ( $this->multi_currency->has_active_session() ) {
			return;
		}

		add_filter( 'wc_price', [ $this, 'wrap_price_with_skeleton' ], 999, 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_async_renderer' ] );
	}

	/**
	 * Wraps a price with skeleton markup for client-side conversion.
	 *
	 * @param string $return           The formatted price string.
	 * @param float  $price            The raw price.
	 * @param array  $args             Arguments passed to wc_price.
	 * @param float  $unformatted_price The unformatted price.
	 * @param float  $original_price    The original price before any conversion.
	 *
	 * @return string The wrapped price markup.
	 */
	public function wrap_price_with_skeleton( $return, $price, $args, $unformatted_price, $original_price ) {
		// The async renderer only runs on non-session pages (catalog/product).
		// Cart/checkout have active sessions and use server-side FrontendPrices.
		// Default to 'product' since catalog pages only call wc_price for products.
		$price_type = apply_filters( 'wcpay_multi_currency_async_price_type', 'product', $price, $args );

		// We use $price (the raw numeric value passed to wc_price) rather than
		// $original_price because in cache-optimized mode FrontendPrices hooks
		// are not active, so $price is the unconverted default-currency price.
		return sprintf(
			'<span class="wcpay-async-price" data-wcpay-price="%s" data-wcpay-price-type="%s"><span class="wcpay-price-skeleton"></span></span>',
			esc_attr( $price ),
			esc_attr( $price_type )
		);
	}

	/**
	 * Enqueues the async price renderer script and styles.
	 *
	 * @return void
	 */
	public function enqueue_async_renderer() {
		$this->multi_currency->register_script_with_dependencies(
			'wcpay-multi-currency-async-renderer',
			'dist/multi-currency-async-renderer'
		);

		wp_localize_script(
			'wcpay-multi-currency-async-renderer',
			'wcpayAsyncPriceConfig',
			[
				'apiUrl' => rest_url( 'wc/v3/payments/multi-currency/public/config' ),
			]
		);

		wp_enqueue_script( 'wcpay-multi-currency-async-renderer' );

		wp_enqueue_style(
			'wcpay-multi-currency-async-renderer',
			plugins_url(
				'dist/multi-currency-async-renderer.css',
				WCPAY_PLUGIN_FILE
			),
			[],
			$this->multi_currency->get_file_version( 'dist/multi-currency-async-renderer.css' )
		);
	}
}
