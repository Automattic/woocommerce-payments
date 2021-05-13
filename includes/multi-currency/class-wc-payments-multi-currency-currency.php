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
class Currency {

	/**
	 * Three letter currency code.
	 *
	 * @var string
	 */
	protected $code;

	/**
	 * Three letter currency code lowecased.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Flag image for the currency.
	 *
	 * @var string
	 */
	protected $img;

	/**
	 * Currency name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Currency conversion rate.
	 *
	 * @var string
	 */
	protected $rate;

	/**
	 * Constructor.
	 *
	 * @param string $code Three letter currency code.
	 * @param float  $rate The conversion rate.
	 */
	public function __construct( $code = '', $rate = '' ) {
		$this->code = $code;
		$this->id   = strtolower( $code );
		$this->img  = '';
		$this->name = $this->get_currency_name_from_code( $code );
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
}
