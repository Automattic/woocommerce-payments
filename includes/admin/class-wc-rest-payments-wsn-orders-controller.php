<?php
/**
 * Class WC_REST_Payments_WSN_Orders_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the Woo Shopping Network Hub Overview tab data.
 *
 * Route:
 *   GET /wp-json/wc/v3/payments/wsn/orders?period=today|7d|30d|90d|12m
 *
 * Returns recent WSN-attributed orders and aggregate stat-card values for the
 * Overview dashboard. Orders are identified by the `_woopay_marketplace_*` meta
 * keys per [api-contract.md §7](../../../../../woopay/docs/wsn/api-contract.md#7-marketplace-origin-metadata-on-the-order):
 *
 *   - `_woopay_marketplace_order = true`               (always present on WSN orders)
 *   - `_woopay_marketplace_storefront_slug = <slug>`   (which merchant page they came from)
 *   - `_woopay_marketplace_cohort = a|b`               (handoff cohort)
 *
 * Both Cohorts (A: Store API extension, B: URL-param) stamp the same keys, so this
 * controller doesn't need to know which cohort produced the order.
 *
 * **Empty-state semantics:** until the WooPay-side Cohort A/B work (RSM-2484/2485) ships,
 * no orders carry the marketplace meta. The endpoint returns `is_empty: true` with
 * the stats object empty and the orders list empty — the Overview UI renders `—` for
 * every stat and a single "first WSN purchase will appear here" row for the table.
 * This is by design: ship the surface ahead of the data.
 *
 * **No total-revenue aggregation yet:** the v2 mockup wants "Network revenue / Total
 * revenue" framing but querying the total-revenue side requires another order query
 * (without the meta filter) and can be expensive on busy stores. Deferred — the
 * `reference` field on each StatCard renders empty when null.
 */
