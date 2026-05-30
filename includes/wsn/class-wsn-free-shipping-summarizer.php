<?php
/**
 * Class WSN_Free_Shipping_Summarizer
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Derives a "free shipping at-a-glance" summary from the merchant's WooCommerce
 * shipping zone configuration, for display on the WSN storefront and inside the
 * Hub's Profile tab.
 *
 * Locked design decisions (RSM-2493 Profile-tab planning, 2026-05-30):
 *
 * 1. **Per-zone comma-joined format** (mockup style):
 *      "Orders over $50 (US) · Orders over $75 (CA)"
 *    Direct port of the v2 mockup. Reads cleanly for the typical merchant (1–3
 *    zones); merchants with dozens of zones will see a longer string, but that's
 *    rare and the alternative (summarized) loses per-region detail merchants
 *    asked for in the design discussion.
 *
 * 2. **Skip zones with no qualifying free-shipping method silently**. Only
 *    zones that offer free shipping appear in the summary. Zones with no
 *    shipping methods, or only paid methods, or coupon-only free shipping,
 *    are omitted entirely.
 *
 *    Trade-off accepted: a merchant who configures partial free shipping won't
 *    see "Standard rates apply elsewhere" in the summary. Acceptable for MVP
 *    because the WSN storefront will only show this string at all when there's
 *    free shipping to advertise.
 *
 * 3. **Coupon-only free shipping is excluded.** WC `requires` values
 *    `coupon` and `both` (`min_amount AND coupon`) both gate the free
 *    shipping behind a coupon the WSN shopper doesn't have. Treating those
 *    as "free shipping" would mislead. `min_amount` and `either` (`min_amount
 *    OR coupon`) are kept — the merchant's minimum-order path is reachable
 *    without a coupon.
 *
 * Output shape:
 *
 *     [
 *         'human_summary' => 'Orders over $50 (US) · Orders over $75 (CA)',
 *         'has_free_shipping' => true,
 *         'zones' => [
 *             [ 'zone_name' => 'US', 'min_amount' => 50, 'requires' => 'min_amount' ],
 *             [ 'zone_name' => 'CA', 'min_amount' => 75, 'requires' => 'min_amount' ],
 *         ],
 *     ]
 *
 * `human_summary` is the empty string when no zone qualifies — callers should
 * read `has_free_shipping` rather than relying on string emptiness.
 */
class WSN_Free_Shipping_Summarizer {

	/**
	 * Separator between zone summaries in the human-readable string.
	 *
	 * Middle-dot (·) reads cleaner than comma for short labels and matches the
	 * v2 mockup. Surrounded by non-breaking spaces so the dot doesn't orphan
	 * onto the next line.
	 *
	 * @var string
	 */
	const ZONE_SEPARATOR = "\u{00A0}·\u{00A0}";

	/**
	 * Compute the free-shipping summary across all merchant shipping zones.
	 *
	 * @return array
	 */
	public static function summarize(): array {
		$empty = [
			'human_summary'     => '',
			'has_free_shipping' => false,
			'zones'             => [],
		];

		if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) {
			return $empty;
		}

		$zone_objects = self::collect_zones();
		if ( empty( $zone_objects ) ) {
			return $empty;
		}

		$zone_summaries = [];
		foreach ( $zone_objects as $zone ) {
			$summary = self::summarize_zone( $zone );
			if ( null !== $summary ) {
				$zone_summaries[] = $summary;
			}
		}

		if ( empty( $zone_summaries ) ) {
			return $empty;
		}

		$human_parts = array_map( [ self::class, 'format_zone_for_display' ], $zone_summaries );

		return [
			'human_summary'     => implode( self::ZONE_SEPARATOR, $human_parts ),
			'has_free_shipping' => true,
			'zones'             => $zone_summaries,
		];
	}

	/**
	 * Collect every zone (including the "Rest of the world" zone, which
	 * `WC()->shipping->get_shipping_zones()` excludes — that's zone 0,
	 * accessible via the `WC_Shipping_Zone` constructor).
	 *
	 * @return WC_Shipping_Zone[]
	 */
	private static function collect_zones(): array {
		$zones = [];

		foreach ( WC()->shipping->get_shipping_zones() as $zone_data ) {
			$zone = wc_get_shipping_zone( $zone_data );
			if ( $zone instanceof WC_Shipping_Zone ) {
				$zones[] = $zone;
			}
		}

		// Zone id 0 = "Locations not covered by your other zones". WC excludes
		// it from get_shipping_zones() — fetch explicitly.
		$rest_of_world = new WC_Shipping_Zone( 0 );
		if ( $rest_of_world instanceof WC_Shipping_Zone ) {
			$zones[] = $rest_of_world;
		}

		return $zones;
	}

	/**
	 * Summarize a single zone if it has a qualifying free-shipping method.
	 *
	 * Returns null when the zone has none — caller skips silently.
	 *
	 * @param WC_Shipping_Zone $zone Zone to inspect.
	 * @return array|null
	 */
	private static function summarize_zone( WC_Shipping_Zone $zone ): ?array {
		$methods = $zone->get_shipping_methods( true ); // true = enabled only.
		if ( empty( $methods ) ) {
			return null;
		}

		$cheapest_qualifying = null;

		foreach ( $methods as $method ) {
			if ( 'free_shipping' !== $method->id ) {
				continue;
			}

			$requires   = $method->get_option( 'requires', '' );
			$min_amount = (float) $method->get_option( 'min_amount', 0 );

			// Skip coupon-gated methods — the WSN shopper has no coupon.
			if ( 'coupon' === $requires || 'both' === $requires ) {
				continue;
			}

			$candidate = [
				'zone_name'  => $zone->get_zone_name(),
				'min_amount' => $min_amount,
				'requires'   => (string) $requires,
			];

			// If multiple free-shipping instances are configured in a single
			// zone (unusual but possible), surface the cheapest threshold —
			// that's the rule WSN shoppers care about.
			if ( null === $cheapest_qualifying
				|| $candidate['min_amount'] < $cheapest_qualifying['min_amount']
			) {
				$cheapest_qualifying = $candidate;
			}
		}

		return $cheapest_qualifying;
	}

	/**
	 * Render a single zone summary as a human-readable string fragment.
	 *
	 * @param array $zone_summary One element from the zones[] array returned by summarize_zone().
	 * @return string
	 */
	private static function format_zone_for_display( array $zone_summary ): string {
		$zone_name = (string) $zone_summary['zone_name'];

		if ( $zone_summary['min_amount'] > 0 ) {
			return sprintf(
				/* translators: 1: minimum order amount with currency, 2: shipping zone name */
				__( 'Orders over %1$s (%2$s)', 'woocommerce-payments' ),
				wp_strip_all_tags( wc_price( $zone_summary['min_amount'] ) ),
				$zone_name
			);
		}

		return sprintf(
			/* translators: %s: shipping zone name */
			__( 'Free shipping (%s)', 'woocommerce-payments' ),
			$zone_name
		);
	}
}
