<?php
/**
 * Class WC_REST_UPE_Flag_Toggle_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for UPE feature flag.
 */
class WC_REST_UPE_Flag_Toggle_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'payments/upe_flag_toggle';

	/**
	 * Instance of WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * WC_REST_UPE_Flag_Toggle_Controller constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WC_Payment_Gateway_WCPay instance.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway ) {
		$this->wcpay_gateway = $wcpay_gateway;
	}

	/**
	 * Verify access to request.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
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
				'callback'            => [ $this, 'get_flag' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_flag' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_upe_enabled' => [
						'description'       => __( 'Determines if the UPE feature flag is enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve flag status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_flag(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'is_upe_enabled' => WC_Payments_Features::is_upe_enabled(),
			]
		);
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function set_flag( WP_REST_Request $request ) {
		$this->update_is_upe_enabled( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Update UPE feature flag enabled status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_upe_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_upe_enabled' ) ) {
			return;
		}

		$is_upe_enabled = $request->get_param( 'is_upe_enabled' );

		if ( $is_upe_enabled ) {
			update_option( WC_Payments_Features::UPE_FLAG_NAME, '1' );
			return;
		}

		// marking the flag as "disabled", so that we can keep track that the merchant explicitly disabled it.
		update_option( WC_Payments_Features::UPE_FLAG_NAME, 'disabled' );

		// resetting a few other things:
		// removing the UPE payment methods and _only_ leaving "card",
		// just in case the user added additional payment method types.
		$this->wcpay_gateway->update_option(
			'upe_enabled_payment_method_ids',
			[
				'card',
			]
		);

		// removing the note from the database.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-additional-payment-methods.php';
			WC_Payments_Notes_Additional_Payment_Methods::possibly_delete_note();
		}
	}
}
