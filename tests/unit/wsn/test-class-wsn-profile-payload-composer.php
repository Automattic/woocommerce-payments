<?php
/**
 * Class WSN_Profile_Payload_Composer_Test
 *
 * @package WooCommerce\Payments\WSN
 */

/**
 * Tests for the canonical Profile sync payload composer.
 *
 * Critical assertions:
 *
 * 1. **Privacy invariant** — the serialized payload must NOT contain the
 *    merchant's stored street address or postcode. Asserted via substring
 *    search on the serialized JSON. This is the defense-in-depth catch
 *    for any future code change that inadvertently leaks address data
 *    into the wire payload.
 *
 * 2. **Producer-stability shape** — the payload uses canonical WCPay-side
 *    field names (tagline, refund_page_url, refund_page_label, etc.). The
 *    WooPay-side handler does the projection into store.* fields at merge
 *    time. We assert the canonical shape is what's emitted, NOT the
 *    storefront shape.
 *
 * 3. **Determinism** — same inputs produce the same payload_version hash.
 *    Different inputs produce different hashes. The skip-emit guard
 *    relies on this.
 */
class WSN_Profile_Payload_Composer_Test extends WCPAY_UnitTestCase {

	const FIXTURE_STREET   = 'SECRET-STREET-DO-NOT-LEAK-12345';
	const FIXTURE_STREET_2 = 'APT-666-LEAK-CANARY';
	const FIXTURE_POSTCODE = 'ZIP-90210-LEAK-CANARY';

	public function set_up() {
		parent::set_up();

		// Seed WC address options with canary strings — the privacy
		// invariant test searches the serialized payload for these.
		update_option( 'woocommerce_store_address', self::FIXTURE_STREET );
		update_option( 'woocommerce_store_address_2', self::FIXTURE_STREET_2 );
		update_option( 'woocommerce_store_postcode', self::FIXTURE_POSTCODE );
		update_option( 'woocommerce_store_city', 'Anytown' );
		update_option( 'woocommerce_default_country', 'US:CA' );
	}

	public function tear_down() {
		delete_option( 'woocommerce_store_address' );
		delete_option( 'woocommerce_store_address_2' );
		delete_option( 'woocommerce_store_postcode' );
		delete_option( 'woocommerce_store_city' );
		delete_option( 'woocommerce_default_country' );
		delete_option( 'wcpay_woopay_checkout_appearance' );

		parent::tear_down();
	}

	/**
	 * The killer test: stored address strings must not appear anywhere
	 * in the serialized payload. If this fails, a code path has leaked
	 * address data into the wire payload and the WooPay-side schema
	 * validator is now the only thing standing between the merchant
	 * and a PII leak — defense in depth requires this layer to catch it.
	 */
	public function test_serialized_payload_does_not_contain_stored_address_strings() {
		$payload    = WSN_Profile_Payload_Composer::compose();
		$serialized = wp_json_encode( $payload );

		$this->assertIsString( $serialized, 'Payload must serialize to JSON.' );
		$this->assertStringNotContainsString(
			self::FIXTURE_STREET,
			$serialized,
			'Stored street address must not appear anywhere in the serialized payload.'
		);
		$this->assertStringNotContainsString(
			self::FIXTURE_STREET_2,
			$serialized,
			'Stored address line 2 must not appear anywhere in the serialized payload.'
		);
		$this->assertStringNotContainsString(
			self::FIXTURE_POSTCODE,
			$serialized,
			'Stored postcode must not appear anywhere in the serialized payload.'
		);
	}

	public function test_location_allowlist_includes_country_region_city_only() {
		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertArrayHasKey( 'location', $payload );
		$location = $payload['location'];

		$this->assertEqualsCanonicalizing(
			[ 'country', 'region', 'city' ],
			array_keys( $location ),
			'location must contain exactly country, region, city — no other keys.'
		);

		$this->assertSame( 'US', $location['country'] );
		$this->assertSame( 'CA', $location['region'] );
		$this->assertSame( 'Anytown', $location['city'] );
	}

	public function test_location_country_and_region_parse_default_country_format() {
		update_option( 'woocommerce_default_country', 'GB' );
		$payload = WSN_Profile_Payload_Composer::compose();
		$this->assertSame( 'GB', $payload['location']['country'] );
		$this->assertNull(
			$payload['location']['region'],
			'No colon in default_country means region is null.'
		);
	}

	public function test_location_returns_nulls_when_nothing_configured() {
		delete_option( 'woocommerce_default_country' );
		delete_option( 'woocommerce_store_city' );

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertNull( $payload['location']['country'] );
		$this->assertNull( $payload['location']['region'] );
		$this->assertNull( $payload['location']['city'] );
	}

