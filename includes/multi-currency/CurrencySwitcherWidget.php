<?php
/**
 * WooCommerce Payments Currency Switcher Widget
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WC_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Currency Switcher Widget Class
 */
class CurrencySwitcherWidget extends WC_Widget {

	const DEFAULT_SETTINGS = [
		'title'  => '',
		'symbol' => true,
		'flag'   => false,
	];

	/**
	 * Compatibility instance.
	 *
	 * @var Compatibility
	 */
	protected $compatibility;

	/**
	 * Multi-Currency instance.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * Register widget with WordPress.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 * @param Compatibility $compatibility The Compatibility instance.
	 */
	public function __construct( MultiCurrency $multi_currency, Compatibility $compatibility ) {
		$this->multi_currency = $multi_currency;
		$this->compatibility  = $compatibility;

		$this->widget_id          = 'currency_switcher_widget';
		$this->widget_name        = __( 'Currency Switcher Widget', 'woocommerce-payments' );
		$this->widget_description = __( 'Let your customers switch between your enabled currencies', 'woocommerce-payments' );
		$this->settings           = [
			'title'  => [
				'type'  => 'text',
				'std'   => '',
				'label' => __( 'Title', 'woocommerce-payments' ),
			],
			'symbol' => [
				'type'  => 'checkbox',
				'std'   => true,
				'label' => __( 'Display currency symbols', 'woocommerce-payments' ),
			],
			'flag'   => [
				'type'  => 'checkbox',
				'std'   => false,
				'label' => __( 'Display flags in supported devices', 'woocommerce-payments' ),
			],
		];

		parent::__construct();
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		if ( $this->compatibility->should_hide_widgets() ) {
			return;
		}

		$enabled_currencies = $this->multi_currency->get_enabled_currencies();

		if ( 1 === count( $enabled_currencies ) ) {
			return;
		}

		$instance = wp_parse_args(
			$instance,
			self::DEFAULT_SETTINGS
		);

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		?>
		<form>
			<?php $this->output_get_params(); ?>
			<select
				name="currency"
				aria-label="<?php echo esc_attr( $title ); ?>"
				onchange="this.form.submit()"
			>
				<?php
				foreach ( $enabled_currencies as $currency ) {
					$this->display_currency_option( $currency, $instance['symbol'], $instance['flag'] );
				}
				?>
			</select>
		</form>
		<?php

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Create an <option> element with provided currency. With symbol and flag if requested.
	 *
	 * @param Currency $currency    Currency to use for <option> element.
	 * @param boolean  $with_symbol Whether to show the currency symbol.
	 * @param boolean  $with_flag   Whether to show the currency flag.
	 *
	 * @return void Displays HTML of currency <option>
	 */
	private function display_currency_option( Currency $currency, bool $with_symbol, bool $with_flag ) {
		$code        = $currency->get_code();
		$same_symbol = html_entity_decode( $currency->get_symbol() ) === $code;
		$text        = $code;
		$selected    = $this->multi_currency->get_selected_currency()->code === $code ? ' selected' : '';

		if ( $with_symbol && ! $same_symbol ) {
			$text = $currency->get_symbol() . ' ' . $text;
		}
		if ( $with_flag ) {
			$text = $currency->get_flag() . ' ' . $text;
		}

		echo "<option value=\"$code\"$selected>$text</option>"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Output hidden inputs for every $_GET param.
	 * This prevent the switcher form to remove them on submit.
	 *
	 * @return void
	 */
	private function output_get_params() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_GET ) ) {
			return;
		}
		$params = explode( '&', urldecode( http_build_query( $_GET ) ) );
		foreach ( $params as $param ) {
			$name_value = explode( '=', $param );
			$name       = $name_value[0];
			$value      = $name_value[1];
			if ( 'currency' === $name ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}
}
