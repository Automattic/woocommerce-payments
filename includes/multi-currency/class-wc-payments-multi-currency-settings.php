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
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'wcpay_multi_currency';
		$this->label = _x( 'WIP Multi-currency', 'Settings tab label', 'woocommerce-payments' );

		add_action( 'woocommerce_admin_field_wcpay_multi_currencies', [ $this, 'wcpay_multi_currencies_setting' ] );
		parent::__construct();

		$this->available_currencies = $this->get_available_currencies();
		$this->enabled_currencies   = $this->get_enabled_currencies();
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
	 * Mock currencies.
	 */
	public function get_available_currencies() {
		$mock_currencies = [
			[ 'US Dollars', 'USD', '1.00', true ],
			[ 'Canadian Dollars', 'CAD', '1.206823', true ],
			[ 'British Pounds', 'GBP', '0.708099', true ],
			[ 'Euros', 'EUR', '0.826381', true ],
			[ 'Arab Emirates Dirhams', 'AED', '3.6732', false ],
			[ 'Congolese Francs', 'CDF', '2000', false ],
			[ 'New Zealand Dollars', 'NZD', '1.387163', false ],
			[ 'Danish Krones', 'DKK', '6.144615', false ],
			[ 'Burundian Francs', 'BIF', '1974', false ], // Zero dollar currency.
			[ 'Chilean Pesos', 'CLP', '706.8', false ], // Zero dollar currency.
		];

		$available = [];
		foreach ( $mock_currencies as $currency ) {
			$c       = new stdClass();
			$c->id   = strtolower( $currency[1] );
			$c->name = $currency[0];
			$c->abbr = $currency[1];
			$c->rate = $currency[2];
			$c->img  = '';
			$c->auto = $currency[3]; // Auto enable for dev purposes.

			$available[ $c->abbr ] = $c;
		}

		return $available;
	}

	/**
	 * Mock currencies.
	 */
	public function get_default_currency() {

		// For dev purposes.
		foreach ( $this->available_currencies as $currency ) {
			if ( $currency->auto ) {
				$default[] = $currency;
			}
		}
		return $default;

		return $this->available_currencies[ get_woocommerce_currency() ];
	}

	/**
	 * Mock currencies.
	 */
	public function get_enabled_currencies() {
		// This should pull from the database.
		// If there is no setting in the database, then use the store's default currency.

		return get_option( $this->id . '_enabled_currencies', $this->get_default_currency() );
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
		$wc_currency = get_woocommerce_currency();

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

						$columns = apply_filters( $this->id . '_enabled_currencies_columns', $default_columns );

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
										echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . strtolower( $currency->id ) ) ) . '" class="' . $this->id . '_name">' . wp_kses_post( $currency->name ) . '</a>';
										$abbr = '<span class="' . $this->id . '_abbreviation">(' . wp_kses_post( $currency->abbr );
										if ( $wc_currency == $currency->abbr ) {
											$abbr .= '&nbsp;&ndash;&nbsp;';
											$abbr .= __( 'Default currency', 'woocommerce-payments' );
										}
										$abbr .= ')</span>';
										echo esc_html( $abbr );

										break;
								}
								// TODO: This should probably be sprintf.
								echo '<div class="row-actions">';
								echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . strtolower( $currency->id ) ) ) . '">Edit</a> | <a href="#" class="' . $this->id . '_remove">Remove</a>';
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
	 * Don't lint me.
	 * 
	 * @param string $abbr The currency abbreviation.
	 */
	public function get_currency_name( $abbr ) {
		$wc_currencies = get_woocommerce_currencies();
		return $wc_currencies[ $abbr ];
	}

	/**
	 * This docbloc still needs to be completed.
	 * TODO: this ^
	 *
	 * @param object $currency The currency object we're getting settings for.
	 */
	public function get_currency_setting( $currency ) {
		$back_url      = esc_url( admin_url( 'admin.php?page=wc-settings&tab=' . $this->id ) );

		$currency_name = $this->get_currency_name( $currency->abbr );
		$page_title    = sprintf(
			/* translators: %1$s: url back to currencies, %2$s: current currency name for page title, %3$s: currency abbreviation. */
			__( '<a href="%1$s">Currencies</a> > %2$s (%3$s)', 'woocommerce-payments' ),
			$back_url,
			$currency_name,
			$currency->abbr
		);
		$page_id = $this->id . '_single_currency';

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
			'1000' => '$10.00',
			'100'  => __( '$1.00 (recommended)', 'woocommerce-payments' ),
			'10'   => '$0.10',
			'1'    => '$0.01',
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
			'Default currency abbr',
			$currency->abbr
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
					'title'           => __( 'Exchange rate', 'woocommerce-payments' ),
					'id'              => $this->id . '_exchange_rate_' . $currency->id,
					'default'         => 'automatic',
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
					'id'       => $this->id . '_price_rounding_' . $currency->id,
					'default'  => 100,
					'type'     => 'select',
					'options'  => apply_filters( $this->id . '_price_rounding_options', $rounding_options ),
					'desc_tip' => __( 'Recommend doing $1.00', 'woocommerce-payments' ),
				],

				[
					'name'     => __( 'Charm pricing', 'woocommerce-payments' ),
					'desc'     => $charm_desc,
					'id'       => $this->id . '_price_charm_' . $currency->id,
					'default'  => 0,
					'type'     => 'select',
					'options'  => apply_filters( $this->id . '_price_charm_options', $charm_options ),
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
					'name'        => $this->get_default_currency(), // Should be default currency.
					'id'          => $this->id . '_preview_default_' . $currency->id,
					'default'     => '$20.00',
					'type'        => 'text',
					'placeholder' => '$20.00',
				],

				[
					'name'    => $currency_name,
					'id'      => $this->id . '_preview_converted_' . $currency->id,
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

		// Save settings fields based on section.
		WC_Admin_Settings::save_fields( $this->get_settings( $current_section ) );

		if ( ! $current_section ) {
			// No current section means it's the enabled currencies.
		} elseif ( 'store' === $current_section ) {
			// Store settings.
		} else {
			// Single currency settings.
			// Update the single currency in the enabled currencies, then update the option. 
			$this->update_single_currency_settings();
		}

		do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
	}

	/**
	 * New function.
	 */
	public function update_single_currency_settings() {
		global $current_section;
		$enabled       = $this->get_enabled_currencies();
		$wc_currencies = get_woocommerce_currencies();

		$c           = new stdClass();
		$c->id       = strtolower( $current_section );
		$c->abbr     = strtoupper( $current_section );
		$c->rounding = $_POST[ $this->id . '_price_rounding_' . $current_section ];
		$c->charm    = $_POST[ $this->id . '_price_charm_' . $current_section ]; // Should use wc_clean?

		update_option( $this->id . '_enabled_currencies_previous', $enabled );
		$enabled[ $c->abbr ] = $c;
		update_option( $this->id . '_enabled_currencies', $enabled );
	}
}

return new WC_Payments_Multi_Currency_Settings();