	public function test_payload_uses_canonical_field_names_not_storefront_shape() {
		$payload = WSN_Profile_Payload_Composer::compose();

		// Producer-stability: WCPay emits its own naming; WooPay renames at merge.
		// Asserting these keys exist confirms WCPay-side names are NOT being
		// pre-translated into WooPay's storefront naming.
		$this->assertArrayHasKey( 'derivations', $payload );
		$d = $payload['derivations'];

		$this->assertArrayHasKey( 'tagline', $d, 'Emit tagline (canonical), NOT description (storefront name).' );
		$this->assertArrayHasKey( 'refund_page_url', $d, 'Emit separate refund_page_url, NOT bundled return_policy.' );
		$this->assertArrayHasKey( 'refund_page_label', $d, 'Emit separate refund_page_label, NOT bundled return_policy.' );
		$this->assertArrayHasKey( 'shipping_zones', $d, 'Emit shipping_zones array (with per-zone free_shipping inlined), NOT flattened shipping_promise string.' );

		$this->assertArrayNotHasKey( 'description', $d, 'Storefront-shape key description must not appear in canonical payload.' );
		$this->assertArrayNotHasKey( 'shipping_promise', $d, 'Storefront-shape key shipping_promise must not appear in canonical payload.' );
		$this->assertArrayNotHasKey( 'return_policy', $d, 'Storefront-shape key return_policy must not appear in canonical payload.' );
	}

