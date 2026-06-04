<?php
/**
 * Get Fraud Outcomes ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-fraud-outcomes` ability.
 *
 * Lists transactions flagged by fraud protection for a given outcome status
 * (e.g. block, review). Paginated.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WC_REST_Payments_Transactions_Controller::get_fraud_outcome_transactions()
 */
class GetFraudOutcomes extends AbstractWCPayAbility implements AbilityDefinition {

	private const DEFAULT_PER_PAGE = 25;
	private const MAX_PER_PAGE     = 100;

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-fraud-outcomes';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		$filter_properties = [
			'status'      => [
				'type'        => 'string',
				'description' => __( 'Fraud outcome status to filter by (e.g. block, review).', 'woocommerce-payments' ),
			],
			'search'      => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'search_term' => [ 'type' => 'string' ],
			'orderby'     => [ 'type' => 'string' ],
			'order'       => [
				'type' => 'string',
				'enum' => [ 'asc', 'desc' ],
			],
		];

		return [
			'label'               => __( 'List flagged (fraud outcome) transactions', 'woocommerce-payments' ),
			'description'         => __( 'List transactions flagged by fraud protection for a given outcome status (e.g. block, review).', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'required'             => [ 'status' ],
				'properties'           => array_merge(
					self::get_pagination_input_properties( self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE ),
					$filter_properties
				),
				'additionalProperties' => false,
			],
			'output_schema'       => self::get_collection_output_schema(
				'fraud_outcomes',
				[ 'type' => 'object' ]
			),
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
				],
			],
		];
	}

	/**
	 * Execute the get-fraud-outcomes ability.
	 *
	 * @see \WC_REST_Payments_Transactions_Controller::get_fraud_outcome_transactions()
	 *
	 * @param array<string, mixed> $input Required `status`; optional pagination/search.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input = is_array( $input ) ? $input : [];

		if ( ! isset( $input['status'] ) || ! is_string( $input['status'] ) || '' === $input['status'] ) {
			return new \WP_Error(
				'wcpay_missing_status',
				__( 'A status is required to list fraud outcomes.', 'woocommerce-payments' )
			);
		}

		$input['page']     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$input['per_page'] = isset( $input['per_page'] ) ? (int) $input['per_page'] : self::DEFAULT_PER_PAGE;

		$response = AbilitiesRegistrar::delegate_to_rest_controller(
			'GET',
			'/wc/v3/payments/transactions/fraud-outcomes',
			$input
		);

		return self::wrap_paginated_response( $response, 'fraud_outcomes', $input['page'], $input['per_page'] );
	}
}
