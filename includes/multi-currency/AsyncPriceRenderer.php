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
		add_filter( 'woocommerce_format_sale_price', [ $this, 'annotate_sale_price_sr_text' ], 999, 3 );
		add_filter( 'woocommerce_format_price_range', [ $this, 'annotate_price_range_sr_text' ], 999, 3 );
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

		// We use $unformatted_price (the raw float before number_format) rather
		// than $price (which is locale-formatted, e.g. "20,00" in European
		// locales). The JS async renderer parses this with Decimal.js which
		// expects dot-decimal notation.
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
			esc_attr( $unformatted_price ),
			esc_attr( $price_type ),
			wp_kses_post( $return )
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
				'apiUrl'          => rest_url( 'wc/v3/payments/multi-currency/public/config' ),
				'defaultCurrency' => [
					'symbol'       => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'decimals'     => wc_get_price_decimals(),
					'decimal_sep'  => wc_get_price_decimal_separator(),
					'thousand_sep' => wc_get_price_thousand_separator(),
					'symbol_pos'   => get_option( 'woocommerce_currency_pos' ),
				],
				// Uses WC's text domain so translations match WC core output in every locale.
				// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
				'srText'          => [
					/* translators: %s: formatted price */
					'sale_original' => __( 'Original price was: %s.', 'woocommerce' ),
					/* translators: %s: formatted price */
					'sale_current'  => __( 'Current price is: %s.', 'woocommerce' ),
					/* translators: %1$s: minimum price, %2$s: maximum price */
					'range'         => __( 'Price range: %1$s through %2$s', 'woocommerce' ),
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

	/**
	 * Annotates screen-reader-text spans in sale price HTML with data attributes
	 * so the client-side renderer can rebuild them in the target currency.
	 *
	 * @param string $price_html    The formatted sale price HTML.
	 * @param string $regular_price The regular price.
	 * @param string $sale_price    The sale price.
	 *
	 * @return string The annotated HTML.
	 */
	public function annotate_sale_price_sr_text( $price_html, $regular_price, $sale_price ) {
		if ( ! is_numeric( $regular_price ) || ! is_numeric( $sale_price ) ) {
			return $price_html;
		}

		$count = 0;
		return preg_replace_callback(
			'/<span class="screen-reader-text">/',
			function () use ( $regular_price, $sale_price, &$count ) {
				$count++;
				if ( 1 === $count ) {
					return sprintf(
						'<span class="screen-reader-text" data-wcpay-sr-type="sale_original" data-wcpay-sr-price="%s">',
						esc_attr( $regular_price )
					);
				}
				return sprintf(
					'<span class="screen-reader-text" data-wcpay-sr-type="sale_current" data-wcpay-sr-price="%s">',
					esc_attr( $sale_price )
				);
			},
			$price_html,
			2
		);
	}

	/**
	 * Annotates the screen-reader-text span in price range HTML with data attributes
	 * so the client-side renderer can rebuild it in the target currency.
	 *
	 * @param string $price_html The formatted price range HTML.
	 * @param string $from       The "from" price.
	 * @param string $to         The "to" price.
	 *
	 * @return string The annotated HTML.
	 */
	public function annotate_price_range_sr_text( $price_html, $from, $to ) {
		if ( ! is_numeric( $from ) || ! is_numeric( $to ) ) {
			return $price_html;
		}

		return preg_replace(
			'/<span class="screen-reader-text">/',
			sprintf(
				'<span class="screen-reader-text" data-wcpay-sr-type="range" data-wcpay-sr-price-from="%s" data-wcpay-sr-price-to="%s">',
				esc_attr( $from ),
				esc_attr( $to )
			),
			$price_html,
			1
		);
	}
}
