<?php
/**
 * Get Disputes ability definition.
 *
 * @package WooCommerce\Payments
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-disputes` ability.
 *
 * Paginated list of disputes. Adopts the WC 10.9 paginated output
 * envelope: `{ disputes: [...], total_pages, page, per_page }`.
 *
 * WIRE-FORMAT BREAK vs the pre-migration ability: previously returned the
 * controller's raw response. Now wraps that in the canonical envelope.
 * Acceptable because the `woocommerce_payments_abilities_enabled` filter
 * is default false and there are no production consumers.
 *
 * Input shape unchanged (page + per_page + dispute filters); the registrar's
 * `translate_pagination_keys()` helper still maps these to the WCPay
 * `Paginated` request class's `pagesize` at the delegate boundary.
 *
 * @internal Only loaded when WooCommerce Core 10.9+ is active.
 */
class GetDisputes extends AbstractWCPayAbility implements AbilityDefinition {

	private const DEFAULT_PER_PAGE = 25;
	private const MAX_PER_PAGE     = 100;

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-disputes';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		// Filter properties lifted verbatim from AbilitiesRegistrar::disputes_list_input_schema()
		// MINUS page/per_page — those come from get_pagination_input_properties() in the base class.
		$filter_properties = [
			'match'             => [ 'type' => 'string' ],
			'store_currency_is' => [ 'type' => 'string' ],
			'date_before'       => [ 'type' => 'string' ],
			'date_after'        => [ 'type' => 'string' ],
			'date_between'      => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'search'            => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'status_is'         => [
				'type'        => 'string',
				'description' => 'Dispute status (warning_needs_response|needs_response|under_review|won|lost|warning_under_review|warning_closed).',
			],
			'status_is_not'     => [ 'type' => 'string' ],
			'orderby'           => [ 'type' => 'string' ],
			'order'             => [
				'type' => 'string',
				'enum' => [ 'asc', 'desc' ],
			],
		];

		return [
			'label'               => __( 'List disputes', 'woocommerce-payments' ),
			'description'         => __( 'List disputes with status and date-range filters. Answers \'which disputes need response?\'.', 'woocommerce-payments' ),
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
				'disputes',
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
	 * Execute the get-disputes ability.
	 *
	 * Wraps the controller response in the WC 10.9 paginated envelope.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input    = is_array( $input ) ? $input : [];
		$page     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : self::DEFAULT_PER_PAGE;

		$response = AbilitiesRegistrar::delegate_to_rest_controller(
			'GET',
			'/wc/v3/payments/disputes',
			$input
		);

		return self::wrap_paginated_response( $response, 'disputes', $page, $per_page );
	}
}
