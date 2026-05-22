<?php
/**
 * Class WC_REST_Payments_Reports_Balance_Controller
 *
 * @package WooCommerce\Payments\Reports
 */

use WCPay\Core\Server\Request\Get_Reporting_Balance_Summary;

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
		$wcpay_request = Get_Reporting_Balance_Summary::create();
		$wcpay_request->set_date_start( (string) $request->get_param( 'date_start' ) );
		$wcpay_request->set_date_end( (string) $request->get_param( 'date_end' ) );
		$wcpay_request->set_currency( (string) $request->get_param( 'currency' ) );

		$response = $wcpay_request->handle_rest_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $response );
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
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'date_end'   => [
				'description'       => __( 'Balance report period end date.', 'woocommerce-payments' ),
				'type'              => 'string',
				'format'            => 'date-time',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'currency'   => [
				'description'       => __( 'Balance report currency.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => static function ( $currency ) {
					return strtolower( sanitize_text_field( $currency ) );
				},
				'validate_callback' => static function ( $currency ) {
					if ( Get_Reporting_Balance_Summary::is_valid_currency_code( $currency ) ) {
						return true;
					}

					return new WP_Error(
						'rest_invalid_param',
						__( 'Currency must be an ISO 4217 three-letter code.', 'woocommerce-payments' )
					);
				},
			],
		];
	}
}
