<?php
/**
 * WooCommerce Payments Multi-currency Settings
 *
 * @package WooCommerce\Admin
 */

namespace WCPay\Multi_Currency;

use WCPay\Multi_Currency\Currency;

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Settings', false ) ) {
	return new Settings();
}

/**
 * Settings.
 */
class Settings extends \WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'wcpay_multi_currency';
		$this->label = _x( 'Multi-currency', 'Settings tab label', 'woocommerce-payments' );

		add_action( 'woocommerce_admin_field_wcpay_multi_currencies', [ $this, 'wcpay_multi_currencies_setting' ] );
		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = [
			''      => __( 'Currencies', 'woocommerce-payments' ),
			'store' => __( 'Store settings', 'woocommerce-payments' ),
		];
		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
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
						// TODO: Learn more link needed.
						'desc'  => sprintf(
							/* translators: %s: url to documentation. */
							__( 'Accept payments in multiple currencies. Prices are converted based on exchange rates and rounding rules, and include conversion fees. <a href="%s">Learn more</a>', 'woocommerce-payments' ),
							''
						),
						'type'  => 'title',
						'id'    => $this->id . '_enabled_currencies',
					],

					[
						'type' => 'wcpay_multi_currencies',
					],

					[
						'type' => 'sectionend',
						'id'   => $this->id . '_enabled_currencies',
					],
				]
			);
		} elseif ( 'store' === $current_section ) {
			$settings = apply_filters(
				$this->id . 'store_settings',
				[
					[
						'title' => __( 'Store settings', 'woocommerce-payments' ),
						// TODO: Need learn more link.
						'desc'  => sprintf(
							/* translators: %s: url to documentation. */
							__( 'Store settings allow your customers to choose which currency they would like to use when shopping at your store. <a href="%s">Learn more</a>', 'woocommerce-payments' ),
							''
						),
						'type'  => 'title',
						'id'    => $this->id . '_store_settings',
					],

					[
						'title'         => __( 'Store settings', 'woocommerce-payments' ),
						'desc'          => __( 'Automatically switch customers to the local currency if it is enabled above.', 'woocommerce-payments' ),
						// TODO: Need preview link... what is expected?
						'desc_tip'      => sprintf(
							/* translators: %s: url to preview? */
							__( 'Customers will be notified via store alert banner. <a href="%s">Preview</a>', 'woocommerce-payments' ),
							''
						),
						'id'            => $this->id . '_enable_auto_currency',
						'default'       => 'no',
						'type'          => 'checkbox',
						'checkboxgroup' => 'start',
					],

					[
						'desc'          => __( 'Add a currency switcher to the cart widget', 'woocommerce-payments' ),
						// TODO: Need configure link... what is expected? Widgets?
						'desc_tip'      => sprintf(
							/* translators: %s: url to the widgets? */
							__( 'A currency switcher is also available in your widgets. <a href="%s">Configure now</a>', 'woocommerce-payments' ),
							''
						),
						'id'            => $this->id . '_enable_cart_switcher',
						'default'       => 'no',
						'type'          => 'checkbox',
						'checkboxgroup' => 'end',
					],

					[
						'type' => 'sectionend',
						'id'   => $this->id . 'store_settings',
					],
				]
			);
		} else {
			foreach ( WC_Payments_Multi_Currency()->get_enabled_currencies() as $currency ) {
				if ( $currency->get_id() === $current_section ) {
					$settings = $this->get_currency_setting( $currency );
				}
			}
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
	 * Output payment gateway settings.
	 */
	public function wcpay_multi_currencies_setting() {
		$wc_currency = get_woocommerce_currency();

		?>
		<tr valign="top">
			<td class="wcpay_enabled_currencies_wrapper">
				<div id="wcpay_enabled_currencies_list" aria-describedby="wcpay_enabled_currencies-description"></div>
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
		$default_currency = WC_Payments_Multi_Currency()->get_default_currency();
		$page_id          = $this->id . '_single_currency';

		$page_title = sprintf( '%1$s (%2$s)', $currency->get_name(), $currency->get_code() );

		$exchange_rate_options = [
			'automatic' => sprintf(
				/* translators: %1$s: default currency rate, %2$s: new currency exchange rate, %3$s: time rates were last updated. */
				__( 'Fetch rate automatically. Current rate: %1$s = %2$s (Last updated %3$s)', 'woocommerce-payments' ),
				'1 ' . $default_currency->get_code(),
				$currency->get_rate() . ' ' . $currency->get_code(),
				// TODO: Proper timestamp needed from API data.
				'12:00 UTC'
			),
			'manual'    => __( 'Manual rate. Enter your own fixed rate of exchange.', 'woocommerce-payments' ),
		];

		$rounding_desc = sprintf(
			/* translators: %1$s currency being converted to, %2$s url to documentation. */
			__( 'Make your %1$s prices consistent by rounding them up after they are converted. <a href="%2$s">Learn more</a>', 'woocommerce-payments' ),
			$currency->get_code(),
			// TODO: Url to documentation needed.
			''
		);
		// TODO: Formatting of the default currency according to WC core settings.
		$rounding_options = apply_filters(
			$this->id . '_price_rounding_options',
			[
				'1000' => $default_currency->get_symbol() . '10.00',
				'100'  => sprintf(
					/* translators: %s: Default currency symbol */
					__( '%s1.00 (recommended)', 'woocommerce-payments' ),
					$default_currency->get_symbol()
				),
				'10'   => $default_currency->get_symbol() . '0.10',
				'1'    => $default_currency->get_symbol() . '0.01',
				'none' => __( 'None', 'woocommerce-payments' ),
			]
		);

		$charm_desc = sprintf(
			/* translators: %s: url to documentation.*/
			__( 'Reduce the converted price by a specific amount. <a href="%s">Learn more</a>', 'woocommerce-payments' ),
			// TODO: Url to documentation needed.
			''
		);

		$preview_desc = sprintf(
			/* translators: %1$s: default currency of the store, %2$s currency being converted to. */
			__( 'Enter a price in your default currency of %1$s to see it converted into %2$s using the excange rate and formatting rules above.', 'woocommerce-payments' ),
			$default_currency->get_code(),
			$currency->get_code()
		);

		return apply_filters(
			$this->id . '_single_settings',
			[
				[
					'title' => $page_title,
					'type'  => 'title',
					'id'    => $page_id,
				],

				[
					'title'   => __( 'Exchange rate', 'woocommerce-payments' ),
					'id'      => $this->id . '_exchange_rate_' . $currency->get_id(),
					'default' => 'automatic',
					'type'    => 'radio',
					'options' => $exchange_rate_options,
				],
				// TODO: Manual rate field needs to hide if manual isn't selected.
				[
					'title'    => __( 'Manual rate', 'woocommerce-payments' ),
					'type'     => 'text',
					'id'       => $this->id . '_manual_rate_' . $currency->get_id(),
					'class'    => 'input-text regular-input',
					'default'  => $currency->get_rate(),
					'desc'     => __( 'Enter the manual rate you would like to use.', 'woocommerce-payments' ),
					'desc_tip' => true,
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
					'title'    => __( 'Price rounding', 'woocommerce-payments' ),
					'desc'     => $rounding_desc,
					'id'       => $this->id . '_price_rounding_' . $currency->get_id(),
					'default'  => 'none',
					'type'     => 'select',
					'options'  => $rounding_options,
					'desc_tip' => __( 'Conversion rates at the bank may differ from current conversion rates. Rounding up to the nearest whole dollar helps prevent losses on sales.', 'woocommerce-payments' ),
				],

				[
					'title'    => __( 'Charm pricing', 'woocommerce-payments' ),
					'desc'     => $charm_desc,
					'id'       => $this->id . '_price_charm_' . $currency->get_id(),
					'default'  => 0.00,
					'type'     => 'text',
					'desc_tip' => __( 'A value of .01 would reduce 20.00 to 19.99.', 'woocommerce-payments' ),
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
					'name'        => WC_Payments_Multi_Currency()->get_default_currency()->get_name(),
					'id'          => $this->id . '_preview_default_' . $currency->get_id(),
					'default'     => '$20.00',
					'type'        => 'text',
					'placeholder' => '$20.00',
				],

				[
					'name'    => $currency->get_name(),
					'id'      => $this->id . '_preview_converted_' . $currency->get_id(),
					'default' => '',
					'type'    => 'text',
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

		do_action( 'woocommerce_update_options_' . $this->id );
	}
}

new Settings();
