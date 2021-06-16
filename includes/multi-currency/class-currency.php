<?php
/**
 * Class Currency
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Multi_Currency;

use WC_Payments_Utils;

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
	 * Currency charm rate after conversion and rounding.
	 *
	 * @var float|null
	 */
	private $charm;

	/**
	 * Is currency default for store?
	 *
	 * @var bool|null
	 */
	private $is_default;

	/**
	 * Currency rounding rate after conversion.
	 *
	 * @var float|null
	 */
	private $rounding;

	/**
	 * Is currency zero decimal?
	 *
	 * @var bool|null
	 */
	private $is_zero_decimal;


	/**
	 * Constructor.
	 *
	 * @param string $code Three letter currency code.
	 * @param float  $rate The conversion rate.
	 */
	public function __construct( $code = '', $rate = 1.0 ) {
		$this->code = $code;
		$this->rate = $rate;

		if ( get_woocommerce_currency() === $code ) {
			$this->is_default = true;
		}

		if ( in_array( strtolower( $code ), WC_Payments_Utils::zero_decimal_currencies(), true ) ) {
			$this->is_zero_decimal = true;
		}
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
	 * Retrieves the currency's charm rate.
	 *
	 * @return float Charm rate.
	 */
	public function get_charm() {
		return is_null( $this->charm ) ? 0.00 : $this->charm;
	}

	/**
	 * Retrieves the currency's flag.
	 *
	 * @return string Currency flag.
	 */
	public function get_flag() {
		// Maybe add param img/emoji to return which you want?
		return Country_Flags::get_by_currency( $this->code );
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
	 * Retrieves if the currency is default for the store.
	 *
	 * @return bool
	 */
	public function get_is_default() {
		return $this->is_default || false;
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
	 * Retrieves the currency's rounding rate.
	 *
	 * @return string Rounding rate.
	 */
	public function get_rounding() {
		return is_null( $this->rounding ) ? 'none' : $this->rounding;
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
	 * Retrieves if the currency is zero decimal.
	 *
	 * @return bool
	 */
	public function get_is_zero_decimal() {
		return $this->is_zero_decimal || false;
	}

	/**
	 * Sets the currency's charm rate.
	 *
	 * @param float $charm Charm rate.
	 */
	public function set_charm( $charm ) {
		$this->charm = $charm;
	}

	/**
	 * Sets the currency's conversion rate.
	 *
	 * @param float $rate Conversion rate.
	 */
	public function set_rate( $rate ) {
		$this->rate = $rate;
	}

	/**
	 * Sets the currency's rounding rate.
	 *
	 * @param string $rounding Rounding rate.
	 */
	public function set_rounding( $rounding ) {
		$this->rounding = $rounding;
	}

	/**
	 * Specify the data that should be serialized to JSON.
	 *
	 * @return mixed Serialized Currency object.
	 */
	public function jsonSerialize() {
		return [
			'code'            => $this->code,
			'rate'            => $this->get_rate(),
			'name'            => html_entity_decode( $this->get_name() ),
			'id'              => $this->get_id(),
			'is_default'      => $this->get_is_default(),
			'flag'            => $this->get_flag(),
			'symbol'          => html_entity_decode( $this->get_symbol() ),
			'is_zero_decimal' => $this->get_is_zero_decimal(),
		];
	}
}
