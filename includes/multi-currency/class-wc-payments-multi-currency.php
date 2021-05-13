<?php
/**
 * Class WC_Payments_Multi_Currency
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payments_Multi_Currency', false ) ) {
	return new WC_Payments_Multi_Currency();
}

/**
 * Class that controls Multi Currency functionality.
 */
class WC_Payments_Multi_Currency {

	/**
	 * The available currencies.
	 */
	public static $available_currencies;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ], 12 );
	}

	/**
	 * Init.
	 */
	public function init() {

		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-wc-payments-multi-currency-currency.php';

		self::$available_currencies = $this->get_available_currencies();
		
		// Add admin screens.
		if ( is_admin() ) {

			// Multi-currency settings page.
			add_filter(
				'woocommerce_get_settings_pages',
				function( $settings_pages ) {
					$settings_pages[] = include_once WCPAY_ABSPATH . 'includes/multi-currency/class-wc-payments-multi-currency-settings.php';
					return $settings_pages;
				}
			);
		}
	}

	/**
	 * Mock currencies.
	 */
	public function get_available_currencies() {
		$mock_currencies = [
			[ 'USD', '1.00', true ],
			[ 'CAD', '1.206823', true ],
			[ 'GBP', '0.708099', true ],
			[ 'EUR', '0.826381', true ],
			[ 'AED', '3.6732', false ],
			[ 'CDF', '2000', false ],
			[ 'NZD', '1.387163', false ],
			[ 'DKK', '6.144615', false ],
			[ 'BIF', '1974', false ], // Zero dollar currency.
			[ 'CLP', '706.8', false ], // Zero dollar currency.
		];

		$available = [];
		foreach ( $mock_currencies as $currency ) {
			$c = new WC_Payments_Multi_Currency_Currency( $currency[0], $currency[1], $currency[2] ); // Auto enable for dev purposes.
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
}