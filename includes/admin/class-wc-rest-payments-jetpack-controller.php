<?php
/**
 * Class WC_REST_Payments_Jetpack_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for managing the Jetpack connection flow.
 */
class WC_REST_Payments_Jetpack_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/jetpack';

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_REST_Payments_Jetpack_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client - WooCommerce Payments API client.
	 * @param WC_Payments_Account      $account    - Account instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_Account $account ) {
		parent::__construct( $api_client );
		$this->account = $account;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/register-site',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'register_site' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check-stripe-connection',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_stripe_connection' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function register_site() {
		$manager = new Automattic\Jetpack\Connection\Manager();
		$result = $manager->register();
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( $result );
		}
		return rest_ensure_response( [
			'connectUrl' => $manager->get_authorization_url(),
		] );
	}

	public function check_stripe_connection() {
		return rest_ensure_response( [
			'isConnected' => $this->account->is_stripe_connected(),
			'redirectUrl' => add_query_arg(
				array( 'wcpay-connection-success' => '1' ),
				WC_Payment_Gateway_WCPay::get_settings_url()
			),
		] );
	}
}
