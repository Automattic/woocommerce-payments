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
			'/' . $this->rest_base . '/available',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_available_currencies' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/default',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_default_currency' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/enabled',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_enabled_currencies' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve available currencies for the store.
	 */
	public function get_available_currencies() {
		return WC_Payments_Multi_Currency()->get_available_currencies();
	}

	/**
	 * Retrieve available currencies for the store.
	 */
	public function get_default_currency() {
		return WC_Payments_Multi_Currency()->get_default_currency();
	}

	/**
	 * Retrieve enabled currencies for the store.
	 */
	public function get_enabled_currencies() {
		return WC_Payments_Multi_Currency()->get_enabled_currencies();
	}
}
