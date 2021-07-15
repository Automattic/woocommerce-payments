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
	 * WP_Theme instance.
	 *
	 * @var \WP_Theme
	 */
	protected $theme;

	/**
	 * Themes where we need to add a wrapper around the breadcrumbs before adding the widget.
	 *
	 * @var array
	 */
	protected $wrapper_themes;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 * @param \WP_Theme     $theme          The instance of the theme in use.
	 */
	public function __construct( MultiCurrency $multi_currency, \WP_Theme $theme ) {
		$this->multi_currency = $multi_currency;
		$this->id             = $this->multi_currency->id;
		$this->theme          = $theme;
		$this->wrapper_themes = [ 'arcade', 'boutique', 'homestore', 'stationery' ];
		$this->init_actions_and_filters();
	}

	/**
	 * Adds the CSS to the head of the page.
	 *
	 * @return void
	 */
	public function add_inline_css() {
		wp_add_inline_style(
			'storefront-style',
			apply_filters( $this->id . '_storefront_widget_css', $this->get_css() )
		);
	}

	/**
	 * Adds the switcher widget.
	 *
	 * @return void
	 */
	public function add_switcher_widget() {
		/**
		 * The spl_object_hash function is used here due to we register the widget with an instance of the widget and
		 * not the class name of the widget. WordPress core takes the instance and passes it through spl_object_hash
		 * to get a hash and adds that as the widget's name in the $wp_widget_factory->widgets[] array. In order to
		 * call the_widget, you need to have the name of the widget, so we get the instance and hash to use.
		 */
		the_widget(
			spl_object_hash( $this->multi_currency->get_currency_switcher_widget() ),
			apply_filters(
				$this->id . '_storefront_widget_options',
				[
					'symbol' => true,
					'flag'   => true,
				]
			),
			apply_filters(
				$this->id . '_storefront_widget_args',
				[
					'before_widget' => '<div id="woocommerce-payments-multi-currency-storefront-widget">',
					'after_widget'  => '</div>',
				]
			)
		);
	}

	/**
	 * This closes the div tags opened in modify_breadcrumb_wrapper.
	 *
	 * @return void
	 */
	public function close_breadcrumb_wrapper() {
		if ( $this->is_wrapper_theme() ) {
			echo '</div>';
		} else {
			echo '</div></div>';
		}
	}

	/**
	 * Adds the checkbox to the store settings to toggle the currency switched in the Storefront header.
	 *
	 * @param array $settings The settings from the Settings class.
	 *
	 * @return array The modified settings array.
	 */
	public function filter_store_settings( array $settings ): array {
		foreach ( $settings as $setting ) {
			if ( isset( $setting['id'] ) && $this->id . '_enable_auto_currency' === $setting['id'] ) {
				// Add the checkboxgroup prop to the store settings section, then add the setting to the new array.
				$setting['checkboxgroup'] = 'start';
				$new_settings[]           = $setting;

				// Add the checkbox to be able to toggle the widget.
				$new_settings[] = [
					'desc'          => __( 'Add a currency switcher to the Storefront theme. ', 'woocommerce-payments' ),
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

			} else {
				// Otherwise just pass the setting to the new array.
				$new_settings[] = $setting;
			}
		}
		return $new_settings;
	}

	/**
	 * This modifies the wrapper(s) around the breadcrumb for us to be able to place the widget in the wrapper.
	 *
	 * @param array $defaults The default breadcrumb wrapper elements.
	 *
	 * @return array The modified wrapper elements.
	 */
	public function modify_breadcrumb_wrapper( array $defaults ): array {
		if ( $this->is_wrapper_theme() ) {
			// This adds the opening div tag so we can make use of it for these themes.
			$defaults['wrap_before'] = '<div class="storefront-breadcrumb-wrapper"><nav class="woocommerce-breadcrumb">';
		} else {
			// This removes the closing div tags for all other Storefront themes.
			$defaults['wrap_after'] = '</nav>';
		}

		return $defaults;
	}

	/**
	 * Gets the css for the current Storefront theme.
	 *
	 * @return string The css to be added to the header.
	 */
	private function get_css(): string {
		$flex_rules = '
			display: flex;
			flex-wrap: wrap;
			justify-content: space-between;
			align-items: center;
		';

		$without_wrapper = '
			div.storefront-breadcrumb div.col-full {
				' . $flex_rules . '
			}

			div.storefront-breadcrumb div.col-full:before,
			div.storefront-breadcrumb div.col-full:after {
				display: none;
			}
		';

		switch ( $this->theme->stylesheet ) {
			case 'arcade':
			case 'boutique':
				$css = '
					div.storefront-breadcrumb-wrapper {
						' . $flex_rules . '
						margin: 0 0 1.618em;
					}

					div.storefront-breadcrumb-wrapper nav.woocommerce-breadcrumb {
						margin: 0;
					}
				';
				break;
			case 'bistro':
				$css = $without_wrapper . '
					div.storefront-breadcrumb div.col-full {
						margin-top: 4.236em;
					}

					div.storefront-breadcrumb nav.woocommerce-breadcrumb {
						margin: 0;
					}
				';
				break;
			case 'deli':
				$css = $without_wrapper . '
					div.storefront-breadcrumb div.col-full {
						margin-bottom: 0;
					}

					div.storefront-breadcrumb nav.woocommerce-breadcrumb {
						margin: 0;
					}
				';
				break;
			case 'galleria':
			case 'outlet':
				$css = $without_wrapper . '
					#woocommerce-payments-multi-currency-storefront-widget {
						font-size: .8rem;
					}
				';
				break;
			case 'homestore':
				$css = '
					div.storefront-breadcrumb-wrapper {
						' . $flex_rules . '
						font-size: 0.9em;
						border-bottom: 3px solid #151515;
						color: #a2a1a1;
						margin: -3em 0 3.24rem;
						padding: 0 0 .6rem;
					}

					div.storefront-breadcrumb-wrapper nav.woocommerce-breadcrumb {
						border: none;
						margin: 0;
						padding: 0;
					}
				';
				break;
			case 'stationery':
				$css = '
					div.storefront-breadcrumb-wrapper {
						' . $flex_rules . '
						margin: 0 0 1.861em;
						padding: 1.861em 0;
					}

					div.storefront-breadcrumb-wrapper nav.woocommerce-breadcrumb {
						margin: 0;
						padding: 0;
					}
				';
				break;
			case 'toyshop':
				$css = $without_wrapper . '
					div.storefront-breadcrumb div.col-full {
						margin-bottom: 2rem;
					}

					div.storefront-breadcrumb nav.woocommerce-breadcrumb {
						margin: 0;
					}
				';
				break;
			default:
				$css = $without_wrapper;
				break;
		}

		$css .= '
			#woocommerce-payments-multi-currency-storefront-widget form {
				margin: 0;
			}
		';

		return $css;
	}

	/**
	 * Adds the actions and filters.
	 *
	 * @return void
	 */
	private function init_actions_and_filters() {
		add_filter( $this->id . '_enabled_currencies_settings', [ $this, 'filter_store_settings' ] );

		// We specifically look for 'no', because we want it enabled by default.
		if ( 'no' !== get_option( $this->id . '_enable_storefront_switcher' ) ) {
			add_filter( 'woocommerce_breadcrumb_defaults', [ $this, 'modify_breadcrumb_wrapper' ], 9999 );
			add_action( 'wp_enqueue_scripts', [ $this, 'add_inline_css' ], 50 );

			$breadcrumb_action = $this->is_wrapper_theme() ? 'storefront_content_top' : 'storefront_before_content';
			add_action( $breadcrumb_action, [ $this, 'add_switcher_widget' ], 11 );
			add_action( $breadcrumb_action, [ $this, 'close_breadcrumb_wrapper' ], 12 );
		}
	}

	/**
	 * Returns if a wrapper theme is in use or not.
	 *
	 * @return bool
	 */
	private function is_wrapper_theme(): bool {
		return in_array( $this->theme->stylesheet, $this->wrapper_themes, true );
	}
}
