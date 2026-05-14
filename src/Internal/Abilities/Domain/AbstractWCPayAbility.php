<?php
/**
 * Abstract base class for WooPayments ability definitions.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for WooPayments ability definitions.
 *
 * @internal Plugin-internal base for classes in this Domain namespace.
 */
abstract class AbstractWCPayAbility {

	/**
	 * Build a paginated collection-output schema.
	 *
	 * @param string $collection_key Property key naming the array of items
	 *                               (e.g. `transactions`, `disputes`, `authorizations`).
	 * @param array  $item_schema    JSON schema describing a single item in
	 *                               the collection.
	 * @return array
	 */
	protected static function get_collection_output_schema( string $collection_key, array $item_schema ): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				$collection_key => [
					'type'        => 'array',
					'description' => sprintf(
						/* translators: %s: Collection key, such as transactions or disputes. */
						__( 'Returned %s for the current page.', 'woocommerce-payments' ),
						$collection_key
					),
					'items'       => $item_schema,
				],
				'total_pages'   => [
					'type'        => 'integer',
					'description' => __( 'Total number of result pages available for the current query.', 'woocommerce-payments' ),
				],
				'page'          => [
					'type'        => 'integer',
					'description' => __( 'Current result page.', 'woocommerce-payments' ),
				],
				'per_page'      => [
					'type'        => 'integer',
					'description' => __( 'Maximum number of items requested per page.', 'woocommerce-payments' ),
				],
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * Build the standard pagination input properties for inclusion in an
	 * ability's `input_schema['properties']` array.
	 *
	 * @param int $default_per_page Default page size when caller omits `per_page`.
	 * @param int $max_per_page     Hard cap on page size.
	 * @return array
	 */
	protected static function get_pagination_input_properties( int $default_per_page = 25, int $max_per_page = 100 ): array {
		return [
			'page'     => [
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
				'description' => __( 'Page number to return (1-indexed).', 'woocommerce-payments' ),
			],
			'per_page' => [
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => $max_per_page,
				'default'     => $default_per_page,
				'description' => __( 'Maximum number of items per page.', 'woocommerce-payments' ),
			],
		];
	}

	/**
	 * Compute total_pages from a total count + per_page.
	 *
	 * @param int $total    Total result count.
	 * @param int $per_page Page size in effect.
	 * @return int
	 */
	protected static function compute_total_pages( int $total, int $per_page ): int {
		if ( $total <= 0 || $per_page <= 0 ) {
			return 0;
		}
		return (int) ceil( $total / $per_page );
	}

	/**
	 * Wrap a controller's raw list response in the WC 10.9 paginated envelope.
	 *
	 * The WCPay REST controllers return either:
	 *  (a) a flat list `[ item, item, ... ]` (older controllers), or
	 *  (b) `{ data: [ item, ... ], total_count: N }` (modern controllers
	 *      using the `Paginated` request class).
	 *
	 * This helper normalizes both shapes into:
	 *   `{ <collection_key>: [...], total_pages, page, per_page }`.
	 *
	 * If the response is a `WP_Error`, return it unchanged so callers can
	 * propagate the failure.
	 *
	 * @param mixed  $response       Controller response (array, list, or WP_Error).
	 * @param string $collection_key Key to use for the items array in the envelope.
	 * @param int    $page           Page number in effect for this request.
	 * @param int    $per_page       Page size in effect for this request.
	 * @return array|\WP_Error
	 */
	protected static function wrap_paginated_response( $response, string $collection_key, int $page, int $per_page ) {
		if ( $response instanceof \WP_Error ) {
			return $response;
		}

		if ( ! is_array( $response ) ) {
			return [
				$collection_key => [],
				'total_pages'   => 0,
				'page'          => $page,
				'per_page'      => $per_page,
			];
		}

		// Modern shape: `{ data: [...], total_count: N }`.
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$items = $response['data'];
			$total = isset( $response['total_count'] ) ? (int) $response['total_count'] : count( $items );
		} elseif ( self::is_flat_list( $response ) ) {
			// Flat list shape.
			$items = $response;
			$total = count( $items );
		} else {
			// Associative payload — leave the body in the envelope under the
			// collection key (the caller's controller response shape didn't
			// match either expected pattern).
			$items = [ $response ];
			$total = 1;
		}

		return [
			$collection_key => $items,
			'total_pages'   => self::compute_total_pages( $total, $per_page ),
			'page'          => $page,
			'per_page'      => $per_page,
		];
	}

	/**
	 * PHP 7.4-compatible check for a numerically-indexed (list-shaped) array.
	 *
	 * @param array $value Array to check.
	 * @return bool
	 */
	private static function is_flat_list( array $value ): bool {
		if ( [] === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
