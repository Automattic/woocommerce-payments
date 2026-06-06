<?php
/**
 * Class WSN_Derivations
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Computes the WSN derivations — values the Profile UI + the Profile-sync
 * composer need that AREN'T stored as WSN options. They're derived from WC
 * core options, the WP site identity, the active theme, the shipping zone
 * configuration, and the resolved attachment URLs.
 *
 * Lives in its own class (rather than as a private method on the settings
 * controller) so the upcoming Profile sync emitter (RSM-3945) can call the
 * SAME derivation logic the controller exposes via GET /wsn/settings — no
 * duplication, no instantiating the controller (which requires the API
 * client) in a background-job context.
 *
 * All methods are static + side-effect-free. Returns null for any field
 * that doesn't resolve so consumers can render placeholders. Never throws —
 * degrades silently when WC isn't fully loaded.
 *
 * Owned by RSM-2470 (extracted from the settings controller). Will be
 * consumed by RSM-3945 (Profile sync emitter / composer).
 */
class WSN_Derivations {

	/**
	 * Compute the full derivations array.
	 *
	 * @return array Fully-resolved derivations. Shape matches what the
	 *               settings controller GET endpoint returns under the
	 *               `derivations` key. Stable contract — consumers depend
	 *               on this shape (Profile tab UI, sync composer, etc.).
	 */
	public static function compute(): array {
		$logo_override_id = WSN_Settings::get_logo_override_id();

		// Build the fallback chain that determines what shows when the
		// merchant hasn't set a WSN override:
		//
		// 1. Site logo — `theme_mod custom_logo` (classic themes) OR
		// `option site_logo` (block / FSE themes set via the Site
		// Editor). Block themes never populate `custom_logo`, so
		// checking only one path silently misses half of installs.
		// Prefer custom_logo when both are set.
		// 2. Site icon (favicon) — many small-store merchants have only
		// a favicon and no proper site logo; using it as a brand
		// mark in WSN is better than rendering "No logo".
		//
		// `default_logo_*` exposes both the URL and the source-of-truth
		// so the editor UI can tell the merchant which fallback is in
		// use ('Using your site logo' vs. 'Using your site icon — set a
		// proper site logo in the Site Editor').
		$site_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $site_logo_id <= 0 ) {
			$site_logo_id = (int) get_option( 'site_logo', 0 );
		}

		$default_logo_url       = null;
		$default_logo_source    = 'none';
		$default_logo_attach_id = null;
		if ( $site_logo_id > 0 ) {
			$default_logo_url    = self::resolve_attachment_url( $site_logo_id );
			$default_logo_source = null === $default_logo_url ? 'none' : 'site_logo';
			if ( null !== $default_logo_url ) {
				$default_logo_attach_id = $site_logo_id;
			}
		}
		if ( null === $default_logo_url ) {
			$site_icon_id = (int) get_option( 'site_icon', 0 );
			if ( $site_icon_id > 0 ) {
				$default_logo_url    = self::resolve_attachment_url( $site_icon_id );
				$default_logo_source = null === $default_logo_url ? 'none' : 'site_icon';
				if ( null !== $default_logo_url ) {
					$default_logo_attach_id = $site_icon_id;
				}
			}
		}

		$override_logo_url = self::resolve_attachment_url( $logo_override_id );
		$logo_url          = $override_logo_url ?? $default_logo_url;
		// Track which attachment ID actually resolved into logo_url so
		// consumers (e.g. the Profile sync composer) can look up image
		// metadata (width/height) without re-running the fallback chain.
		$logo_attachment_id = null !== $override_logo_url
			? $logo_override_id
			: $default_logo_attach_id;

