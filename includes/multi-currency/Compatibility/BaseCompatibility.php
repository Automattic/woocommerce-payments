<?php
/**
 * Class BaseCompatibility
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;

/**
 * Class that sets up base options for compatibility classes.
 */
class BaseCompatibility {

	const FILTER_PREFIX = 'wcpay_multi_currency_';

	/**
	 * FrontendCurrencies class.
	 *
	 * @var FrontendCurrencies
	 */
	protected $frontend_currencies;

	/**
	 * MultiCurrency class.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * Utils class.
	 *
	 * @var Utils
	 */
	protected $utils;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency MultiCurrency class.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency      = $multi_currency;
		$this->frontend_currencies = $multi_currency->get_frontend_currencies();
		$this->utils               = $this->get_utils();
	}

	/**
	 * Returns the Utils class.
	 *
	 * @return Utils
	 */
	public function get_utils() {
		if ( $this->utils ) {
			return $this->utils;
		}
		$this->utils = $this->multi_currency->get_utils();
		return $this->utils;
	}
}
