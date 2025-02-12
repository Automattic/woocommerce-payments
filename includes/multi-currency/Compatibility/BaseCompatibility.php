<?php
/**
 * Class BaseCompatibility
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that sets up base options for compatibility classes.
 */
abstract class BaseCompatibility {

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
	 * @param Utils         $utils          Utils class.
	 */
	public function __construct( MultiCurrency $multi_currency, Utils $utils ) {
		$this->multi_currency = $multi_currency;
		$this->utils          = $utils;
		$this->init();
	}

	/**
	 * Init the class.
	 *
	 * @return void
	 */
	abstract public function init();
}
