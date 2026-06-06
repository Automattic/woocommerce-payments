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
 *             [
 *                 'zone_id'          => 1,
 *                 'zone_locations'   => [ [ 'type' => 'country', 'code' => 'US' ] ],
 *                 'is_rest_of_world' => false,
 *                 'min_amount'       => 50,
 *                 'requires'         => 'min_amount',
 *             ],
 *             [
 *                 'zone_id'          => 0,
 *                 'zone_locations'   => [],          // zone 0 has no locations by design
 *                 'is_rest_of_world' => true,
 *                 'min_amount'       => 100,
 *                 'requires'         => 'min_amount',
 *             ],
 *         ],
 *     ]
 *
 * Receivers should consume `zone_locations` (the standard WC contract:
 * [{type, code}] where type is country / state / postcode / continent)
 * and the `is_rest_of_world` boolean to render the catch-all zone. The
 * old `zone_name` field has been removed — it was a merchant-chosen
 * label and not standard across stores.
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
	 * as a normalized shape carrying `zone_id`, `zone_locations`,
	 * `shipping_methods`, and `is_rest_of_world`.
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
	 * @return array<array{zone_id: int, zone_locations: array, shipping_methods: array, is_rest_of_world: bool}>
	 *   `shipping_methods` may include disabled methods — filter in the caller.
	 */
	private static function collect_zones(): array {
		$zones = [];

		foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
			// `get_zones()` returns each zone's `zone_locations` array
			// pre-populated. Each location is a stdClass with
			// `code` (string) and `type` (country / state / postcode /
			// continent). Pass through the raw structure — receivers
			// are the standard WC contract, no per-WSN translation here.
			$zones[] = [
				'zone_id'          => (int) $zone_data['id'],
				'zone_locations'   => self::normalize_zone_locations(
					$zone_data['zone_locations'] ?? []
				),
				'shipping_methods' => isset( $zone_data['shipping_methods'] ) && is_array( $zone_data['shipping_methods'] )
					? $zone_data['shipping_methods']
					: [],
				'is_rest_of_world' => false,
			];
		}

		// Zone id 0 = "Locations not covered by your other zones". WC excludes
		// it from get_zones() — fetch explicitly. Has no zone_locations rows
		// by design (its meaning is "everywhere else"), so we mark it with
		// `is_rest_of_world` to disambiguate from a misconfigured zone that
		// the merchant created and never added locations to.
		$rest_of_world = new WC_Shipping_Zone( 0 );
		$zones[]       = [
			'zone_id'          => 0,
			'zone_locations'   => [],
			'shipping_methods' => $rest_of_world->get_shipping_methods( false ),
			'is_rest_of_world' => true,
		];

		return $zones;
	}

	/**
	 * Normalize WC zone-locations into pure-data array of [type, code] pairs.
	 *
	 * WC returns each location as a `stdClass` with `code` and `type`
	 * properties (sometimes also `zone_id`). The composer's canonical-JSON
	 * step would serialize stdClass objects but the output isn't
	 * deterministic across PHP versions — flatten to plain arrays so the
	 * payload_version hash stays stable.
	 *
	 * @param array $raw WC's `zone_locations` array.
	 * @return array<array{type: string, code: string}>
	 */
	private static function normalize_zone_locations( array $raw ): array {
		$out = [];
		foreach ( $raw as $loc ) {
			if ( is_object( $loc ) ) {
				$type = isset( $loc->type ) ? (string) $loc->type : '';
				$code = isset( $loc->code ) ? (string) $loc->code : '';
			} elseif ( is_array( $loc ) ) {
				$type = isset( $loc['type'] ) ? (string) $loc['type'] : '';
				$code = isset( $loc['code'] ) ? (string) $loc['code'] : '';
			} else {
				continue;
			}
			if ( '' === $type || '' === $code ) {
				continue;
			}
			$out[] = [
				'type' => $type,
				'code' => $code,
			];
		}
		return $out;
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
	 * @param array{zone_id: int, zone_locations: array, shipping_methods: array, is_rest_of_world: bool} $zone Normalized zone shape.
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
				'zone_id'          => (int) ( $zone['zone_id'] ?? 0 ),
				'zone_locations'   => isset( $zone['zone_locations'] ) && is_array( $zone['zone_locations'] )
					? $zone['zone_locations']
					: [],
				'is_rest_of_world' => ! empty( $zone['is_rest_of_world'] ),
				'min_amount'       => $min_amount,
				'requires'         => $normalized_requires,
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
		$zone_label = self::format_zone_label( $zone_summary );

		if ( $zone_summary['min_amount'] > 0 ) {
			return sprintf(
				/* translators: 1: minimum order amount with currency, 2: shipping zone label */
				__( 'Orders over %1$s (%2$s)', 'woocommerce-payments' ),
				html_entity_decode(
					wp_strip_all_tags( wc_price( $zone_summary['min_amount'] ) ),
					ENT_QUOTES | ENT_HTML5,
					'UTF-8'
				),
				$zone_label
			);
		}

		return sprintf(
			/* translators: %s: shipping zone label */
			__( 'Free shipping (%s)', 'woocommerce-payments' ),
			$zone_label
		);
	}

	/**
	 * Build the human-readable label for a zone summary using its
	 * normalized `zone_locations` array. Used inside `human_summary`
	 * (Profile-tab readonly field) — the wire payload itself ships the
	 * `zone_locations` array verbatim so receivers can decide their own
	 * rendering.
	 *
	 * Labeling rules:
	 *   - Zone 0 (rest of world): "Rest of World".
	 *   - Single location: the code (e.g. "US", or "US:CA" for a state).
	 *   - Multiple locations: comma-joined codes (e.g. "US, CA, MX").
	 *   - No locations and not the rest-of-world zone (rare misconfig):
	 *     fall back to a generic "Free-shipping zone" placeholder so the
	 *     string doesn't read awkwardly. The composer's privacy + content
	 *     contracts are unaffected — the wire payload reflects the empty
	 *     locations honestly.
	 *
	 * @param array $zone_summary One element from the zones[] array returned by summarize_zone().
	 * @return string
	 */
	private static function format_zone_label( array $zone_summary ): string {
		if ( ! empty( $zone_summary['is_rest_of_world'] ) ) {
			return __( 'Rest of World', 'woocommerce-payments' );
		}

		$locations = isset( $zone_summary['zone_locations'] ) && is_array( $zone_summary['zone_locations'] )
			? $zone_summary['zone_locations']
			: [];

		$codes = [];
		foreach ( $locations as $loc ) {
			if ( isset( $loc['code'] ) && '' !== $loc['code'] ) {
				$codes[] = (string) $loc['code'];
			}
		}

		if ( empty( $codes ) ) {
			return __( 'Free-shipping zone', 'woocommerce-payments' );
		}

		return implode( ', ', $codes );
	}
}
