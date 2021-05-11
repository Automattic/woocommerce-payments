<?php
/**
 * WooCommerce Payments Multi-currency Settings
 *
 * @package WooCommerce\Admin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payments_Multi_Currency_Settings', false ) ) {
	return new WC_Payments_Multi_Currency_Settings();
}

/**
 * WC_Payments_Multi_Currency_Settings.
 */
class WC_Payments_Multi_Currency_Settings extends WC_Settings_Page {
	// TODO: make all filters uniform.
	/**
	 * Mock currencies.
	 */
	public function get_enabled_currencies() {
		$usd          = new stdClass();
		$usd->name    = 'US Dollars';
		$usd->abbr    = 'USD';
		$usd->img     = '';
		$usd->default = true;
		$usd->id      = strtolower( $usd->abbr );

		$cad          = new stdClass();
		$cad->name    = 'Canadian Dollars';
		$cad->abbr    = 'CAD';
		$cad->img     = '';
		$cad->default = false;
		$cad->id      = strtolower( $cad->abbr );

		$gbp          = new stdClass();
		$gbp->name    = 'British Pounds';
		$gbp->abbr    = 'GBP';
		$gbp->img     = '';
		$gbp->default = false;
		$gbp->id      = strtolower( $gbp->abbr );

		$eur          = new stdClass();
		$eur->name    = 'Euros';
		$eur->abbr    = 'EUR';
		$eur->img     = '';
		$eur->default = false;
		$eur->id      = strtolower( $eur->abbr );

		return [ $usd, $cad, $gbp, $eur ];
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'wcpay-multi-currency';
		$this->label = _x( 'WIP Multi-currency', 'Settings tab label', 'woocommerce-payments' );

		add_action( 'woocommerce_admin_field_wcpay_multi_currencies', [ $this, 'wcpay_multi_currencies_setting' ] );
		parent::__construct();

		$this->enabled_currencies = $this->get_enabled_currencies();
	}

