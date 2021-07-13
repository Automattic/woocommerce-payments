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
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;
		$this->id             = $this->multi_currency->id;

		add_filter( $this->id . '_enabled_currencies_settings', [ $this, 'filter_store_settings' ] );
	}

	/**
	 * Adds the checkbox to the store settings to toggle the currency switched in the Storefront header.
	 *
	 * @param array $settings The settings from the Settings class.
	 */
	public function filter_store_settings( $settings ) {
		foreach ( $settings as $setting ) {
			if ( isset( $setting['id'] ) && $this->id . '_enable_auto_currency' === $setting['id'] ) {
				// Add the checkboxgroup prop to the store settings section, then add the Storefront checkbox below it.
				$setting['checkboxgroup'] = 'start';
				$new_settings[]           = $setting;
				$new_settings[]           = [
					'desc'          => __( 'Add a currency switcher to the Storefront header and mobile menu.', 'woocommerce-payments' ),
					'desc_tip'      => sprintf(
						/* translators: %s: url to the widgets page */
						__( 'A currency switcher is also available in your widgets. <a href="%s">Configure now</a>', 'woocommerce-payments' ),
						'widgets.php'
					),
					'id'            => $this->id . '_enable_storefront_switcher',
					'default'       => 'no',
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
}
