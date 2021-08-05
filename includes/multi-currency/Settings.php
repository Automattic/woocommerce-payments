<?php
/**
 * WooCommerce Payments Multi-currency Settings
 *
 * @package WooCommerce\Admin
 */

namespace WCPay\MultiCurrency;

use WCPay\MultiCurrency\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class Settings extends \WC_Settings_Page {

	/**
	 * The id of the plugin.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * The tab label.
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Instance of MultiCurrency class.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * Link to the multi-currency documentation page.
	 *
	 * @var string
	 */
	const LEARN_MORE_URL = 'https://docs.woocommerce.com/document/payments/currencies/multi-currency-setup/';

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;
		$this->id             = $this->multi_currency->id;
		$this->label          = _x( 'Multi-currency', 'Settings tab label', 'woocommerce-payments' );

		// TODO: We need to re-enable it on every screen we use emoji flags. Until WC Admin decide if they will enable it too: https://github.com/woocommerce/woocommerce-admin/issues/6388.
		add_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		add_action( 'woocommerce_admin_field_wcpay_enabled_currencies_list', [ $this, 'enabled_currencies_list' ] );
		add_action( 'woocommerce_admin_field_wcpay_currencies_settings_section_start', [ $this, 'currencies_settings_section_start' ] );
		add_action( 'woocommerce_admin_field_wcpay_currencies_settings_section_end', [ $this, 'currencies_settings_section_end' ] );

		add_action( 'woocommerce_admin_field_wcpay_single_currency_preview_helper', [ $this, 'single_currency_preview_helper' ] );

		add_action( 'woocommerce_settings_' . $this->id, [ $this, 'render_single_currency_breadcrumbs' ] );

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		return apply_filters(
			'woocommerce_get_sections_' . $this->id,
			[
				'' => __( 'Currencies', 'woocommerce-payments' ),
			]
		);
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Section being shown.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		$settings = [];

		if ( '' === $current_section ) {
			$settings = apply_filters(
				$this->id . '_enabled_currencies_settings',
				[
					[
						'title' => __( 'Enabled currencies', 'woocommerce-payments' ),
						'desc'  => sprintf(
							/* translators: %s: url to documentation. */
							__( 'Accept payments in multiple currencies. Prices are converted based on exchange rates and rounding rules. <a href="%s">Learn more</a>', 'woocommerce-payments' ),
							self::LEARN_MORE_URL
						),
						'type'  => 'title',
						'id'    => $this->id . '_enabled_currencies',
					],

					[
						'type' => 'wcpay_enabled_currencies_list',
					],

					[
						'type' => 'sectionend',
						'id'   => $this->id . '_enabled_currencies',
					],

					[
						'type' => 'wcpay_currencies_settings_section_start',
					],

					[
						'title' => __( 'Store settings', 'woocommerce-payments' ),
						'desc'  => sprintf(
							/* translators: %s: url to documentation. */
							__( 'Store settings allow your customers to choose which currency they would like to use when shopping at your store. <a href="%s" target="blank">Learn more</a>', 'woocommerce-payments' ),
							self::LEARN_MORE_URL
						),
						'type'  => 'title',
						'id'    => $this->id . '_store_settings',
						'class' => $this->id . '_store_settings_input',
					],

					[
						'title'    => __( 'Store settings', 'woocommerce-payments' ),
						'desc'     => __( 'Automatically switch customers to their local currency if it is enabled above.', 'woocommerce-payments' ),
						// TODO: Preview link, to be done on #2523.
						'desc_tip' => __( 'Customers will be notified via store alert banner.', 'woocommerce-payments' ),
						'id'       => $this->id . '_enable_auto_currency',
						'default'  => 'yes',
						'type'     => 'checkbox',
					],

					[
						'desc' => sprintf(
						/* translators: %s: url to the widgets page */
							__( 'A currency switcher is available in your widgets. <a href="%s">Configure now</a>', 'woocommerce-payments' ),
							'widgets.php'
						),
						'type' => 'title',
						'id'   => $this->id . '_store_settings_widgets_link',
					],

					[
						'type' => 'sectionend',
						'id'   => $this->id . 'store_settings',
					],

					[
						'type' => 'wcpay_currencies_settings_section_end',
					],
				]
			);
		} else {
			$settings = $this->get_currency_setting( $this->multi_currency->get_enabled_currencies()[ strtoupper( $current_section ) ] );
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Output the settings.
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		\WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Output container for enabled currencies list.
	 */
	public function enabled_currencies_list() {
		?>
		<tr valign="top">
			<td class="wcpay_enabled_currencies_wrapper">
				<div id="wcpay_enabled_currencies_list" aria-describedby="wcpay_enabled_currencies-description"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output section start for store settings.
	 */
	public function currencies_settings_section_start() {
		?>
		<div id="wcpay_currencies_settings_section" style="display: none;">
		<?php
	}

	/**
	 * Output section end for store settings.
	 */
	public function currencies_settings_section_end() {
		?>
		</div>
		<?php
	}

	/**
	 * Output hidden fields for preview.
	 */
	public function single_currency_preview_helper() {
		global $current_section;
		$available_currencies = $this->multi_currency->get_available_currencies();
		$currency             = $available_currencies[ strtoupper( $current_section ) ];
		$default_currency     = $this->multi_currency->get_default_currency();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="wcpay_multi_currency_preview_default"><?php echo esc_html( $default_currency->get_name() ); ?></label>
			</th>
			<td class="forminp forminp-text">
				<span style="line-height:30px"><?php echo esc_html( $default_currency->get_symbol() ); ?></span>
				<input name="wcpay_multi_currency_preview_default" id="wcpay_multi_currency_preview_default" type="text" value="20.00" placeholder="20.00">
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="wcpay_multi_currency_preview_converted"><?php echo esc_html( $currency->get_name() ); ?></label>
			</th>
			<td>
				<div id="wcpay_multi_currency_preview_converted">
					<span style="display:inline-block;"></span>
				</div>
				<input type="hidden"
					name="<?php echo esc_attr( $this->id . '_automatic_exchange_rate' ); ?>"
					value="<?php echo esc_attr( $available_currencies[ $currency->get_code() ]->get_rate() ); ?>"
				/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns the settings for the single currency.
	 *
	 * @param Currency $currency The currency object we're getting settings for.
	 *
	 * @return array Array of settings.
	 */
	public function get_currency_setting( $currency ) {
		$available_currencies = $this->multi_currency->get_available_currencies();
		$default_currency     = $this->multi_currency->get_default_currency();
		$page_id              = $this->id . '_single_currency';

		if ( $default_currency->get_is_zero_decimal() ) {
			$default_rate   = '1,000' . $default_currency->get_code();
			$converted_rate = number_format( $available_currencies[ $currency->get_code() ]->get_rate() * 1000, 4 ) . ' ' . $currency->get_code();
		} else {
			$default_rate   = '1' . $default_currency->get_code();
			$converted_rate = number_format( $available_currencies[ $currency->get_code() ]->get_rate(), 4 ) . ' ' . $currency->get_code();
		}

		$last_updated = ! is_null( $available_currencies[ $currency->get_code() ]->get_last_updated() )
			? $this->format_last_updated_date( $available_currencies[ $currency->get_code() ]->get_last_updated() )
			: 'Error - Unable to fetch automatic rate for this currency';

		$exchange_rate_options = [
			'automatic' => sprintf(
				/* translators: %1$s: default currency rate, %2$s: new currency exchange rate, %3$s: time rates were last updated. */
				__( 'Fetch rate automatically. Current rate: %1$s = %2$s (Last updated: %3$s)', 'woocommerce-payments' ),
				$default_rate,
				$converted_rate,
				$last_updated
			),
			'manual'    => __( 'Manual rate. Enter your own fixed rate of exchange.', 'woocommerce-payments' ),
		];

		$decimal_currency_rounding_options      = [
			'none'  => __( 'None', 'woocommerce-payments' ),
			'0.25'  => '0.25',
			'0.50'  => '0.50',
			'1.00'  => '1.00 (recommended)',
			'5.00'  => '5.00',
			'10.00' => '10.00',
		];
		$zero_decimal_currency_rounding_options = [
			'1'    => '1',
			'10'   => '10',
			'25'   => '25',
			'50'   => '50',
			'100'  => '100 (recommended)',
			'500'  => '500',
			'1000' => '1000',
		];

		$rounding_options = apply_filters(
			$this->id . '_price_rounding_options',
			$currency->get_is_zero_decimal() ? $zero_decimal_currency_rounding_options : $decimal_currency_rounding_options
		);

		$decimal_currency_charm_options      = [
			'0.00'  => __( 'None', 'woocommerce-payments' ),
			'-0.01' => '-0.01',
			'-0.05' => '-0.05',
		];
		$zero_decimal_currency_charm_options = [
			'0.00' => __( 'None', 'woocommerce-payments' ),
			'-1'   => '-1',
			'-5'   => '-5',
			'-10'  => '-10',
			'-20'  => '-20',
			'-25'  => '-25',
			'-50'  => '-50',
			'-100' => '-100',
		];

		$charm_options = apply_filters(
			$this->id . '_charm_options',
			$currency->get_is_zero_decimal() ? $zero_decimal_currency_charm_options : $decimal_currency_charm_options
		);

		$preview_desc = sprintf(
			/* translators: %1$s: default currency of the store, %2$s currency being converted to. */
			__( 'Enter a price in your default currency of %1$s to see it converted into %2$s using the exchange rate and formatting rules above.', 'woocommerce-payments' ),
			$default_currency->get_code(),
			$currency->get_code()
		);

		return apply_filters(
			$this->id . '_single_settings',
			[
				[
					'type' => 'title',
					'id'   => $page_id,
				],

				[
					'title'   => __( 'Exchange rate', 'woocommerce-payments' ),
					'id'      => $this->id . '_exchange_rate_' . $currency->get_id(),
					'class'   => 'exchange-rate-selector',
					'default' => 'automatic',
					'type'    => 'radio',
					'options' => $exchange_rate_options,
				],

				[
					'title'             => __( 'Manual rate', 'woocommerce-payments' ),
					'type'              => 'text',
					'id'                => $this->id . '_manual_rate_' . $currency->get_id(),
					'class'             => 'input-text regular-input',
					'default'           => $currency->get_rate(),
					'desc'              => __( 'Enter the manual rate you would like to use. Must be a positive number.', 'woocommerce-payments' ),
					'desc_tip'          => true,
					'custom_attributes' => [
						'pattern' => '[0-9]*(\.[0-9]+)?',
					],
				],

				[
					'type' => 'sectionend',
					'id'   => $page_id,
				],

				[
					'title' => __( 'Formatting rules', 'woocommerce-payments' ),
					'type'  => 'title',
					'id'    => $page_id . '_formatting_rules',
				],

				[
					'title'   => __( 'Price rounding', 'woocommerce-payments' ),
					'desc'    => sprintf(
						/* translators: %1$s currency being converted to. */
						__( 'Make your %1$s prices consistent by rounding them up after they are converted.', 'woocommerce-payments' ),
						$currency->get_code()
					),
					'id'      => $this->id . '_price_rounding_' . $currency->get_id(),
					'default' => $currency->get_is_zero_decimal() ? '100' : '1.00',
					'type'    => 'select',
					'options' => $rounding_options,
				],

				[
					'title'   => __( 'Price charm', 'woocommerce-payments' ),
					'desc'    => __( 'Reduce the converted price by a specific amount.', 'woocommerce-payments' ),
					'id'      => $this->id . '_price_charm_' . $currency->get_id(),
					'default' => '0.00',
					'type'    => 'select',
					'options' => $charm_options,
				],

				[
					'type' => 'sectionend',
					'id'   => $page_id . '_formatting_rules',
				],

				[
					'name' => __( 'Preview', 'woocommerce-payments' ),
					'type' => 'title',
					'desc' => $preview_desc,
					'id'   => $page_id . '_preview',
				],

				[
					'type' => 'wcpay_single_currency_preview_helper',
				],

				[
					'type' => 'sectionend',
					'id'   => $page_id . '_preview',
				],
			]
		);
	}

	/**
	 * Save settings.
	 */
	public function save() {
		global $current_section;

		// Save all settings through the settings API.
		\WC_Admin_Settings::save_fields( $this->get_settings( $current_section ) );

		// If we are saving the settings for an individual currency, we have some additional logic.
		if ( '' !== $current_section ) {
			// If the manual rate was blank, or zero, we set it to the automatic rate.
			$manual_rate = get_option( $this->id . '_manual_rate_' . $current_section, false );
			if ( ! $manual_rate || 0 >= $manual_rate || '' === $manual_rate ) {
				$available_currencies = $this->multi_currency->get_available_currencies();
				$selected_currency    = strtoupper( $current_section );
				if ( isset( $available_currencies[ $selected_currency ] ) ) {
					update_option( $this->id . '_manual_rate_' . $current_section, $available_currencies[ $selected_currency ]->get_rate() );
				}
			}
		}

		do_action( 'woocommerce_update_options_' . $this->id );
	}

	/**
	 * Renders the breadcrumbs for the single currency settings page.
	 */
	public function render_single_currency_breadcrumbs() {
		global $current_section;

		$currency_code = strtoupper( $current_section );
		$currencies    = $this->multi_currency->get_enabled_currencies();

		// Exit early if this is not a single currency page or the currency is not enabled.
		if ( empty( $current_section ) || ! isset( $currencies[ $currency_code ] ) ) {
			return;
		}

		$currency = $currencies[ $currency_code ];

		?>
		<h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=wcpay_multi_currency' ) ); ?>"><?php esc_html_e( 'Currencies', 'woocommerce-payments' ); ?></a> &gt; <?php echo esc_html( "{$currency->get_name()} ({$currency->get_code()}) {$currency->get_flag()}" ); ?>
		</h2>
		<?php
	}

	/**
	 * Format the last updated date using the WordPress installations date/time settings for use on the frontend.
	 *
	 * @param int $last_updated The last updated unix timestamp.
	 *
	 * @return string
	 */
	private function format_last_updated_date( int $last_updated ): string {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated );
	}
}
