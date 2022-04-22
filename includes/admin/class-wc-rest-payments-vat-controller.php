<?php
/**
 * Class WC_REST_Payments_VAT_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for vat.
 */
class WC_REST_Payments_VAT_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/vat';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<vat_number>[\w\.\%]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'validate_vat' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'args'                => [
					'vat_number' => [
						'type'     => 'string',
						'required' => false,
					],
					'name'       => [
						'type'     => 'string',
						'required' => true,
					],
					'address'    => [
						'type'     => 'string',
						'required' => true,
					],
				],
				'callback'            => [ $this, 'save_vat_details' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Validate VAT number to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function validate_vat( $request ) {
		$vat_number = $request->get_param( 'vat_number' );
		return $this->forward_request( 'validate_vat', [ $vat_number ] );
	}

	/**
	 * Save VAT details and respond via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function save_vat_details( $request ) {
		$vat_number = $request->get_param( 'vat_number' );
		$name       = $request->get_param( 'name' );
		$address    = $request->get_param( 'address' );
		return $this->forward_request( 'save_vat_details', [ $vat_number, $name, $address ] );
	}
}
