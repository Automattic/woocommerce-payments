<?php
/**
 * Class StorefrontIntegration
 *
 * @package WooCommerce\Payments\StorefrontIntegration
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency Storefront Integration.
 */
class StorefrontIntegration {
	/**
	 * The plugin's ID.
	 *
	 * @var string
	 */
	public $id;

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
		$this->id             = $this->multi_currency->id;
		$this->init_actions_and_filters();
	}

	/**
	 * Adds the checkbox to the store settings to toggle the currency switched in the Storefront breadcrumb section.
	 *
	 * @param array $settings The settings from the Settings class.
	 *
	 * @return array The modified settings array.
	 */
	public function filter_store_settings( array $settings ): array {
		foreach ( $settings as $key => $value ) {
			if ( ! isset( $value['id'] ) ) {
				continue;
			}
			if ( $this->id . '_enable_auto_currency' === $value['id'] ) {
				$settings[ $key ]['checkboxgroup'] = 'start';
			}
			if ( $this->id . '_store_settings_widgets_link' === $value['id'] ) {
				$settings[ $key ] = [
					'desc'          => __( 'Add a currency switcher to the Storefront theme on breadcrumb section. ', 'woocommerce-payments' ),
					'desc_tip'      => sprintf(
						/* translators: %s: url to the widgets page */
						__( 'A currency switcher is also available in your widgets. <a href="%s">Configure now</a>', 'woocommerce-payments' ),
						'widgets.php'
					),
					'id'            => $this->id . '_enable_storefront_switcher',
					'default'       => 'yes',
					'type'          => 'checkbox',
					'checkboxgroup' => 'end',
				];
			}
		}
		return $settings;
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
			apply_filters( $this->id . '_storefront_widget_css', $css )
		);
	}

	/**
	 * Generates the switcher widget markup.
	 *
	 * @return string The widget markup.
	 */
	public function switcher_widget_markup(): string {
		/**
		 * The spl_object_hash function is used here due to we register the widget with an instance of the widget and
		 * not the class name of the widget. WordPress core takes the instance and passes it through spl_object_hash
		 * to get a hash and adds that as the widget's name in the $wp_widget_factory->widgets[] array. In order to
		 * call the_widget, you need to have the name of the widget, so we get the instance and hash to use.
		 */
		ob_start();
		the_widget(
			spl_object_hash( $this->multi_currency->get_currency_switcher_widget() ),
			null,
			apply_filters(
				$this->id . '_storefront_widget_args',
				[
					'before_widget' => '<div id="woocommerce-payments-multi-currency-storefront-widget" class="woocommerce-breadcrumb">',
					'after_widget'  => '</div>',
				]
			)
		);
		return ob_get_clean();
	}



	/**
	 * This modifies the breadcrumb defaults for us to be able to place the widget.
	 *
	 * @param array $defaults The defaults breadcrumb properties.
	 *
	 * @return array The modified defaults properties.
	 */
	public function modify_breadcrumb_defaults( array $defaults ): array {
		/**
		 * Some storefront child themes uses different wrappers and styles. We need to place the widget before
		 * the <nav> to display it properly.
		 */
		$defaults['wrap_before'] = str_replace( '<nav', $this->switcher_widget_markup() . '<nav', $defaults['wrap_before'] );

		return $defaults;
	}

	/**
	 * Adds the actions and filters.
	 *
	 * @return void
	 */
	private function init_actions_and_filters() {
		add_filter( $this->id . '_enabled_currencies_settings', [ $this, 'filter_store_settings' ] );

		// Do not enable the breadcrumb widget if there's only one currency active.
		if ( 1 >= count( $this->multi_currency->get_enabled_currencies() ) ) {
			return;
		}

		// We want this enabled by default, so we default the option to 'yes'.
		if ( 'yes' === get_option( $this->id . '_enable_storefront_switcher', 'yes' ) ) {
			add_filter( 'woocommerce_breadcrumb_defaults', [ $this, 'modify_breadcrumb_defaults' ], 9999 );
			add_action( 'wp_enqueue_scripts', [ $this, 'add_inline_css' ], 50 );
		}
	}
}
