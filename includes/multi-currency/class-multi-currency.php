<?php
/**
 * Class Multi_Currency
 *
 * @package WooCommerce\Payments\Multi_Currency
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency functionality.
 */
class Multi_Currency {

	const CURRENCY_SESSION_KEY = 'wcpay_currency';

	/**
	 * The single instance of the class.
	 *
	 * @var Multi_Currency
	 */
	protected static $instance = null;

	/**
	 * The available currencies.
	 *
	 * @var array
	 */
	protected $available_currencies;

	/**
	 * The default currency.
	 *
	 * @var object
	 */
	protected $default_currency;

	/**
	 * The enabled currencies.
	 *
	 * @var array
	 */
	protected $enabled_currencies;

	/**
	 * Main Multi_Currency Instance.
	 *
	 * Ensures only one instance of Multi_Currency is loaded or can be loaded.
	 *
	 * @static
	 * @return Multi_Currency - Main instance.
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
	private function __construct() {
		$this->init();
	}

	/**
	 * Init.
	 */
	public function init() {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-currency.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-country-flags.php';

		$this->id = 'wcpay_multi_currency';
		$this->get_available_currencies();
		$this->get_default_currency();
		$this->get_enabled_currencies();

		add_action( 'init', [ $this, 'update_selected_currency_by_url' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'init_rest_api' ] );
	}

	/**
	 * Initialize the REST API controller.
	 */
	public static function init_rest_api() {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-wc-rest-controller.php';
		$api_controller = new WC_REST_Controller( \WC_Payments::create_api_client() );
		$api_controller->register_routes();
	}

	/**
	 * Gets the mock available.
	 *
	 * @return array Array of currencies.
	 */
	public function get_mock_currencies() {
		return [
			[ 'USD', '1.00' ],
			[ 'CAD', '1.206823' ],
			[ 'GBP', '0.708099' ],
			[ 'EUR', '0.826381' ],
			[ 'AED', '3.6732' ],
			[ 'CDF', '2000' ],
			[ 'NZD', '1.387163' ],
			[ 'DKK', '6.144615' ],
			[ 'BIF', '1974' ], // Zero dollar currency.
			[ 'CLP', '706.8' ], // Zero dollar currency.
		];
	}

	/**
	 * Gets the currencies available.
	 *
	 * @return array Array of Currency objects.
	 */
	public function get_available_currencies() {
		if ( isset( $this->available_currencies ) ) {
			return $this->available_currencies;
		}

		// TODO: This will need to get stored data, then build and return it accordingly.
		$currencies = $this->get_mock_currencies();
		foreach ( $currencies as $currency ) {
			$this->available_currencies[ $currency[0] ] = new Currency( $currency[0], $currency[1] );
		}
		return $this->available_currencies;
	}

	/**
	 * Gets the store base currency.
	 *
	 * @return Currency The store base currency.
	 */
	public function get_default_currency(): Currency {
		if ( isset( $this->default_currency ) ) {
			return $this->default_currency;
		}

		$this->default_currency = $this->available_currencies[ get_woocommerce_currency() ];

		return $this->default_currency;
	}

	/**
	 * Gets the currently enabled currencies.
	 *
	 * @return array Array of Currency objects.
	 */
	public function get_enabled_currencies() {
		if ( isset( $this->enabled_currencies ) ) {
			return $this->enabled_currencies;
		}

		$this->enabled_currencies = get_option( $this->id . '_enabled_currencies', false );
		if ( ! $this->enabled_currencies ) {

			// TODO: Remove dev mode option here.
			if ( get_option( 'wcpaydev_dev_mode', false ) ) {
				$count = 0;
				foreach ( $this->available_currencies as $currency ) {
					$this->enabled_currencies[ $currency->code ] = $currency;
					if ( $count >= 3 ) {
						break;
					}
					$count++;
				}
			} else {
				$default = $this->get_default_currency();
				// Need to set the default as an array.
				$this->enabled_currencies[ $default->code ] = $default;
			}
		}

		return $this->enabled_currencies;
	}

	/**
	 * Gets the user selected currency, or `$default_currency` if is not set.
	 *
	 * @return Currency
	 */
	public function get_selected_currency(): Currency {
		$code = WC()->session->get( self::CURRENCY_SESSION_KEY );
		return $this->get_enabled_currencies()[ $code ] ?? $this->default_currency;
	}

	/**
	 * Update the selected currency from url param `currency`.
	 *
	 * @return void
	 */
	public function update_selected_currency_by_url() {
		if ( ! isset( $_GET['currency'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$code     = strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$currency = $this->get_enabled_currencies()[ $code ] ?? null;

		if ( $currency ) {
			WC()->session->set( self::CURRENCY_SESSION_KEY, $currency->code );
		}
	}

}
