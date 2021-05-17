<?php
/**
 * Class WC_REST_Payments_Settings_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Payment_Methods\Digital_Wallets_Payment_Gateway;
use WCPay\Constants\Digital_Wallets_Sections;

/**
 * REST controller for settings.
 */
class WC_REST_Payments_Settings_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/settings';

	/**
	 * Instance of WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * WC_REST_Payments_Settings_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client WC_Payments_API_Client instance.
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WC_Payment_Gateway_WCPay instance.
	 * @param Digital_Wallets_Payment_Gateway $digital_wallets_gateway Digital_Wallets_Payment_Gateway instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $wcpay_gateway, Digital_Wallets_Payment_Gateway $digital_wallets_gateway ) {
		parent::__construct( $api_client );

		$this->wcpay_gateway = $wcpay_gateway;
		$this->digital_wallets_gateway = $digital_wallets_gateway;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_wcpay_enabled'           => [
						'description'       => __( 'If WooCommerce Payments should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'enabled_payment_method_ids' => [
						'description'       => __( 'Payment method IDs that should be enabled. Other methods will be disabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => $this->wcpay_gateway->get_upe_available_payment_methods(),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'enabled_payment_method_ids'   => $this->wcpay_gateway->get_upe_enabled_payment_method_ids(),
				'available_payment_method_ids' => $this->wcpay_gateway->get_upe_available_payment_methods(),
				'is_wcpay_enabled'             => $this->wcpay_gateway->is_enabled(),
				'is_digital_wallets_enabled'       => $this->digital_wallets_gateway->is_enabled(),
				'digital_wallets_enabled_sections' => $this->digital_wallets_gateway->get_option( 'digital_wallets_enabled_sections' ),
			]
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$this->update_is_wcpay_enabled( $request );
		$this->update_enabled_payment_methods( $request );
		$this->update_is_digital_wallets_enabled( $request );
		$this->update_digital_wallets_enabled_sections( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Updates WooCommerce Payments enabled status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_wcpay_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_wcpay_enabled' ) ) {
			return;
		}

		$is_wcpay_enabled = $request->get_param( 'is_wcpay_enabled' );

		if ( $is_wcpay_enabled ) {
			$this->wcpay_gateway->enable();
		} else {
			$this->wcpay_gateway->disable();
		}
	}

	/**
	 * Updates the list of enabled payment methods.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_enabled_payment_methods( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'enabled_payment_method_ids' ) ) {
			return;
		}

		$payment_method_ids_to_enable = $request->get_param( 'enabled_payment_method_ids' );
		$available_payment_methods    = $this->wcpay_gateway->get_upe_available_payment_methods();

		$payment_method_ids_to_enable = array_values(
			array_filter(
				$payment_method_ids_to_enable,
				function ( $payment_method ) use ( $available_payment_methods ) {
					return in_array( $payment_method, $available_payment_methods, true );
				}
			)
		);

		$this->wcpay_gateway->update_option( 'enabled_payment_method_ids', $payment_method_ids_to_enable );
	}

	/**
	 * Updates the digital wallets enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_digital_wallets_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_digital_wallets_enabled' ) ) {
			return;
		}

		$is_digital_wallets_enabled = $request->get_param( 'is_digital_wallets_enabled' );

		if ( $is_digital_wallets_enabled ) {
			$this->digital_wallets_gateway->enable();
		} else {
			$this->digital_wallets_gateway->disable();
		}
	}

	/**
	 * Updates the list of sections that will show digital wallets.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_digital_wallets_enabled_sections( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'digital_wallets_enabled_sections' ) ) {
			return;
		}

		$digital_wallets_enabled_sections         = $request->get_param( 'digital_wallets_enabled_sections' );
		$updated_digital_wallets_enabled_sections = [];

		$remove_invalid_sections                  = function ( $section ) {
			return ! Digital_Wallets_Sections::isValidKey( $section );
		};
		$updated_digital_wallets_enabled_sections = array_filter( $digital_wallets_enabled_sections, $remove_invalid_sections );

		$this->digital_wallets_gateway->update_option( 'digital_wallets_enabled_sections', $updated_digital_wallets_enabled_sections );
	}
}
