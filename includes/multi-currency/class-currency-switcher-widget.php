<?php
/**
 * WooCommerce Payments Currency Switcher Widget
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Multi_Currency;

use WP_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Currency Switcher Widget Class
 */
class Currency_Switcher_Widget extends WP_Widget {

	const DEFAULT_SETTINGS = [
		'title'  => '',
		'symbol' => true,
		'flag'   => false,
	];

	/**
	 * Multi-Currency instance.
	 *
	 * @var Multi_Currency
	 */
	protected $multi_currency;

	/**
	 * Register widget with WordPress.
	 *
	 * @param Multi_Currency $multi_currency The Multi_Currency instance.
	 */
	public function __construct( Multi_Currency $multi_currency ) {
		$this->multi_currency = $multi_currency;

		parent::__construct(
			'currency_switcher_widget',
			__( 'Currency Switcher', 'woocommerce-payments' ),
			[ 'description' => __( 'Let your customers switch between your enabled currencies', 'woocommerce-payments' ) ]
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args(
			$instance,
			self::DEFAULT_SETTINGS
		);

		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		?>
		<form>
			<select
				name="currency"
				aria-label="<?php echo esc_attr( $title ); ?>"
				onchange="this.form.submit()"
			>
				<?php
				foreach ( $this->multi_currency->get_enabled_currencies() as $currency ) {
					$this->display_currency_option( $currency, $instance['symbol'], $instance['flag'] );
				}
				?>
			</select>
		</form>
		<?php

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args(
			$instance,
			self::DEFAULT_SETTINGS
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'woocommerce-payments' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $instance['title'] ); ?>"
			/>
		</p>
		<p>
			<input
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'symbol' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'symbol' ) ); ?>"
				type="checkbox"<?php checked( $instance['symbol'] ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'symbol' ) ); ?>">
				<?php esc_html_e( 'Display currency symbols', 'woocommerce-payments' ); ?>
			</label>
			<br/>
			<input
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'flag' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'flag' ) ); ?>"
				type="checkbox"<?php checked( $instance['flag'] ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'flag' ) ); ?>">
				<?php esc_html_e( 'Display flags', 'woocommerce-payments' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = [
			'title'  => sanitize_text_field( $new_instance['title'] ),
			'symbol' => ( $new_instance['symbol'] ?? null ) ? 1 : 0,
			'flag'   => ( $new_instance['flag'] ?? null ) ? 1 : 0,
		];

		return $instance;
	}

	/**
	 * Create an <option> element with provided currency. With symbol and flag if requested.
	 *
	 * @param Currency $currency    Currency to use for <option> element.
	 * @param boolean  $with_symbol Whether to show the currency symbol.
	 * @param boolean  $with_flag   Whether to show the currency flag.
	 * @return void Displays HTML of currency <option>
	 */
	private function display_currency_option( Currency $currency, bool $with_symbol, bool $with_flag ) {
		$code     = $currency->get_code();
		$text     = $code;
		$selected = $this->multi_currency->get_selected_currency()->code === $code ? ' selected' : '';

		if ( $with_symbol ) {
			$text = $currency->get_symbol() . ' ' . $text;
		}
		if ( $with_flag ) {
			$text = $currency->get_flag() . ' ' . $text;
		}

		echo "<option value=\"$code\"$selected>$text</option>"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
