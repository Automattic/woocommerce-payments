<?php
/**
 * Class WC_REST_Payments_Settings_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

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
	 * Payment gateways.
	 *
	 * @var WC_Payment_Gateways
	 */
	private $payment_gateways;

	/**
	 * WC_REST_Payments_Settings_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client WC_Payments_API_Client instance.
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WC_Payment_Gateway_WCPay instance.
	 * @param WC_Payment_Gateways      $payment_gateways WC_Payment_Gateways instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payment_Gateways $payment_gateways ) {
		parent::__construct( $api_client );
		$this->wcpay_gateway    = $wcpay_gateway;
		$this->payment_gateways = $payment_gateways;
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
						'description'       => __( 'Payment method IDs that should be enabled, in the order they should appear in during checkout. Other methods will be disabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => wp_list_pluck( $this->get_available_wcpay_payment_methods(), 'id' ),
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
		$enabled_payment_methods = array_filter(
			$this->get_available_wcpay_payment_methods(),
			function ( WC_Payment_Gateway_WCPay $gateway ) {
				return $gateway->is_enabled();
			}
		);

		$enabled_payment_method_ids = wp_list_pluck( $enabled_payment_methods, 'id' );

		return new WP_REST_Response(
			[
				'enabled_payment_method_ids' => array_values( $enabled_payment_method_ids ),
				'is_wcpay_enabled'           => $this->wcpay_gateway->is_enabled(),
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

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Get available payment methods.
	 *
	 * @return WC_Payment_Gateway_WCPay[]
	 */
	private function get_available_wcpay_payment_methods(): array {
		return array_filter(
			$this->payment_gateways->payment_gateways(),
			function ( $gateway ) {
				return is_subclass_of( $gateway, WC_Payment_Gateway_WCPay::class );
			}
		);
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

		foreach ( $this->get_available_wcpay_payment_methods() as $payment_method ) {
			$should_be_enabled = in_array( $payment_method->id, $payment_method_ids_to_enable, true );

			if ( $should_be_enabled ) {
				$payment_method->enable();
			} else {
				$payment_method->disable();
			}
		}

		$this->wcpay_gateway->update_option( 'payment_method_order', $payment_method_ids_to_enable );
	}

}
