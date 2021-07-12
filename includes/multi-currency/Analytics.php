<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that contains multi-currency related support for WooCommerce analytics.
 */
class Analytics {

	/**
	 * A list of all the pages in the WC Admin analytics section that
	 * we want to add multi-currency filters to.
	 *
	 * @var string[]
	 */
	const ANALYTICS_PAGES = [
		'orders',
		'revenue',
		'products',
		'categories',
		'coupons',
		'taxes',
	];

	/**
	 * Instance of MultiCurrency.
	 *
	 * @var MultiCurrency $multi_currency
	 */
	private $multi_currency;

	/**
	 * Constructor
	 *
	 * @param MultiCurrency $multi_currency Instance of MultiCurrency.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		// If we aren't in the context of WC Admin, just return false.
		if ( ! is_admin() ) {
			return;
		}

		$this->multi_currency = $multi_currency;

		$this->init();
	}

	/**
	 * Initialise all actions, filters and hooks related to analytics support.
	 *
	 * @return void
	 */
	public function init() {
		// TODO. See The analytics controller in the multi currency plugin.
	}
}
