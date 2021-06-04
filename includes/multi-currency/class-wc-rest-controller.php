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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update-enabled-currencies',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_enabled_currencies' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve currencies for the store.
	 */
	public function get_store_currencies() {
		return [
			'available' => WC_Payments_Multi_Currency()->get_available_currencies(),
			'enabled'   => WC_Payments_Multi_Currency()->get_enabled_currencies(),
			'default'   => WC_Payments_Multi_Currency()->get_default_currency(),
		];
	}

	/**
	 * Update enabled currencies based on posted data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_enabled_currencies( $request ) {
		$params = $request->get_params();
		WC_Payments_Multi_Currency()->set_enabled_currencies( $params['enabled'] );
		return $this->get_store_currencies();
	}
}
