<?php
/**
 * Class StorefrontIntegration
 *
 * @package WooCommerce\Payments\StorefrontIntegration
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency Storefront Integration.
 */
class StorefrontIntegration {

	/**
	 * Multi-Currency instance.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;
		$this->init_actions_and_filters();
	}

	/**
	 * Adds the CSS to the head of the page.
	 *
	 * @return void
	 */
	public function add_inline_css() {
		$css = '
			#woocommerce-payments-multi-currency-storefront-widget {
				float: right;
			}
			#woocommerce-payments-multi-currency-storefront-widget form {
				margin: 0;
			}
		';

		wp_add_inline_style(
			'storefront-style',
			apply_filters( MultiCurrency::FILTER_PREFIX . 'storefront_widget_css', $css )
		);
	}

	/**
	 * This modifies the breadcrumb defaults for us to be able to place the widget.
	 *
	 * @param array $defaults The defaults breadcrumb properties.
	 *
	 * @return array The modified defaults properties.
	 */
	public function modify_breadcrumb_defaults( array $defaults ): array {
		// Set the instance and args arrays for the widget.
		$instance = apply_filters( MultiCurrency::FILTER_PREFIX . 'storefront_widget_instance', [] );
		$args     = apply_filters(
			MultiCurrency::FILTER_PREFIX . 'storefront_widget_args',
			[
				'before_widget' => '<div id="woocommerce-payments-multi-currency-storefront-widget" class="woocommerce-breadcrumb">',
				'after_widget'  => '</div>',
			]
		);

		/**
		 * Some storefront child themes use different wrappers and styles. We need to place the widget before
		 * the <nav> to display it properly.
		 */
		$defaults['wrap_before'] = str_replace( '<nav', $this->multi_currency->get_switcher_widget_markup( $instance, $args ) . '<nav', $defaults['wrap_before'] );

		return $defaults;
	}

	/**
	 * Adds the actions and filters.
	 *
	 * @return void
	 */
	private function init_actions_and_filters() {
		// Do not enable the breadcrumb widget if there's only one currency active.
		if ( 1 >= count( $this->multi_currency->get_enabled_currencies() ) ) {
			return;
		}

		// Simulation overrides for Multi-Currency onboarding preview.
		$simulation_variables     = $this->multi_currency->get_multi_currency_onboarding_simulation_variables() ?? [];
		$simulation_enabled       = false;
		$simulation_hide_switcher = false;

		if ( 0 < count( $simulation_variables ) && isset( $simulation_variables['enable_storefront_switcher'] ) ) {
			// We have a incoming override request! Simulate the flag.
			$simulation_enabled         = true;
			$enable_storefront_switcher = (bool) $simulation_variables['enable_storefront_switcher'];
			// If the Storefront switcher is not enabled on the onboarding page, hide it.
			if ( ! $enable_storefront_switcher ) {
				$simulation_hide_switcher = true;
			}
		}

		// We want this enabled by default, so we default the option to 'yes'.
		if ( ! $simulation_hide_switcher
			&& (
				$simulation_enabled
				|| $this->multi_currency->is_using_storefront_switcher()
				)
			) {
				add_filter( 'woocommerce_breadcrumb_defaults', [ $this, 'modify_breadcrumb_defaults' ], 9999 );
				add_action( 'wp_enqueue_scripts', [ $this, 'add_inline_css' ], 50 );
		}
	}
}
