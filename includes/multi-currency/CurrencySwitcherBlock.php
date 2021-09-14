<?php
/**
 * WooCommerce Payments Currency Switcher Widget
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Currency Switcher Gutenberg Block.
 */
class CurrencySwitcherBlock {

	/**
	 * Instance of Multi-Currency.
	 *
	 * @var MultiCurrency $multi_currency
	 */
	protected $multi_currency;

	/**
	 * Instance of Compatibility.
	 *
	 * @var Compatibility $compatibility
	 */
	protected $compatibility;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency Instance of Multi Currency.
	 * @param Compatibility $compatibility  Instance of Compatibility.
	 */
	public function __construct( MultiCurrency $multi_currency, Compatibility $compatibility ) {
		$this->multi_currency = $multi_currency;
		$this->compatibility  = $compatibility;

		add_action( 'init', [ $this, 'init_block_widget' ] );
	}

	/**
	 * Initialize the block currency switcher widget.
	 *
	 * @return void
	 */
	public function init_block_widget() {
		// Automatically load dependencies and version.
		$asset_file = include WCPAY_ABSPATH . 'dist/multi-currency-switcher-block.asset.php';

		wp_register_script(
			'woocommerce-payments/multi-currency-switcher',
			plugins_url( 'dist/multi-currency-switcher-block.js', WCPAY_PLUGIN_FILE ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		register_block_type(
			'woocommerce-payments/multi-currency-switcher',
			[
				'api_version'     => 2,
				'editor_script'   => 'woocommerce-payments/multi-currency-switcher',
				'render_callback' => [ $this, 'render_block_widget' ],
				'attributes'      => [
					'title'  => [
						'type' => 'string',
					],
					'symbol' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'flag'   => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);
	}

	/**
	 * Render the content of the block widget. Called by the render_callback callback.
	 * Normally, this could be all done on the JS side, however we need to use a dynamic
	 * block here because the currencies enabled on a site could change, and this would not update
	 * properly on the Gutenberg block, because it is cached.
	 *
	 * @param array  $block_attributes The attributes (settings) applicable to this block. We expect this will contain
	 * the widget title, and whether or not we should render both flags and symbols.
	 * @param string $content The existing widget content. Will be an empty string, because the `save()` function
	 * on the JS side is set to return null to force usage of the dynamic widget render_callback.
	 *
	 * @return string The content to be displayed inside the block widget.
	 */
	public function render_block_widget( $block_attributes, $content ): string {
		if ( $this->compatibility->should_hide_widgets() ) {
			return '';
		}

		$title       = $block_attributes['title'] ?? '';
		$with_symbol = $block_attributes['symbol'] ?? true;
		$with_flag   = $block_attributes['flag'] ?? false;
		$aria_label  = ! empty( $title ) ? $title : 'Currency Selector';

		$widget_content = '';

		if ( ! empty( $title ) ) {
			$widget_content .= '<span class="gamma widget-title">' . $title . '</span>';
		}

		$widget_content .= '<form>';
		$widget_content .= '<select name="currency" aria-label="' . $aria_label . '" onchange="this.form.submit()">';

		foreach ( $this->multi_currency->get_enabled_currencies() as $currency ) {
			$widget_content .= $this->render_currency_option( $currency, $with_symbol, $with_flag );
		}

		$widget_content .= '</select>';
		$widget_content .= '</form>';
		return $widget_content;
	}

	/**
	 * Create an <option> element with provided currency. With symbol and flag if requested.
	 *
	 * @param Currency $currency    Currency to use for <option> element.
	 * @param boolean  $with_symbol Whether to show the currency symbol.
	 * @param boolean  $with_flag   Whether to show the currency flag.
	 *
	 * @return string Display HTML of currency <option>, as a string.
	 */
	private function render_currency_option( Currency $currency, bool $with_symbol, bool $with_flag ): string {
		$code        = $currency->get_code();
		$same_symbol = html_entity_decode( $currency->get_symbol() ) === $code;
		$text        = $code;
		$selected    = $this->multi_currency->get_selected_currency()->code === $code ? 'selected' : '';

		if ( $with_symbol && ! $same_symbol ) {
			$text = $currency->get_symbol() . ' ' . $text;
		}
		if ( $with_flag ) {
			$text = $currency->get_flag() . ' ' . $text;
		}

		return '<option value="' . $code . '" ' . $selected . '>' . $text . '</option>';
	}
}
