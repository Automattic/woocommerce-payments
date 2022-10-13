<?php
/**
 * Class WC_REST_Platform_Checkout_Signature_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Platform_Checkout\Platform_Checkout_Utilities;

/**
 * REST controller to check if a user exists.
 */
class WC_REST_Platform_Checkout_Signature_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'platform-checkout/signature';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_platform_checkout_signature' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Retrieve a platform checkout request signature.
	 *
	 * @return WP_REST_Response
	 */
	public function get_platform_checkout_signature(): WP_REST_Response {
		$platform_checkout_util = new Platform_Checkout_Utilities();

		$signature = $platform_checkout_util->get_platform_checkout_request_signature();

		return new WP_REST_Response(
			[
				'signature' => $signature,
			]
		);
	}
}
