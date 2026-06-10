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
				'schema' => [ $this, 'get_item_schema' ],
			]
		);
	}

	/**
	 * Retrieves the Balance report summary.
	 *
	 * @see \WCPay\Internal\Abilities\Domain\GetBalance
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

	/**
	 * Returns the response schema for the Balance summary endpoint.
	 *
	 * Mirrors the Get_Reporting_Balance_Summary response shape so REST schema
	 * discovery (and any future schema-driven UI) sees an accurate contract.
	 * Each row matches the ReportsBalanceSummaryRow client type at
	 * client/data/reports/hooks.ts:73-77.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$row_schema = [
			'type'       => 'object',
			'properties' => [
				'amount' => [
					'type' => 'number',
				],
				'count'  => [
					'type' => 'integer',
				],
			],
		];

		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'payments_reports_balance_summary',
			'type'       => 'object',
			'properties' => [
				'currency'                         => [ 'type' => 'string' ],
				'period'                           => [
					'type'       => 'object',
					'properties' => [
						'start' => [
							'type'   => 'string',
							'format' => 'date-time',
						],
						'end'   => [
							'type'   => 'string',
							'format' => 'date-time',
						],
					],
				],
				'starting_balance'                 => $row_schema,
				'total_charges_captured'           => $row_schema,
				'fees'                             => $row_schema,
				'charge_fees'                      => $row_schema,
				'payout_fees'                      => $row_schema,
				'reader_fees'                      => $row_schema,
				'dispute_fees'                     => $row_schema,
				'fee_refunds'                      => $row_schema,
				'refunds'                          => $row_schema,
				'refund_failure'                   => $row_schema,
				'disputes'                         => $row_schema,
				'financing_payout'                 => $row_schema,
				'financing_paydown'                => $row_schema,
				'network_costs'                    => $row_schema,
				'other_adjustments'                => $row_schema,
				'net_balance_change_in_the_period' => $row_schema,
				'payouts'                          => $row_schema,
				'ending_balance'                   => $row_schema,
			],
		];
	}
}
