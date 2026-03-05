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
		add_filter( 'woocommerce_format_sale_price', [ $this, 'tag_sale_price_sr_text' ], 10, 3 );
		add_filter( 'woocommerce_format_price_range', [ $this, 'tag_price_range_sr_text' ], 10, 3 );
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
		//
		// The screen-reader-text placeholder contains the original WC-formatted
		// price so crawlers and screen readers on slow connections see a real
		// price before JS loads. JS removes it after successful conversion.
		//
		// The wrapper reuses the woocommerce-Price-amount/amount classes so the
		// DOM hierarchy matches what wc_price() normally produces. This avoids
		// an extra nesting level that could break theme CSS selectors like
		// `.price > .woocommerce-Price-amount`. JS replaces the <bdi> contents
		// in-place rather than appending a new child element.
		return sprintf(
			'<span class="woocommerce-Price-amount amount wcpay-async-price" data-wcpay-price="%s" data-wcpay-price-type="%s"><bdi class="wcpay-price-skeleton"></bdi><span class="screen-reader-text wcpay-price-placeholder">%s</span></span>',
			esc_attr( $price ),
			esc_attr( $price_type ),
			wp_kses_post( $return )
		);
	}

	/**
	 * Add data attributes to screen-reader-text spans in sale price markup.
	 *
	 * WC core's wc_format_sale_price() generates screen-reader text like
	 * "Original price was: $20.00." using wp_strip_all_tags() on the wc_price()
	 * output. In async mode that text contains the default-currency price.
	 * This filter marks those spans so JS can update them after conversion.
	 *
	 * @param string $price         The formatted sale price HTML.
	 * @param string $regular_price The regular price.
	 * @param string $sale_price    The sale price.
	 *
	 * @return string The modified HTML with data attributes on SR spans.
	 */
	public function tag_sale_price_sr_text( $price, $regular_price, $sale_price ) {
		// Mark the "Original price was:" span with the regular price.
		$price = preg_replace(
			'/(<del[^>]*>.*?<\/del>\s*)<span class="screen-reader-text">/s',
			'$1<span class="screen-reader-text" data-wcpay-sr-price="' . esc_attr( $regular_price ) . '" data-wcpay-sr-template="original_price">',
			$price,
			1
		);

		// Mark the "Current price is:" span with the sale price.
		$price = preg_replace(
			'/(<ins[^>]*>.*?<\/ins>)\s*<span class="screen-reader-text">/s',
			'$1<span class="screen-reader-text" data-wcpay-sr-price="' . esc_attr( $sale_price ) . '" data-wcpay-sr-template="current_price">',
			$price,
			1
		);

		return $price;
	}

	/**
	 * Add data attributes to screen-reader-text spans in price range markup.
	 *
	 * Similar to tag_sale_price_sr_text but for wc_format_price_range().
	 *
	 * @param string $price The formatted price range HTML.
	 * @param string $from  The "from" price.
	 * @param string $to    The "to" price.
	 *
	 * @return string The modified HTML with data attributes on SR spans.
	 */
	public function tag_price_range_sr_text( $price, $from, $to ) {
		$price = preg_replace(
			'/<span class="screen-reader-text">/',
			'<span class="screen-reader-text" data-wcpay-sr-price-from="' . esc_attr( $from ) . '" data-wcpay-sr-price-to="' . esc_attr( $to ) . '" data-wcpay-sr-template="price_range">',
			$price,
			1
		);

		return $price;
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
				'apiUrl'          => rest_url( 'wc/v3/payments/multi-currency/public/config' ),
				'defaultCurrency' => [
					'symbol'       => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'decimals'     => wc_get_price_decimals(),
					'decimal_sep'  => wc_get_price_decimal_separator(),
					'thousand_sep' => wc_get_price_thousand_separator(),
					'symbol_pos'   => get_option( 'woocommerce_currency_pos' ),
				],
				// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Must use WC core domain to reuse its translations.
				'i18n'            => [
					/* translators: %s is a product's regular price. */
					'original_price' => __( 'Original price was: %s.', 'woocommerce' ),
					/* translators: %s is a product's current (sale) price. */
					'current_price'  => __( 'Current price is: %s.', 'woocommerce' ),
					/* translators: 1: price from 2: price to */
					'price_range'    => __( 'Price range: %1$s through %2$s', 'woocommerce' ),
				],
				// phpcs:enable WordPress.WP.I18n.TextDomainMismatch
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