	/**
	 * Default is going to be a currencies listing: https://d.pr/i/l17FlV
	 * There will need to be a currency adding modal: https://d.pr/i/i91u9l
	 * There will be a currency setting page: https://d.pr/i/56O81S
	 * And then an overall setting page: https://d.pr/i/TXRvOX
	 *
	 * TODO: Make sure to fix any text domains for translations.
	 */

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
				'wcpay_multi_currency_currencies',
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
						'id'    => 'wcpay_enabled_currencies',
					],

					[
						'type' => 'wcpay_multi_currencies',
					],

					[
						'type' => 'sectionend',
						'id'   => 'wcpay_enabled_currencies',
					],
				]
			);
		} elseif ( 'store' === $current_section ) {
			$settings = apply_filters(
				'wcpay_multi_currency_store_settings',
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
						'id'    => 'store_settings',
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
						'id'            => 'wcpay_mc_enable_auto_currency',
						'default'       => 'no',
						'type'          => 'checkbox',
						'checkboxgroup' => 'start',
						'autoload'      => false,
					],

					[
						'desc'          => __( 'Add a currency switcher to the cart widget', 'woocommerce-payments' ),
						// TODO: Need configure link... what is expected? Widgets?
						'desc_tip'      => sprintf(
							/* translators: %s: url to the widgets? */
							__( 'A currency switcher is also available in your widgets. <a href="%s">Configure now</a>', 'woocommerce-payments' ),
							''
						),
						'id'            => 'wcpay_mc_enable_cart_switcher',
						'default'       => 'no',
						'type'          => 'checkbox',
						'checkboxgroup' => 'end',
					],

					[
						'type' => 'sectionend',
						'id'   => 'store_settings',
					],
				]
			);
		} else {
			foreach ( $this->enabled_currencies as $currency ) {
				if ( $currency->id === $current_section ) {
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
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Output payment gateway settings.
	 */
	public function wcpay_multi_currencies_setting() {

		?>
		<tr valign="top">
		<td class="wcpay_enabled_currencies_wrapper">
			<table class="wcpay_enabled_currencies widefat" cellspacing="0" aria-describedby="wcpay_enabled_currencies-description">
				<thead>
					<tr>
						<?php
						$default_columns = [
							'name' => __( 'Currency name', 'woocommerce-payments' ),
						];

						$columns = apply_filters( 'wcpay_enabled_currencies_columns', $default_columns );

						foreach ( $columns as $key => $column ) {
							echo '<th class="' . esc_attr( $key ) . '">' . esc_html( $column ) . '</th>';
						}
						?>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $this->enabled_currencies as $currency ) {

							echo '<tr data-currency_id="' . esc_attr( $currency->id ) . '">';

							foreach ( $columns as $key => $column ) {
								echo '<td class="' . esc_attr( $key ) . '">';

								switch ( $key ) {
									case 'name':
										echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . strtolower( $currency->id ) ) ) . '" class="wcpay_multi_currencies_name">' . wp_kses_post( $currency->name ) . '</a>';
										$abbr = '<span class="wc-payment-gateway-method-name">(' . wp_kses_post( $currency->abbr );
										if ( $currency->default ) {
											$abbr .= '&nbsp;&ndash;&nbsp;';
											$abbr .= __( 'Default currency', 'woocommerce-payments' );
										}
										$abbr .= ')</span>';
										echo esc_html( $abbr );

										break;
								}
								echo '<div class="row-actions">';
								echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . strtolower( $currency->id ) ) ) . '">Edit</a> | <a href="#" class="wcpay_multi_currencies_remove">Remove</a>';
								echo '</div>';
								echo '</td>';
							}

							echo '</tr>';
						}
						?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * This docbloc still needs to be completed.
	 * TODO: this ^
	 *
	 * @param object $currency The currency object we're getting settings for.
	 */
	public function get_currency_setting( $currency ) {

		$back_url      = esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . $this->id ) );
		$currency_name = $currency->name . ' (' . $currency->abbr . ')';
		$page_title    = sprintf(
			/* translators: %1$s: url back to currencies, %2$s: current currency name for page title */
			__( '<a href="%1$s">Currencies</a> > %2$s', 'woocommerce-payments' ),
			$back_url,
			$currency_name
		);
		$page_id = 'wcpay_mc_single_currency';

		$option_automatic = sprintf(
			/* translators: %1$s: default currency rate, %2$s: new currency exchange rate, %3$s: time rates were last updated. */
			__( 'Fetch rates automatically <span>Current rate: %1$s = %2$s (Updated hourly, Last updated %3$s)', 'woocommerce-payments' ),
			'1 USD',
			'1.29 CAD',
			'12:00 UTC'
		);
		$option_manual = __( 'Manual <span>Enter your own fixed rate of exchange</span>', 'woocommerce-payments' );

		$rounding_desc = sprintf(
			/* translators: %1$s currency being converted to, %2$s url to documentation. */
			__( 'Make your %1$s prices consistent by rounding them up after they are converted. <a href="%2$s">Learn more</a>', 'woocommerce-payments' ),
			$currency->abbr,
			''
		);
		$rounding_options = [
			'100' => __( '$1.00 (recommended)', 'woocommerce-payments' ),
		];

		$charm_desc = sprintf(
			/* translators: %s: url to documentation.*/
			__( 'Reduce the converted price by a specific amount. <a href="%s">Learn more</a>', 'woocommerce-payments' ),
			''
		);
		$charm_options = [
			'0' => __( 'None', 'woocommerce-payments' ),
			'1' => '-0.01',
		];

		$preview_desc = sprintf(
			/* translators: %1$s: default currency of the store, %2$s currency being converted to. */
			__( 'Enter a price in your default currency (%1$s) to see it converted into %2$s using the excange rate and formatting rules above.', 'woocommerce-payments' ),
			'US Dollars',
			$currency->name
		);

		return apply_filters(
			'wcpay_multi_currency_single_settings',
			[
				[
					'title' => $page_title,
					'type'  => 'title',
					'id'    => $page_id,
				],

				[
					'title'           => __( 'Exchange rate', 'woocommerce-payments' ),
					'id'              => 'wcpay_mc_exchange_rate_' . $currency->id,
					'default'         => 'billing',
					'type'            => 'radio',
					'options'         => [
						'automatic' => $option_automatic,
						'manual'    => $option_manual,
					],
					'autoload'        => false,
					'show_if_checked' => 'option',
				],

				[
					'name' => __( 'Formatting rules', 'woocommerce-payments' ),
					'type' => 'title',
					'id'   => $page_id . '_formatting_rules',
				],

				[
					'name'     => __( 'Price rounding', 'woocommerce-payments' ),
					'desc'     => $rounding_desc,
					'id'       => 'wcpay_mc_price_rounding_' . $currency->id,
					'default'  => 100,
					'type'     => 'select',
					'options'  => apply_filters( 'wcpay_mc_price_rounding_options', $rounding_options ),
					'desc_tip' => __( 'Recommend doing $1.00', 'woocommerce-payments' ),
				],

				[
					'name'     => __( 'Charm pricing', 'woocommerce-payments' ),
					'desc'     => $charm_desc,
					'id'       => 'wcpay_mc_price_charm_' . $currency->id,
					'default'  => 0,
					'type'     => 'select',
					'options'  => apply_filters( 'wcpay_mc_price_charm_options', $charm_options ),
					'desc_tip' => __( 'Hey there!', 'woocommerce-payments' ),
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
					'name'        => __( 'US Dollars', 'woocommerce-payments' ), // Should be default currency.
					'id'          => $page_id . '_preview_default',
					'default'     => '$20.00',
					'type'        => 'text',
					'placeholder' => '$20.00',
				],

				[
					'name'    => $currency->name,
					'id'      => $page_id . '_preview_converted',
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
		/*
		Save not yet implemented.
		global $current_section;

		$wc_payment_gateways = WC_Payment_Gateways::instance();

		// Save settings fields based on section.
		WC_Admin_Settings::save_fields( $this->get_settings( $current_section ) );

		if ( ! $current_section ) {
		If section is empty, we're on the main settings page. This makes sure 'gateway ordering' is saved.
		$wc_payment_gateways->process_admin_options();
		$wc_payment_gateways->init();
		} else {
		There is a section - this may be a gateway or custom section.
		foreach ( $wc_payment_gateways->payment_gateways() as $gateway ) {
		if ( in_array( $current_section, array( $gateway->id, sanitize_title( get_class( $gateway ) ) ), true ) ) {
		do_action( 'woocommerce_update_options_payment_gateways_' . $gateway->id );
		$wc_payment_gateways->init();
		}
		}

		do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
		}
		*/
	}
}

return new WC_Payments_Multi_Currency_Settings();