class WC_REST_Payments_WSN_Orders_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path under the namespace.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/wsn/orders';

	/**
	 * Hard cap on returned recent-orders rows. The Overview table renders ~6 by default;
	 * the cap is here so a future "show all" link can't accidentally pull thousands.
	 *
	 * @var int
	 */
	const RECENT_ORDERS_LIMIT = 20;

	/**
	 * Marketplace-order meta keys (canonical, per api-contract.md §7).
	 */
	const META_IS_MARKETPLACE  = '_woopay_marketplace_order';
	const META_STOREFRONT_SLUG = '_woopay_marketplace_storefront_slug';
	const META_COHORT          = '_woopay_marketplace_cohort';

	/**
	 * Period -> seconds-back lookup. Keep in sync with the `period` enum below.
	 */
	const PERIOD_SECONDS = [
		'today' => DAY_IN_SECONDS,
		'7d'    => 7 * DAY_IN_SECONDS,
		'30d'   => 30 * DAY_IN_SECONDS,
		'90d'   => 90 * DAY_IN_SECONDS,
		'12m'   => 365 * DAY_IN_SECONDS,
	];

	/**
	 * Registers REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_orders' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'period' => [
							'description'       => __( 'Time window for the stats and orders list.', 'woocommerce-payments' ),
							'type'              => 'string',
							'enum'              => array_keys( self::PERIOD_SECONDS ),
							'default'           => '30d',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);
	}

	/**
	 * Capability check. WSN Hub data exposure is a merchant-admin action.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * GET handler — returns `{ period, is_empty, stats, orders }`.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function get_orders( WP_REST_Request $request ) {
		$period = (string) $request->get_param( 'period' );
		$since  = $this->get_since_timestamp( $period );

		$orders = $this->fetch_marketplace_orders( $since, self::RECENT_ORDERS_LIMIT );

		$payload = [
			'period'   => $period,
			'is_empty' => empty( $orders ),
			'stats'    => $this->compute_stats( $orders ),
			'orders'   => array_map( [ $this, 'format_order' ], $orders ),
		];

		return rest_ensure_response( $payload );
	}

	/**
	 * Compute the unix timestamp the period started.
	 *
	 * @param string $period One of the keys in self::PERIOD_SECONDS.
	 * @return int
	 */
	private function get_since_timestamp( string $period ): int {
		$seconds = self::PERIOD_SECONDS[ $period ] ?? self::PERIOD_SECONDS['30d'];
		return time() - $seconds;
	}

	/**
	 * Fetch WC orders carrying the marketplace meta keys, created since $since.
	 *
	 * Uses `wc_get_orders()` rather than raw `WP_Query` so it works against either
	 * HPOS (`wp_wc_orders` + `wp_wc_orders_meta`) or the legacy CPT (`wp_posts` +
	 * `wp_postmeta`) — WC's data store abstracts the difference.
	 *
	 * @param int $since Unix timestamp lower bound for `date_created`.
	 * @param int $limit Maximum number of orders to return.
	 * @return WC_Order[]
	 */
	private function fetch_marketplace_orders( int $since, int $limit ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return [];
		}

		$orders = wc_get_orders(
			[
				'limit'        => $limit,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>=' . $since,
				'meta_query'   => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => self::META_IS_MARKETPLACE,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		// Defensive: wc_get_orders can return false-ish when an upstream filter
		// hijacks it. Always normalize to an array of order objects.
		if ( ! is_array( $orders ) ) {
			return [];
		}

		return array_filter(
			$orders,
			static fn( $order ) => $order instanceof WC_Order
		);
	}

	/**
	 * Aggregate stats from the fetched orders. Only computes the metrics derivable
	 * from order data alone — telemetry-dependent fields (Products Viewed, Top
	 * Discovery Source) remain null and the UI renders them as `—`.
	 *
	 * @param WC_Order[] $orders Marketplace-tagged orders aggregated for the dashboard.
	 * @return array
	 */
	private function compute_stats( array $orders ): array {
		if ( empty( $orders ) ) {
			return [];
		}

		$network_count   = count( $orders );
		$network_revenue = 0.0;
		$source_buckets  = [];

		foreach ( $orders as $order ) {
			$network_revenue += (float) $order->get_total();

			// Source tagging (e.g., 'favorites', 'browse', 'recommendations') is a
			// post-MVP signal that may be added to the marketplace meta later.
			// Aggregate whatever the cohort hooks happen to record on the order.
			$cohort = $order->get_meta( self::META_COHORT, true );
			if ( ! empty( $cohort ) ) {
				$source_buckets[ $cohort ] = ( $source_buckets[ $cohort ] ?? 0 ) + 1;
			}
		}

		$network_aov = $network_count > 0 ? $network_revenue / $network_count : 0.0;

		$top_source = null;
		$top_share  = null;
		if ( ! empty( $source_buckets ) ) {
			arsort( $source_buckets );
			$top_source    = (string) array_key_first( $source_buckets );
			$top_share_pct = ( $source_buckets[ $top_source ] / $network_count ) * 100;
			$top_share     = sprintf( '%.1f%%', $top_share_pct );
		}

		return [
			'network_orders'            => $network_count,
			'network_revenue_formatted' => wc_price( $network_revenue ),
			'network_aov_formatted'     => wc_price( $network_aov ),
			'top_source'                => $top_source,
			'top_source_share'          => $top_share,
			// Intentionally omitted (rendered as `—` by StatCard):
			// products_listed, products_viewed, products_viewed_pct,
			// network_order_rate, network_revenue_pct,
			// total_orders, total_revenue_formatted
			// — these depend on either WooPay-side telemetry (views) or a
			// separate per-period query against ALL orders (total_*). Deferred
			// to follow-up issues.
		];
	}

	/**
	 * Project a WC_Order into the slim shape the Overview orders table consumes.
	 *
	 * Edit URL goes through `admin_url`/`wc_get_order_admin_edit_url` (HPOS-aware)
	 * so the link works whether the order lives in the orders table or the legacy
	 * posts table.
	 *
	 * @param WC_Order $order The order to project into the table row shape.
	 * @return array
	 */
	private function format_order( WC_Order $order ): array {
		$created   = $order->get_date_created();
		$timestamp = $created instanceof WC_DateTime ? $created->getTimestamp() : 0;

		return [
			'id'              => $order->get_id(),
			'number'          => $order->get_order_number(),
			'customer_name'   => trim( $order->get_formatted_billing_full_name() ),
			'date_iso'        => $timestamp > 0 ? gmdate( DateTime::ATOM, $timestamp ) : null,
			'date_relative'   => $timestamp > 0
				? sprintf(
					/* translators: %s: relative time, e.g. "2 hours" */
					__( '%s ago', 'woocommerce-payments' ),
					human_time_diff( $timestamp )
				)
				: '',
			'status'          => $order->get_status(),
			'status_label'    => wc_get_order_status_name( $order->get_status() ),
			'items'           => array_values(
				array_map(
					static fn( $item ) => $item->get_name(),
					$order->get_items()
				)
			),
			'source'          => $this->meta_string_or_null( $order, self::META_COHORT ),
			'storefront_slug' => $this->meta_string_or_null( $order, self::META_STOREFRONT_SLUG ),
			'total_formatted' => wc_price( $order->get_total() ),
			'edit_url'        => function_exists( 'wc_get_order_admin_edit_url' )
				? wc_get_order_admin_edit_url( $order->get_id() )
				: admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
		];
	}

	/**
	 * Read a single meta value as a string, returning null when the value is
	 * absent or empty. Avoids short-ternary expressions that PHPCS forbids
	 * (Universal.Operators.DisallowShortTernary).
	 *
	 * @param WC_Order $order    The order to read meta from.
	 * @param string   $meta_key The meta key to look up.
	 * @return string|null
	 */
	private function meta_string_or_null( WC_Order $order, string $meta_key ): ?string {
		$value = (string) $order->get_meta( $meta_key, true );
		return '' === $value ? null : $value;
	}
}