		$refund_page_id    = WSN_Settings::get_refund_page_id();
		$refund_page_label = null;
		$refund_page_url   = null;
		if ( null !== $refund_page_id && $refund_page_id > 0 ) {
			$post = get_post( $refund_page_id );
			if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
				$refund_page_label = get_the_title( $post );
				$refund_page_url   = (string) get_permalink( $post );
			}
		}

		// Synced-from-source fields render readonly in the Profile UI — the
		// merchant edits them at their source (WP Settings > General for the
		// site title + tagline via blogname/blogdescription, WC > Shipping for
		// zones). Sending them in this payload prevents the Profile tab from
		// needing additional REST calls.
		$shop_name = (string) get_bloginfo( 'name' );
		$tagline   = (string) get_bloginfo( 'description' );

		return [
			'logo_url'              => $logo_url,
			'logo_attachment_id'    => $logo_attachment_id,
			// `default_logo_url` is what shows when the merchant clears
			// the override — could be the site logo OR the site icon.
			// `default_logo_source` tells the editor which one so the
			// "Synced from …" copy can be accurate ('site_logo' vs
			// 'site_icon' vs 'none').
			'default_logo_url'      => $default_logo_url,
			'default_logo_source'   => $default_logo_source,
			'logo_source'           => null !== $logo_override_id && null !== $override_logo_url
				? 'override'
				: $default_logo_source,
			'hero_image_url'        => self::resolve_attachment_url( WSN_Settings::get_hero_image_id() ),
			'shop_name'             => $shop_name,
			'tagline'               => $tagline,
			'default_contact_email' => WSN_Settings::resolve_default_contact_email(),
			'shipping_regions'      => self::collect_shipping_region_names(),
			'free_shipping'         => self::compute_free_shipping(),
			'refund_page_label'     => $refund_page_label,
			'refund_page_url'       => $refund_page_url,
			'theme_type'            => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ? 'block' : 'classic',
			// Location is computed here (single source for both the
			// outbound payload + the Profile-tab readonly UI). Composer
			// pulls country/region/city from this block — see
			// WSN_Profile_Payload_Composer::collect_location_allowlist().
			// `country_label` and `region_label` are the human forms
			// (e.g. "United States" / "California") for the UI; they
			// are NOT shipped to WooPay — the payload's `location`
			// allowlist sticks to the three primitive codes.
			'location'              => self::collect_location_for_ui(),
		];
	}

	/**
	 * Collect the merchant's store location for the Profile tab read-only UI.
	 *
	 * Returns the same three primitive fields the composer ships to WooPay
	 * (country / region / city — these are the privacy allowlist), plus
	 * human-friendly labels resolved via WooCommerce's country/state lookups
	 * for the merchant-visible UI.
	 *
	 * @return array { country: ?string, region: ?string, city: ?string, country_label: ?string, region_label: ?string }
	 */
	private static function collect_location_for_ui(): array {
		$default_country = (string) get_option( 'woocommerce_default_country', '' );
		$country         = null;
		$region          = null;
		if ( '' !== $default_country ) {
			$parts   = explode( ':', $default_country, 2 );
			$country = '' !== $parts[0] ? $parts[0] : null;
			$region  = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : null;
		}

		$city = (string) get_option( 'woocommerce_store_city', '' );

		// Resolve human labels via WC's `countries` helper. Falls back to
		// the code when the helper isn't available (e.g. WC not bootstrapped
		// in a CLI context) or the lookup returns nothing.
		$country_label = null;
		$region_label  = null;
		if ( function_exists( 'WC' ) && null !== $country ) {
			$countries = WC()->countries;
			if ( $countries ) {
				$names         = $countries->get_countries();
				$country_label = $names[ $country ] ?? null;

				if ( null !== $region ) {
					$states       = $countries->get_states( $country );
					$region_label = is_array( $states ) ? ( $states[ $region ] ?? null ) : null;
				}
			}
		}

		return [
			'country'       => $country,
			'region'        => $region,
			'city'          => '' !== $city ? $city : null,
			'country_label' => $country_label,
			'region_label'  => $region_label,
		];
	}

	/**
	 * Resolve an attachment ID to its URL. Returns null for null/invalid IDs
	 * or attachments WP can't resolve — a cleaner JSON contract than the
	 * `false` wp_get_attachment_url() returns on lookup failure.
	 *
	 * @param int|null $attachment_id Attachment ID to resolve, or null/0 for "unset".
	 * @return string|null URL string, or null if the attachment can't be resolved.
	 */
	private static function resolve_attachment_url( ?int $attachment_id ): ?string {
		if ( null === $attachment_id || $attachment_id <= 0 ) {
			return null;
		}
		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? $url : null;
	}

	/**
	 * Collect human-readable zone names — what the Profile tab shows in the
	 * readonly "Shipping regions" field.
	 *
	 * Note: `WC()->shipping()` returns WC_Shipping (the singleton), which does
	 * NOT have a get_shipping_zones method — that lives on WC_Shipping_Zones
	 * (the data class). Using the wrong one fatals; this is a load-bearing
	 * distinction the test suite locks in.
	 *
	 * Includes zone 0 ("Locations not covered by your other zones"), which
	 * `WC_Shipping_Zones::get_zones()` excludes — stores with only the
	 * catch-all zone would otherwise see an empty Shipping regions field
	 * even though they ship internationally.
	 *
	 * Filters every zone (including zone 0) to those with at least one
	 * ENABLED shipping method. The signal merchants care about is "do you
	 * actually ship to locations here", not "does WC have a row for this
	 * zone." A zone with no enabled methods can't serve shoppers there,
	 * so listing it as a destination misrepresents reach. The catch-all
	 * is the worst offender — WC creates it by default and it's always
	 * named, even on stores that don't ship outside named zones.
	 *
	 * @return string[]
	 */
	private static function collect_shipping_region_names(): array {
		if ( ! class_exists( 'WC_Shipping_Zones' ) || ! class_exists( 'WC_Shipping_Zone' ) ) {
			return [];
		}

		$names = [];

		// Named zones — get_zones() already returns shipping_methods
		// batched per zone, so we don't trigger a second DB read per
		// zone to check method enablement.
		foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
			$zone_name = isset( $zone_data['zone_name'] ) ? (string) $zone_data['zone_name'] : '';
			if ( '' === $zone_name ) {
				continue;
			}
			$methods = isset( $zone_data['shipping_methods'] ) && is_array( $zone_data['shipping_methods'] )
				? $zone_data['shipping_methods']
				: [];
			if ( self::has_enabled_method( $methods ) ) {
				$names[] = $zone_name;
			}
		}

		// Zone 0 — fetch explicitly. get_zones() excludes it. Single zone,
		// one extra DB read for the methods.
		//
		// Relabel as "Rest of World" before surfacing. WC's literal
		// zone_name ("Locations not covered by your other zones") is
		// long and reads as bureaucratic in a merchant-facing UI;
		// "Rest of World" is what merchants colloquially call it and
		// matches the conventional WSN/storefront copy. Only the
		// surfaced label changes — WC core stays untouched and the
		// underlying zone is still zone 0.
		$rest_of_world = new WC_Shipping_Zone( 0 );
		if ( self::has_enabled_method( $rest_of_world->get_shipping_methods( false ) ) ) {
			$names[] = __( 'Rest of World', 'woocommerce-payments' );
		}

		return $names;
	}

	/**
	 * True when any method in the array is enabled. Defensive about array
	 * shape so a caller that passes a non-array or non-object element
	 * (rare in WC core but possible via extension filters) doesn't fatal.
	 *
	 * @param array $methods Shipping methods array from WC.
	 * @return bool
	 */
	private static function has_enabled_method( array $methods ): bool {
		foreach ( $methods as $method ) {
			if ( is_object( $method ) && method_exists( $method, 'is_enabled' ) && $method->is_enabled() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compute the free-shipping summary structure. Returns null when the
	 * summarizer class isn't loaded — defensive against future autoload
	 * changes.
	 *
	 * @return array|null
	 */
	private static function compute_free_shipping(): ?array {
		if ( ! class_exists( 'WSN_Free_Shipping_Summarizer' ) ) {
			return null;
		}
		return WSN_Free_Shipping_Summarizer::summarize();
	}
}
