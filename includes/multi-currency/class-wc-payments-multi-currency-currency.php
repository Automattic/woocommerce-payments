<?php
/**
 * Class WC_Payments_Multi_Currency_Currency
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Multi Currency Currency object.
 */
class WC_Payments_Multi_Currency_Currency {

	/**
	 * Constructor.
	 *
	 * @param string $code Three letter currency code.
	 * @param float  $rate The conversion rate.
	 * @param bool   $auto Whether to enable by default or not, for dev purposes.
	 */
	public function __construct( $code = '', $rate = '', $auto = false ) {
		$this->id   = strtolower( $code );
		$this->name = $this->get_currency_name_from_code( $code );
		$this->code = $code;
		$this->rate = $rate;
		$this->img  = '';
		$this->auto = $auto; // Auto enable for dev purposes.
	}

	/**
	 * Don't lint me.
	 *
	 * @param string $code The currency code.
	 */
	public function get_currency_name_from_code( $code ) {
		$wc_currencies = get_woocommerce_currencies();
		return $wc_currencies[ $code ];
	}
}
