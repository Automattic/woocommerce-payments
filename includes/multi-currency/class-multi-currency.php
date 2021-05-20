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
	 * Frontend_Prices instance.
	 *
	 * @var Frontend_Prices
	 */
	protected $frontend_prices;

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
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-frontend-prices.php';

		$this->id = 'wcpay_multi_currency';
		$this->get_available_currencies();
		$this->get_default_currency();
		$this->get_enabled_currencies();

		add_action( 'rest_api_init', [ __CLASS__, 'init_rest_api' ] );

		$is_frontend_request = ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! WC()->is_rest_api_request();
		if ( $is_frontend_request ) {
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ] );
			$this->frontend_prices = new Frontend_Prices( $this );
		}
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
		if ( WC()->session ) {
			$code = WC()->session->get( self::CURRENCY_SESSION_KEY );
			return $this->get_enabled_currencies()[ $code ] ?? $this->default_currency;
		}

		return $this->default_currency;
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

		if ( $currency && WC()->session ) {
			WC()->session->set( self::CURRENCY_SESSION_KEY, $currency->code );
		}
	}

	/**
	 * Gets the rounding precision in the format used by round().
	 *
	 * @return int The rounding precision.
	 */
	public function get_round_precision(): float {
		return apply_filters( 'wcpay_multi_currency_round_precision', 0 );
	}

	/**
	 * Gets the charm pricing to be added to the converted price after rounding.
	 *
	 * @return float The charm pricing.
	 */
	public function get_charm_pricing(): float {
		return apply_filters( 'wcpay_multi_currency_charm_pricing', -0.1 );
	}

	/**
	 * Gets the configured value for apply charm pricing only to products.
	 *
	 * @return bool The configured value.
	 */
	public function get_apply_charm_only_to_products() {
		return apply_filters( 'wcpay_multi_currency_apply_charm_only_to_products', true );
	}

	/**
	 * Gets the converted price using the current currency with the rounding and charm pricing settings.
	 *
	 * @param mixed $price      The price to be converted.
	 * @param bool  $is_product True if converting the price for a product.
	 *
	 * @return float The converted price.
	 */
	public function get_price( $price, $is_product = true ): float {
		$current_currency = $this->get_selected_currency();

		if ( $current_currency->get_code() === $this->get_default_currency()->get_code() ) {
			return (float) $price;
		}

		$converted_price     = ( (float) $price ) * $current_currency->get_rate();
		$apply_charm_pricing = $this->get_apply_charm_only_to_products() ? $is_product : true;

		return $this->get_adjusted_price( $converted_price, $apply_charm_pricing );
	}

	/**
	 * Gets the price after adjusting it with the rounding and charm settings.
	 *
	 * @param float $price               The price to be adjusted.
	 * @param bool  $apply_charm_pricing Whether charm pricing should be applied.
	 *
	 * @return float The adjusted price.
	 */
	protected function get_adjusted_price( $price, $apply_charm_pricing ): float {
		$precision = $this->get_round_precision();
		$charm     = $this->get_charm_pricing();

		// TODO: round up.
		$adjusted_price = round( $price, $precision );

		if ( $apply_charm_pricing ) {
			$adjusted_price += $charm;
		}

		// Do not return negative prices (possible because of $charm).
		return max( 0, $adjusted_price );
	}
}
