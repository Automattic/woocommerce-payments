<?php
/**
 * Class WC_Payments_Multi_Currency
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency functionality.
 */
class WC_Payments_Multi_Currency {

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Payments_Multi_Currency
	 */
	protected static $instance = null;

	/**
	 * The available currencies.
	 *
	 * @var array
	 */
	public $available_currencies;

	/**
	 * The default currency.
	 *
	 * @var object
	 */
	public $default_currency;

	/**
	 * The enabled currencies.
	 *
	 * @var array
	 */
	public $enabled_currencies;

	/**
	 * Main WC_Payments_Multi_Currency Instance.
	 *
	 * Ensures only one instance of WC_Payments_Multi_Currency is loaded or can be loaded.
	 *
	 * @static
	 * @return WC_Payments_Multi_Currency - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init.
	 */
	public function init() {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-wc-payments-multi-currency-currency.php';

		$this->id = 'wcpay_multi_currency';
		$this->get_available_currencies();
		$this->get_default_currency();
		$this->get_enabled_currencies();
	}

	/**
	 * Gets the currencies available.
	 *
	 * @return array Array of WC_Payments_Multi_Currency_Currency objects.
	 */
	public function get_available_currencies() {
		if ( isset( $this->available_currencies ) ) {
			return $this->available_currencies;
		}

		// TODO: Mock currencies to be replaced by stored values from API call.
		// Third element in the array tells it to automatically set the currency as an enabled currency.
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
			$c                     = new WC_Payments_Multi_Currency_Currency( $currency[0], $currency[1], $currency[2] );
			$available[ $c->code ] = $c;
		}
		$this->available_currencies = $available;
		return $this->available_currencies;
	}

	/**
	 * Gets the store base currency.
	 *
	 * @return object WC_Payments_Multi_Currency_Currency object.
	 */
	public function get_default_currency() {
		if ( isset( $this->default_currency ) ) {
			return $this->default_currency;
		}

		$this->default_currency = $this->available_currencies[ get_woocommerce_currency() ];

		return $this->default_currency;
	}

	/**
	 * Gets the currently enabled currencies.
	 *
	 * @return array Array of WC_Payments_Multi_Currency_Currency objects.
	 */
	public function get_enabled_currencies() {
		if ( isset( $this->enabled_currencies ) ) {
			return $this->enabled_currencies;
		}

		/*
		This is how it should work.
		// If there is no setting in the database, then use the store's default currency.
		$this->enabled_currencies = get_option( $this->id . '_enabled_currencies', $this->get_default_currency() );
		*/

		// TODO: For dev purposes until settings are finished.
		$this->enabled_currencies = get_option( $this->id . '_enabled_currencies', false );
		foreach ( $this->available_currencies as $currency ) {
			if ( $currency->auto ) {
				$this->enabled_currencies[] = $currency;
			}
		}

		return $this->enabled_currencies;
	}
}
