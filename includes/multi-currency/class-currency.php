<?php
/**
 * Class Currency
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Multi Currency Currency object.
 */
class Currency implements \JsonSerializable {

	/**
	 * Three letter currency code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Currency conversion rate.
	 *
	 * @var float
	 */
	public $rate;

	/**
	 * Constructor.
	 *
	 * @param string $code Three letter currency code.
	 * @param float  $rate The conversion rate.
	 */
	public function __construct( $code = '', $rate = 1.0 ) {
		$this->code = $code;
		$this->rate = $rate;
	}

	/**
	 * Retrieves the currency's translated name from WooCommerce core.
	 *
	 * @param string $code The currency code.
	 */
	public function get_currency_name_from_code( $code ) {
		$wc_currencies = get_woocommerce_currencies();
		return $wc_currencies[ $code ];
	}

	/**
	 * Retrieves the currency's code.
	 *
	 * @return string Three letter currency code.
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Retrieves the currency's flag.
	 *
	 * @return string Currency flag.
	 */
	public function get_flag() {
		// Maybe add param img/emoji to return which you want?
		return '';
	}

	/**
	 * Retrieves the currency code lowercased.
	 *
	 * @return string Currency code lowercased.
	 */
	public function get_id() {
		return strtolower( $this->code );
	}

	/**
	 * Retrieves the currency's name from WooCommerce core.
	 *
	 * @return string Currency name.
	 */
	public function get_name() {
		$wc_currencies = get_woocommerce_currencies();
		return $wc_currencies[ $this->code ];
	}

	/**
	 * Retrieves the currency's conversion rate.
	 *
	 * @return float The conversion rate.
	 */
	public function get_rate() {
		return $this->rate;
	}

	/**
	 * Retrieves the currency's symbol from WooCommerce core.
	 *
	 * @return string Currency symbol.
	 */
	public function get_symbol() {
		return get_woocommerce_currency_symbol( $this->code );
	}

	/**
	 * Specify the data that should be serialized to JSON.
	 *
	 * @return mixed Serialized Currency object.
	 */
	public function jsonSerialize() {
		return [
			'code'   => $this->code,
			'rate'   => $this->get_rate(),
			'name'   => $this->get_name(),
			'id'     => $this->get_id(),
			'flag'   => $this->get_flag(),
			'symbol' => $this->get_symbol(),
		];
	}
}
