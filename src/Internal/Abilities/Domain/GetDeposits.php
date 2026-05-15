<?php
/**
 * Get Deposits ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-deposits` ability.
 *
 * Paginated list of payouts (Stripe deposits). Returns the WC 10.9 paginated
 * envelope: `{ deposits: [...], total_pages, page, per_page }`. `page` /
 * `per_page` echo what the ability sent to the controller (the caller's
 * input, or this class's defaults).
 *
 * @see \WC_REST_Payments_Deposits_Controller::get_deposits()
 */
class GetDeposits extends AbstractWCPayAbility implements AbilityDefinition {

	private const DEFAULT_PER_PAGE = 25;
	private const MAX_PER_PAGE     = 100;

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-deposits';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		$filter_properties = [
			'match'             => [ 'type' => 'string' ],
			'store_currency_is' => [ 'type' => 'string' ],
			'date_before'       => [ 'type' => 'string' ],
			'date_after'        => [ 'type' => 'string' ],
			'date_between'      => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'status_is'         => [
				'type'        => 'string',
				'description' => 'Payout status (paid|pending|in_transit|canceled|failed).',
			],
			'status_is_not'     => [ 'type' => 'string' ],
			'orderby'           => [ 'type' => 'string' ],
			'order'             => [
				'type' => 'string',
				'enum' => [ 'asc', 'desc' ],
			],
		];

		return [
			'label'               => __( 'List payouts', 'woocommerce-payments' ),
			'description'         => __( 'List payouts (Stripe deposits) with status, date-range, and currency filters. Answers \'show me my recent payouts\'.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => array_merge(
					self::get_pagination_input_properties( self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE ),
					$filter_properties
				),
				'additionalProperties' => false,
			],
			'output_schema'       => self::get_collection_output_schema(
				'deposits',
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
	 * Execute the get-deposits ability.
	 *
	 * @see \WC_REST_Payments_Deposits_Controller::get_deposits()
	 *
	 * @param array<string, mixed> $input Pagination + filter parameters. See
	 *                                    `get_registration_args()` for shape.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input             = is_array( $input ) ? $input : [];
		$input['page']     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$input['per_page'] = isset( $input['per_page'] ) ? (int) $input['per_page'] : self::DEFAULT_PER_PAGE;

		$response = AbilitiesRegistrar::delegate_to_rest_controller(
			'GET',
			'/wc/v3/payments/deposits',
			$input
		);

		return self::wrap_paginated_response( $response, 'deposits', $input['page'], $input['per_page'] );
	}
}
