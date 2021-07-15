<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;
use Automattic\WooCommerce\Blocks\Package;

defined( 'ABSPATH' ) || exit;

/**
 * Class that contains multi-currency related support for WooCommerce analytics.
 */
class Analytics {

	const SCRIPT_NAME = 'WCPAY_MULTI_CURRENCY_ANALYTICS';

	const PRIORITY_LATE = 11;

	/**
	 * A list of all the pages in the WC Admin analytics section that
	 * we want to add multi-currency filters to.
	 *
	 * @var string[]
	 */
	const ANALYTICS_PAGES = [
		'orders',
		'revenue',
		'products',
		'categories',
		'coupons',
		'taxes',
	];

	/**
	 * Instance of MultiCurrency.
	 *
	 * @var MultiCurrency $multi_currency
	 */
	private $multi_currency;

	/**
	 * Instance of AssetDataRegistry
	 *
	 * @var AssetDataRegistry $data_registry
	 */
	private $data_registry;

	/**
	 * Constructor
	 *
	 * @param MultiCurrency $multi_currency Instance of MultiCurrency.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		// If we aren't in the context of WC Admin, just return false.
		if ( ! is_admin() ) {
			return;
		}

		$this->multi_currency = $multi_currency;
		$this->data_registry  = Package::container()->get( AssetDataRegistry::class );

		$this->init();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function add_currency_settings() {
		$enabled_currencies = $this->multi_currency->get_enabled_currencies();
		$currency_names     = get_woocommerce_currencies();
		$currencies         = [];

		foreach ( $enabled_currencies as $enabled_currency ) {
			$code = $enabled_currency->get_code();

			$currencies[] = [
				'label' => $currency_names[ $code ] . '(' . $enabled_currency->get_symbol() . ' ' . $code . ')',
				'value' => $code,
			];
		}

		if ( method_exists( $this->data_registry, 'exists' ) ) {
			$this->data_registry->add(
				'WooMC',
				[
					'il8n'       => [
						'Currency' => __( 'Currency', 'woocommerce-payments' ),
					],
					'currencies' => $currencies,
				]
			);
		}
	}

	/**
	 * Return the currently selected currency from _GET, or the store default currency.
	 *
	 * @return string
	 */
	public function get_active_currency(): string {
		0 && wp_verify_nonce( '' );

		if ( ! empty( $_GET['currency'] ) ) {
			return strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) );
		} else {
			return $this->multi_currency->get_default_currency()->get_code();
		}
	}

	/**
	 * Applied when loading a stats page to filter stats by the selected currency.
	 *
	 * @param array $args Arguments passed in from the filter.
	 *
	 * @return array
	 */
	public function filter_stats_by_currency( $args ): array {
		$args['currency'] = $this->get_active_currency();
		return $args;
	}


	/**
	 * Initialise all actions, filters and hooks related to analytics support.
	 *
	 * @return void
	 */
	private function init() {
		add_action( 'init', [ $this, 'add_currency_settings' ], self::PRIORITY_LATE );
		add_filter( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		foreach ( self::ANALYTICS_PAGES as $analytics_page ) {
			add_filter( "woocommerce_analytics_{$analytics_page}_query_args", [ $this, 'filter_stats_by_currency' ] );
			add_filter( "woocommerce_analytics_{$analytics_page}_stats_query_args", [ $this, 'filter_stats_by_currency' ] );
		}

		add_filter( 'woocommerce_analytics_clauses_join', [ $this, 'filter_join_clauses' ] );
		add_filter( 'woocommerce_analytics_clauses_where', [ $this, 'filter_where_clauses' ] );
		add_filter( 'woocommerce_analytics_clauses_select', [ $this, 'filter_select_clauses' ] );
	}

	/**
	 * Register the CSS and JS scripts.
	 *
	 * @return void
	 */
	private function register_admin_scripts() {
		$script_src_url    = plugins_url( 'dist/multi-currency-analytics.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/multi-currency-analytics.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			self::SCRIPT_NAME,
			$script_src_url,
			$script_asset['dependencies'],
			\WC_Payments::get_file_version( 'dist/multi-currency-analytics.js' ),
			true
		);

		wp_register_style(
			self::SCRIPT_NAME,
			plugins_url( 'dist/multi-currency-analytics.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			\WC_Payments::get_file_version( 'dist/multi-currency-analytics.css' )
		);
	}

	/**
	 * Enqueue scripts used on the analytics WP Admin pages.
	 *
	 * @return void
	 */
	private function enqueue_admin_scripts() {
		$this->register_admin_scripts();

		wp_enqueue_script( self::SCRIPT_NAME );
		wp_enqueue_style( self::SCRIPT_NAME );
	}
}
