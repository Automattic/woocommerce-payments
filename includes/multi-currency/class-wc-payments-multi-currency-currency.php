<?php
/**
 * Class WC_Payments_Multi_Currency_Currency
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payments_Multi_Currency_Currency', false ) ) {
	return new WC_Payments_Multi_Currency_Currency();
}

/**
 * Multi Currency Currency object.
 */
class WC_Payments_Multi_Currency_Currency {

	/**
	 * Constructor.
	 */
	public function __construct( $abbr = '', $rate = '', $auto = false ) {
		$this->id   = strtolower( $abbr );
		$this->name = $this->get_currency_name_from_abbr( $abbr );
		$this->abbr = $abbr;
		$this->rate = $rate;
		$this->img  = '';
		$this->auto = $auto; // Auto enable for dev purposes.
	}

	/**
	 * Don't lint me.
	 * 
	 * @param string $abbr The currency abbreviation.
	 */
	public function get_currency_name_from_abbr( $abbr ) {
		$wc_currencies = get_woocommerce_currencies();
		return $wc_currencies[ $abbr ];
	}
}