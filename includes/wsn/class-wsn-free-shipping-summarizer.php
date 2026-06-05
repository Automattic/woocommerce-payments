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

		if ( ! class_exists( 'WC_Shipping_Zones' ) || ! class_exists( 'WC_Shipping_Zone' ) ) {
			return $empty;
		}

		$zones = self::collect_zones();
		if ( empty( $zones ) ) {
			return $empty;
		}

		$zone_summaries = [];
		foreach ( $zones as $zone ) {
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
	 * Collect every shipping zone (including zone 0 = "Locations not covered
	 * by your other zones", which `WC_Shipping_Zones::get_zones()` excludes)
	 * as a normalized `[ 'zone_name' => string, 'shipping_methods' => WC_Shipping_Method[] ]`
	 * shape.
	 *
	 * Note: `WC_Shipping_Zones::get_zones()` lives on the DATA class, not on
	 * the WC_Shipping singleton — an easy method-name mix-up.
	 * `WC()->shipping->get_shipping_zones()` does NOT exist; that path
	 * fatals with "Call to undefined method".
	 *
	 * Performance: `WC_Shipping_Zones::get_zones()` already returns the
	 * shipping methods batched into each zone_data entry (see WC core —
	 * `get_data() + 'shipping_methods' => $zone_object->get_shipping_methods( false, 'admin' )`).
	 * Reusing them avoids 2N+2 extra DB queries per call (one zone read
	 * and one methods read per zone) — relevant because this runs on
	 * every GET/PUT /wsn/settings request.
	 *
	 * Zone 0 is the only zone that still requires a fresh
	 * `new WC_Shipping_Zone( 0 )` + `get_shipping_methods()` pair: WC
	 * deliberately excludes it from `get_zones()` and offers no batched
	 * alternative. It's a single zone, so the extra two queries are
	 * acceptable.
	 *
	 * @return array<array{zone_name: string, shipping_methods: array}> Each entry has
	 *   `zone_name` (string) and `shipping_methods` (array of WC_Shipping_Method,
	 *   may include disabled methods — filter in the caller).
	 */
	private static function collect_zones(): array {
		$zones = [];

		foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
			$zones[] = [
				'zone_name'        => (string) $zone_data['zone_name'],
				'shipping_methods' => isset( $zone_data['shipping_methods'] ) && is_array( $zone_data['shipping_methods'] )
					? $zone_data['shipping_methods']
					: [],
			];
		}

		// Zone id 0 = "Locations not covered by your other zones". WC excludes
		// it from get_zones() — fetch explicitly. Normalize into the same shape
		// so summarize_zone() only sees one structure.
		$rest_of_world = new WC_Shipping_Zone( 0 );
		$zones[]       = [
			'zone_name'        => $rest_of_world->get_zone_name(),
			'shipping_methods' => $rest_of_world->get_shipping_methods( false ),
		];

		return $zones;
	}

	/**
	 * Summarize a single zone if it has a qualifying free-shipping method.
	 *
	 * Returns null when the zone has none — caller skips silently.
	 *
	 * Accepts the normalized shape produced by `collect_zones()` rather than
	 * a `WC_Shipping_Zone` so we don't trigger an extra DB read for the
	 * methods that `WC_Shipping_Zones::get_zones()` already loaded.
	 *
	 * @param array{zone_name: string, shipping_methods: array} $zone Normalized zone shape.
	 *   `shipping_methods` may include disabled methods — they're filtered out here.
	 * @return array|null
	 */
	private static function summarize_zone( array $zone ): ?array {
		$methods = $zone['shipping_methods'];
		if ( empty( $methods ) ) {
			return null;
		}

		$cheapest_qualifying = null;

		foreach ( $methods as $method ) {
			if ( 'free_shipping' !== $method->id ) {
				continue;
			}

			// `get_zones()` returns enabled + disabled; mirror the previous
			// `get_shipping_methods( true )` behavior by skipping disabled.
			if ( ! $method->is_enabled() ) {
				continue;
			}

			$requires   = $method->get_option( 'requires', '' );
			$min_amount = (float) $method->get_option( 'min_amount', 0 );

			// Skip coupon-gated methods — the WSN shopper has no coupon.
			if ( 'coupon' === $requires || 'both' === $requires ) {
				continue;
			}

			// Normalize `either` (min_amount OR coupon) to `min_amount` in the
			// response: the coupon arm is unreachable for the WSN shopper, so
			// the only path that matters downstream is the merchant minimum.
			// Keeping `either` would force every consumer to re-derive this.
			$normalized_requires = ( 'either' === $requires ) ? 'min_amount' : (string) $requires;

			$candidate = [
				'zone_name'  => $zone['zone_name'],
				'min_amount' => $min_amount,
				'requires'   => $normalized_requires,
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
		// Zone names are merchant-controlled (entered in WC → Settings → Shipping
		// → Zones with no markup validation). Strip tags here so the JSON
		// contract is text-only — defense in depth even though current
		// consumers (React Overview dashboard auto-escapes via JSX) render it
		// safely. Matches the wp_strip_all_tags treatment of wc_price() below.
		$zone_name = wp_strip_all_tags( (string) $zone_summary['zone_name'] );

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
