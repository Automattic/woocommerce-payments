<?php
/**
 * Get Authorizations ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-authorizations` ability.
 *
 * Paginated list of uncaptured card authorizations. Returns the WC 10.9
 * paginated envelope: `{ authorizations: [...], total_pages, page, per_page }`.
 * `page` / `per_page` echo what the ability sent to the controller (the
 * caller's input, or this class's defaults).
 *
 * @see \WC_REST_Payments_Authorizations_Controller::get_authorizations()
 */
class GetAuthorizations extends AbstractWCPayAbility implements AbilityDefinition {

	private const DEFAULT_PER_PAGE = 25;
	private const MAX_PER_PAGE     = 100;

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-authorizations';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		$filter_properties = [
			'orderby' => [ 'type' => 'string' ],
			'order'   => [
				'type' => 'string',
				'enum' => [ 'asc', 'desc' ],
			],
		];

		return [
			'label'               => __( 'List authorizations', 'woocommerce-payments' ),
			'description'         => __( 'List uncaptured card authorizations. Answers \'which authorizations expire soon / still need capture?\'.', 'woocommerce-payments' ),
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
				'authorizations',
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
	 * Execute the get-authorizations ability.
	 *
	 * @see \WC_REST_Payments_Authorizations_Controller::get_authorizations()
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
			'/wc/v3/payments/authorizations',
			$input
		);

		return self::wrap_paginated_response( $response, 'authorizations', $input['page'], $input['per_page'] );
	}
}
