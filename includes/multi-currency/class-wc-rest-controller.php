<?php
/**
 * Class WC_REST_Controller
 *
 * @package WooCommerce\Payments\Multi_Currency
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for multi-currency.
 */
class WC_REST_Controller extends \WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/multi-currency';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/currencies',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_store_currencies' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve currencies for the store.
	 */
	public function get_store_currencies() {
		$default    = WC_Payments_Multi_Currency()->get_default_currency();
		$currencies = [
			'available' => WC_Payments_Multi_Currency()->get_available_currencies(),
			'enabled'   => WC_Payments_Multi_Currency()->get_enabled_currencies(),
			'default'   => [ $default->get_code() => $default ],
		];

		foreach ( $currencies as $grouping => $group ) {
			foreach ( $group as $code => $currency ) {
				$currencies[ $grouping ][ $code ] = [
					'code'   => $code,
					'rate'   => $currency->get_rate(),
					'name'   => $currency->get_name(),
					'id'     => $currency->get_id(),
					'flag'   => $currency->get_flag(),
					'symbol' => $currency->get_symbol(),
				];
			}
		}

		return $currencies;
	}
}
