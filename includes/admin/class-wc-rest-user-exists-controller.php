<?php
/**
 * Class WC_REST_User_Exists_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller to check if a user exists.
 */
class WC_REST_User_Exists_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'users/exists';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'user_exists' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email' => [
						'required'    => true,
						'description' => __( 'Email address.', 'woocommerce-payments' ),
						'type'        => 'string',
						'format'      => 'email',
					],
				],
			]
		);
	}

	/**
	 * Retrieve if a user exists by email address.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function user_exists( WP_REST_Request $request ): WP_REST_Response {
		$email        = $request->get_param( 'email' );
		$email_exists = ! empty( email_exists( $email ) );
		$message      = null;

		if ( $email_exists ) {
			// Use this function to show the core error message.
			$error   = wc_create_new_customer( $email );
			$message = $error->get_error_message();
		}

		return new WP_REST_Response(
			[
				'user-exists' => $email_exists,
				'message'     => $message,
			]
		);
	}
}
