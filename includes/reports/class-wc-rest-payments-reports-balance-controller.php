<?php
/**
 * Class WC_REST_Payments_Reports_Balance_Controller
 *
 * @package WooCommerce\Payments\Reports
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the Balance report.
 */
class WC_REST_Payments_Reports_Balance_Controller extends WC_Payments_REST_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/reports/balance';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		if ( ! WC_Payments_Features::is_reports_area_enabled() ) {
			return;
		}

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_balance_summary' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_collection_params(),
				],
			]
		);
	}

	/**
	 * Retrieves the Balance report summary.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_balance_summary( $request ) {
		return $this->forward_request(
			'get_reports_balance_summary',
			[
				$request->get_param( 'date_start' ),
				$request->get_param( 'date_end' ),
				$request->get_param( 'currency' ),
			]
		);
	}

	/**
	 * Collection args params.
	 *
	 * @return array[]
	 */
	public function get_collection_params() {
		return [
			'date_start' => [
				'description'       => __( 'Balance report period start date.', 'woocommerce-payments' ),
				'type'              => 'string',
				'format'            => 'date-time',
				'required'          => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'date_end'   => [
				'description'       => __( 'Balance report period end date.', 'woocommerce-payments' ),
				'type'              => 'string',
				'format'            => 'date-time',
				'required'          => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'currency'   => [
				'description'       => __( 'Balance report currency.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_currency_code' ],
			],
		];
	}

	/**
	 * Validate a lowercase ISO-4217 currency code.
	 *
	 * @param mixed $value Currency value.
	 *
	 * @return bool
	 */
	public static function validate_currency_code( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[a-z]{3}$/', $value );
	}
}