	public function test_payload_has_required_metadata_fields() {
		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertSame( 1, $payload['schema_version'] );
		$this->assertNotEmpty( $payload['payload_version'] );
		$this->assertSame( 64, strlen( $payload['payload_version'] ), 'payload_version is a sha256 hex hash.' );
		$this->assertNotEmpty( $payload['client_updated_at'] );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$payload['client_updated_at'],
			'client_updated_at is gmdate("Y-m-d H:i:s") format.'
		);
		$this->assertArrayHasKey( 'host', $payload );
		$this->assertArrayHasKey( 'blog_id', $payload );
	}

	public function test_payload_version_is_identical_across_consecutive_composes_with_same_inputs() {
		$v1 = WSN_Profile_Payload_Composer::compose()['payload_version'];
		$v2 = WSN_Profile_Payload_Composer::compose()['payload_version'];

		// Critical regression guard for the emitter's skip-emit guard.
		// payload_version MUST be a pure function of content — same data
		// in, same hash out, regardless of wall-clock time at compose
		// time. If volatile fields (client_updated_at, payload_version
		// itself, anything else that varies independent of merchant
		// state) leak into the hash input, the guard never matches and
		// the 6h backstop fires a real POST every tick instead of
		// no-opping when nothing changed.
		$this->assertSame(
			$v1,
			$v2,
			'payload_version must be deterministic for identical inputs. ' .
				'If this assertion fails, a volatile field (likely a timestamp ' .
				'or random value) has leaked into the hashed payload and the ' .
				'emitter\'s skip-emit guard is silently bypassed.'
		);
		$this->assertSame( 64, strlen( $v1 ), 'Sanity: payload_version is a sha256 hex string.' );
	}

	public function test_client_updated_at_does_not_affect_payload_version() {
		// Even though client_updated_at appears in the emitted payload,
		// it MUST NOT be part of the hash input. This is the most likely
		// regression vector for the determinism property above —
		// "wouldn't it be cleaner to include the timestamp in the hash
		// for verification?" is the wrong instinct. The hash is for
		// skip-emit, not auditing; the timestamp is for auditing, not
		// skip-emit. Don't mix the roles.
		$first  = WSN_Profile_Payload_Composer::compose();
		$second = WSN_Profile_Payload_Composer::compose();

		// They differ at the timestamp field (or could, across seconds).
		$this->assertNotEmpty( $first['client_updated_at'] );
		$this->assertNotEmpty( $second['client_updated_at'] );

		// …but the payload_version hash must be identical.
		$this->assertSame( $first['payload_version'], $second['payload_version'] );
	}

	public function test_payload_version_changes_when_shop_name_changes() {
		update_option( 'blogname', 'Original Store Name' );
		$v1 = WSN_Profile_Payload_Composer::compose()['payload_version'];

		update_option( 'blogname', 'Renamed Store' );
		$v2 = WSN_Profile_Payload_Composer::compose()['payload_version'];

		$this->assertNotSame(
			$v1,
			$v2,
			'payload_version must change when shop_name (a derivation) changes.'
		);
	}

	public function test_settings_included_via_wsn_settings_get_all() {
		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertArrayHasKey( 'settings', $payload );
		$this->assertIsArray( $payload['settings'] );
		// WSN_Settings::get_all() returns the 5 wcpay_wsn_* options that survived
		// the Visibility-tab removal (enabled + 4 Profile-tab keys).
		$this->assertArrayHasKey( 'enabled', $payload['settings'] );
		$this->assertArrayHasKey( 'hero_image_id', $payload['settings'] );
	}

	public function test_appearance_is_null_when_styles_cache_unset() {
		delete_option( 'wcpay_woopay_checkout_appearance' );

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertNull( $payload['appearance'] );
		$this->assertNull( $payload['font_rules'] );
	}

	public function test_appearance_resolves_link_current_color_to_text_color() {
		// `currentColor` is CSS-relative — it resolves to the rendering
		// element's `color` at paint time. The composer guards against
		// shipping the keyword raw to WooPay (where it would resolve
		// inside WooPay's own DOM context, producing a different color
		// than the merchant's site shows). See production issue #11777.
		update_option(
			'wcpay_woopay_checkout_appearance',
			[
				'appearance' => [
					'variables' => [
						'colorText' => '#111111',
					],
					'rules'     => [
						'.Text' => [ 'color' => '#222222' ],
						'.Link' => [ 'color' => 'currentColor' ],
					],
				],
				'font_rules' => [],
				'version'    => 'test-version',
			]
		);

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertSame(
			'#222222',
			$payload['appearance']['rules']['.Link']['color'],
			'.Link.color = currentColor must be rewritten to .Text.color before push.'
		);
	}

	public function test_appearance_resolves_footer_link_current_color_via_footer_then_text() {
		// .Footer-link walks .Footer first; only falls back to .Text if
		// .Footer also lacks a literal color.
		update_option(
			'wcpay_woopay_checkout_appearance',
			[
				'appearance' => [
					'variables' => [
						'colorText' => '#111111',
					],
					'rules'     => [
						'.Footer'      => [ 'color' => '#abcdef' ],
						'.Text'        => [ 'color' => '#222222' ],
						'.Footer-link' => [ 'color' => 'currentColor' ],
					],
				],
				'font_rules' => [],
				'version'    => 'test-version',
			]
		);

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertSame(
			'#abcdef',
			$payload['appearance']['rules']['.Footer-link']['color'],
			'.Footer-link must resolve via .Footer.color before walking to .Text.'
		);
	}

	public function test_appearance_falls_back_through_variables_colortext_then_black() {
		// All semantic parents missing or also currentColor → use
		// variables.colorText; if that's missing too, use #000000.
		update_option(
			'wcpay_woopay_checkout_appearance',
			[
				'appearance' => [
					// No variables.colorText, no .Text, no .Footer.
					'rules' => [
						'.Link'        => [ 'color' => 'currentColor' ],
						'.Footer-link' => [ 'color' => 'currentColor' ],
					],
				],
				'font_rules' => [],
				'version'    => 'test-version',
			]
		);

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertSame( '#000000', $payload['appearance']['rules']['.Link']['color'] );
		$this->assertSame( '#000000', $payload['appearance']['rules']['.Footer-link']['color'] );
	}

	public function test_appearance_leaves_literal_link_colors_alone() {
		// Guard is a no-op when the stored value is already a hex.
		// Important once the production extractor fix lands and
		// currentColor stops being written into the option.
		update_option(
			'wcpay_woopay_checkout_appearance',
			[
				'appearance' => [
					'variables' => [ 'colorText' => '#111111' ],
					'rules'     => [
						'.Text' => [ 'color' => '#222222' ],
						'.Link' => [ 'color' => '#0000ff' ],
					],
				],
				'font_rules' => [],
				'version'    => 'test-version',
			]
		);

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertSame(
			'#0000ff',
			$payload['appearance']['rules']['.Link']['color'],
			'Literal hex link colors must pass through unchanged.'
		);
	}

	public function test_appearance_and_font_rules_populated_when_styles_cache_set() {
		update_option(
			'wcpay_woopay_checkout_appearance',
			[
				'appearance' => [
					'theme'     => 'stripe',
					'variables' => [ 'colorPrimary' => '#000' ],
				],
				'font_rules' => [ [ 'cssSrc' => 'https://example.com/font.css' ] ],
				'version'    => 'test-version',
			]
		);

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertSame( 'stripe', $payload['appearance']['theme'] );
		$this->assertSame( '#000', $payload['appearance']['variables']['colorPrimary'] );
		$this->assertCount( 1, $payload['font_rules'] );
		$this->assertSame( 'https://example.com/font.css', $payload['font_rules'][0]['cssSrc'] );
	}

	public function test_logo_dimensions_null_when_no_logo_resolved() {
		// No site_logo, no custom_logo, no override, no site_icon → no
		// attachment ID resolves → no dimensions.
		remove_theme_mod( 'custom_logo' );
		delete_option( 'site_logo' );
		delete_option( 'site_icon' );

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertNull( $payload['logo_dimensions'] );
	}

	public function test_logo_thumb_b64_null_when_no_logo_resolved() {
		remove_theme_mod( 'custom_logo' );
		delete_option( 'site_logo' );
		delete_option( 'site_icon' );

		$payload = WSN_Profile_Payload_Composer::compose();

		$this->assertNull( $payload['logo_thumb_b64'] );
	}

	public function test_host_resolves_from_home_url() {
		$payload = WSN_Profile_Payload_Composer::compose();

		$expected = wp_parse_url( home_url(), PHP_URL_HOST );
		$this->assertSame( $expected, $payload['host'] );
	}
}
