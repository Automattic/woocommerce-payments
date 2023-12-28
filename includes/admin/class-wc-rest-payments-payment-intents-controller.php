<?php
/**
 * Class WC_REST_Payments_Payment_Intents_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Logger;
use WCPay\Exceptions\Rest_Request_Exception;
use WCPay\Constants\Payment_Type;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for charges.
 */
class WC_REST_Payments_Payment_Intents_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/payment_intents';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<payment_intent_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment_intent' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve charge to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_payment_intent( $request ) {
		$payment_intent_id = $request->get_param( 'payment_intent_id' );

		return $this->forward_request( 'get_intent', [ $payment_intent_id ] );
	}
}
