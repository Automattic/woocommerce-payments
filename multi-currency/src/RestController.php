<?php
/**
 * Class RestController
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

use Exception;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyException;
use WCPay\MultiCurrency\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for multi-currency.
 */
class RestController extends \WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/multi-currency';

	/**
	 * MultiCurrency instance.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency MultiCurrency instance.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;
	}


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
				'args'                => [
					'enabled' => [
						'type'     => 'array',
						'required' => true,
					],
				],
				'callback'            => [ $this, 'update_enabled_currencies' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/currencies/(?P<currency_code>[A-Za-z]{3})',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'args'                => [
					'currency_code' => [
						'type'     => 'string',
						'format'   => 'text-field',
						'required' => true,
					],
				],
				'callback'            => [ $this, 'get_single_currency_settings' ],
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
				'args'                => [
					'currency_code'      => [
						'type'     => 'string',
						'format'   => 'text-field',
						'required' => true,
					],
					'exchange_rate_type' => [
						'type'     => 'string',
						'format'   => 'text-field',
						'required' => true,
					],
					'manual_rate'        => [
						'type'     => 'number',
						'required' => false,
					],
					'price_rounding'     => [
						'type'     => 'number',
						'required' => true,
					],
					'price_charm'        => [
						'type'     => 'number',
						'required' => true,
					],
				],
				'callback'            => [ $this, 'update_single_currency_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update-settings',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'args'                => [
					'wcpay_multi_currency_enable_auto_currency'       => [
						'type'     => 'string',
						'format'   => 'text-field',
						'required' => true,
					],
					'wcpay_multi_currency_enable_storefront_switcher' => [
						'type'     => 'string',
						'format'   => 'text-field',
						'required' => true,
					],
				],
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve currencies for the store.
	 *
	 * @return \WP_REST_Response|\WP_Error Array of the store currencies structure.
	 */
	public function get_store_currencies() {
		return rest_ensure_response( $this->multi_currency->get_store_currencies() );
	}

	/**
	 * Update enabled currencies based on posted data.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error The store currencies structure or WP_Error.
	 */
	public function update_enabled_currencies( $request ) {
		$enabled = $request->get_param( 'enabled' );
		try {
			$this->multi_currency->set_enabled_currencies( $enabled );
			$response = $this->get_store_currencies();
		} catch ( InvalidCurrencyException $e ) {
			$response = new \WP_Error( $e->getCode(), $e->getMessage() );
		}
		return rest_ensure_response( $response );
	}

	/**
	 * Gets the currency settings for a single currency.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error The single currency settings as an array.
	 */
	public function get_single_currency_settings( $request ) {
		$currency_code = $request->get_param( 'currency_code' );

		try {
			$response = $this->multi_currency->get_single_currency_settings( $currency_code );
		} catch ( InvalidCurrencyException $e ) {
			$response = new \WP_Error( $e->getCode(), $e->getMessage() );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Updates the currency settings for a single currency.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error The single currency settings as an array.
	 */
	public function update_single_currency_settings( $request ) {
		$currency_code      = $request->get_param( 'currency_code' );
		$exchange_rate_type = $request->get_param( 'exchange_rate_type' );
		$price_rounding     = $request->get_param( 'price_rounding' );
		$price_charm        = $request->get_param( 'price_charm' );
		$manual_rate        = $request->get_param( 'manual_rate' ) ?? null;

		try {
			$this->multi_currency->update_single_currency_settings( $currency_code, $exchange_rate_type, $price_rounding, $price_charm, $manual_rate );
			$response = $this->multi_currency->get_single_currency_settings( $currency_code );
		} catch ( Exception $e ) {
			$response = new \WP_Error( $e->getCode(), $e->getMessage() );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Gets the store settings for Multi-Currency.
	 *
	 * @return \WP_REST_Response|\WP_Error The store settings as an array.
	 */
	public function get_settings() {
		return rest_ensure_response( $this->multi_currency->get_settings() );
	}

	/**
	 * Updates Multi-Currency store settings parameters.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error The store settings as an array.
	 */
	public function update_settings( $request ) {
		$params = $request->get_params();
		$this->multi_currency->update_settings( $params );
		return rest_ensure_response( $this->multi_currency->get_settings() );
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
