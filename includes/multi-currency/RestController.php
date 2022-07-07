<?php
/**
 * Class WC_REST_Controller
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for multi-currency.
 */
class RestController extends \WC_Payments_REST_Controller {

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/currencies/(?P<currency_code>[A-Za-z]{3})',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_currency_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/currencies/(?P<currency_code>[A-Za-z]{3})',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_currency_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update-settings',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve currencies for the store.
	 *
	 * @return array The store currencies structure.
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
	 * @param \WP_REST_Request $request Full data about the request.
	 *
	 * @return array The store currencies structure.
	 */
	public function update_enabled_currencies( $request ) {
		$params = $request->get_params();
		WC_Payments_Multi_Currency()->set_enabled_currencies( $params['enabled'] );
		return $this->get_store_currencies();
	}

	/**
	 * Gets the currency settings for a single currency.
	 *
	 * @param   \WP_REST_Request $request  Full data about the request.
	 *
	 * @return  array            The currency settings.
	 */
	public function get_currency_settings( $request ) {
		$currency_code = sanitize_key( strtolower( $request['currency_code'] ) );
		return [
			'exchange_rate_type' => get_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, 'automatic' ),
			'manual_rate'        => get_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, null ),
			'price_rounding'     => get_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, null ),
			'price_charm'        => get_option( 'wcpay_multi_currency_price_charm_' . $currency_code, null ),
		];
	}

	/**
	 * Updates the currency settings for a single currency.
	 *
	 * @param   \WP_REST_Request $request  Full data about the request.
	 *
	 * @return  array            The currency settings.
	 */
	public function update_currency_settings( $request ) {
		$currency_code        = sanitize_key( strtolower( $request['currency_code'] ) );
		$params               = $request->get_params();
		$available_currencies = WC_Payments_Multi_Currency()->get_available_currencies();

		if ( array_key_exists( strtoupper( $currency_code ), $available_currencies ) ) {
			if ( isset( $params['exchange_rate_type'] ) && in_array( $params['exchange_rate_type'], [ 'automatic', 'manual' ], true ) ) {
				update_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, esc_attr( $params['exchange_rate_type'] ) );
			}
			if ( 'manual' === $params['exchange_rate_type'] && isset( $params['manual_rate'] ) ) {
				update_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, (float) $params['manual_rate'] );
			}
			if ( isset( $params['price_rounding'] ) ) {
				update_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, (float) $params['price_rounding'] );
			}
			if ( isset( $params['price_charm'] ) ) {
				update_option( 'wcpay_multi_currency_price_charm_' . $currency_code, (float) $params['price_charm'] );
			}
		}

		return $this->get_currency_settings( $request );
	}

	/**
	 * Gets the store settings for Multi-Currency.
	 *
	 * @return  array  The store settings.
	 */
	public function get_settings() {
		return WC_Payments_Multi_Currency()->get_settings();
	}

	/**
	 * Updates Multi-Currency store settings parameters.
	 *
	 * @param   \WP_REST_Request $request  Full data about the request.
	 *
	 * @return array The store settings.
	 */
	public function update_settings( $request ) {
		$params = $request->get_params();
		WC_Payments_Multi_Currency()->update_settings( $params );
		return WC_Payments_Multi_Currency()->get_settings();
	}
}
