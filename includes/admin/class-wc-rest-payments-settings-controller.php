<?php
/**
 * Class WC_REST_Payments_Settings_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for disputes.
 */
class WC_REST_Payments_Settings_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/settings';

	/**
	 * Instance of WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * WC_REST_Payments_Settings_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client WC_Payments_API_Client instance.
	 * @param WC_Payment_Gateway_WCPay $gateway WC_Payment_Gateway_WCPay instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $gateway ) {
		parent::__construct( $api_client );
		$this->gateway = $gateway;
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
			]
		);
	}

	/**
	 * Retrieve settings.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$enabled_payment_methods = array_filter(
			$this->get_available_payment_methods(),
			function ( WC_Payment_Gateway_WCPay $gateway ) {
				return $gateway->is_enabled();
			}
		);

		$enabled_payment_method_ids = wp_list_pluck( $enabled_payment_methods, 'id' );

		return [
			'enabled_payment_method_ids' => array_values( $enabled_payment_method_ids ),
			'is_wcpay_enabled'           => $this->gateway->is_enabled(),
		];
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$this->update_is_wcpay_enabled( $request );
		$this->update_enabled_payment_methods( $request );

		return new WP_HTTP_Response( [], 200 );
	}

	/**
	 * Get available payment methods.
	 *
	 * @return WC_Payment_Gateway_WCPay[]
	 */
	private function get_available_payment_methods(): array {
		return array_filter(
			WC()->payment_gateways()->payment_gateways(),
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
			$this->gateway->enable();
		} else {
			$this->gateway->disable();
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

		$available_payment_methods    = $this->get_available_payment_methods();
		$payment_method_ids_to_enable = array_intersect(
			$request->get_param( 'enabled_payment_method_ids' ),
			wp_list_pluck( $this->get_available_payment_methods(), 'id' )
		);

		foreach ( $available_payment_methods as $payment_method ) {
			$is_enabled        = $payment_method->is_enabled();
			$should_be_enabled = in_array( $payment_method->id, $payment_method_ids_to_enable, true );

			if ( $should_be_enabled && ! $is_enabled ) {
				$payment_method->enable();
			} elseif ( ! $should_be_enabled && $is_enabled ) {
				$payment_method->disable();
			}
		}

		$this->gateway->update_option( 'payment_method_order', $payment_method_ids_to_enable );
	}

}
