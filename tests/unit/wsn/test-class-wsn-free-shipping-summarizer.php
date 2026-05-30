<?php
/**
 * Class WSN_Free_Shipping_Summarizer_Test
 *
 * @package WooCommerce\Payments\WSN
 */

/**
 * Unit tests for the free-shipping summarizer.
 *
 * Exercises the locked design decisions for the Profile-tab free shipping
 * summary string:
 *   - per-zone comma-joined (separator is U+00A0·U+00A0 — mockup style)
 *   - skip zones with no qualifying free-shipping method silently
 *   - coupon-gated free shipping is treated as no free shipping
 *   - within-zone multiple instances: surface the cheapest threshold
 */
class WSN_Free_Shipping_Summarizer_Test extends WCPAY_UnitTestCase {

	/**
	 * IDs of zones the test created. Cleaned up in tear_down.
	 *
	 * @var int[]
	 */
	private $created_zone_ids = [];

	public function tear_down() {
		foreach ( $this->created_zone_ids as $zone_id ) {
			$zone = new WC_Shipping_Zone( $zone_id );
			$zone->delete();
		}
		$this->created_zone_ids = [];

		// Also clear any methods added to the "Rest of the world" (zone 0).
		$rest = new WC_Shipping_Zone( 0 );
		foreach ( $rest->get_shipping_methods( false ) as $method ) {
			$rest->delete_shipping_method( $method->instance_id );
		}

		parent::tear_down();
	}

	public function test_summarize_returns_empty_when_no_zones_have_free_shipping() {
		$this->create_zone_with_method( 'US Standard', 'US', 'flat_rate', [] );

		$result = WSN_Free_Shipping_Summarizer::summarize();

		$this->assertFalse( $result['has_free_shipping'] );
		$this->assertSame( '', $result['human_summary'] );
		$this->assertSame( [], $result['zones'] );
	}

	public function test_summarize_includes_zones_with_min_amount_free_shipping() {
		$this->create_zone_with_method(
			'US',
			'US',
			'free_shipping',
			[
				'min_amount' => 50,
				'requires'   => 'min_amount',
			]
		);
		$this->create_zone_with_method(
			'Canada',
			'CA',
			'free_shipping',
			[
				'min_amount' => 75,
				'requires'   => 'min_amount',
			]
		);

		$result = WSN_Free_Shipping_Summarizer::summarize();

		$this->assertTrue( $result['has_free_shipping'] );
		$this->assertCount( 2, $result['zones'] );

		// human_summary uses the U+00A0·U+00A0 separator and includes both zones.
		$this->assertStringContainsString( 'US', $result['human_summary'] );
		$this->assertStringContainsString( 'Canada', $result['human_summary'] );
		$this->assertStringContainsString( '50', wp_strip_all_tags( $result['human_summary'] ) );
		$this->assertStringContainsString( '75', wp_strip_all_tags( $result['human_summary'] ) );
		$this->assertStringContainsString( '·', $result['human_summary'] );
	}

	public function test_summarize_silently_skips_coupon_only_free_shipping() {
		$this->create_zone_with_method(
			'US',
			'US',
			'free_shipping',
			[
				'min_amount' => 0,
				'requires'   => 'coupon',
			]
		);

		$result = WSN_Free_Shipping_Summarizer::summarize();

		$this->assertFalse(
			$result['has_free_shipping'],
			'Coupon-only free shipping must NOT appear in the summary — the WSN shopper has no coupon to apply.'
		);
		$this->assertSame( [], $result['zones'] );
	}

	public function test_summarize_silently_skips_coupon_AND_min_amount() {
		$this->create_zone_with_method(
			'US',
			'US',
			'free_shipping',
			[
				'min_amount' => 50,
				'requires'   => 'both',
			]
		);

		$result = WSN_Free_Shipping_Summarizer::summarize();

		$this->assertFalse(
			$result['has_free_shipping'],
			'`requires=both` (coupon AND min_amount) is coupon-gated; must be excluded.'
		);
	}

	public function test_summarize_keeps_either_coupon_or_min_amount() {
		$this->create_zone_with_method(
			'US',
			'US',
			'free_shipping',
			[
				'min_amount' => 50,
				'requires'   => 'either',
			]
		);

		$result = WSN_Free_Shipping_Summarizer::summarize();

		$this->assertTrue(
			$result['has_free_shipping'],
			'`requires=either` is reachable WITHOUT a coupon via the min_amount path.'
		);
		$this->assertSame( 'min_amount', $result['zones'][0]['requires'] );
	}

	public function test_summarize_with_multiple_free_shipping_instances_picks_cheapest() {
		$zone_id = $this->create_zone_with_method(
			'EU',
			'GB',
			'free_shipping',
			[
				'min_amount' => 100,
				'requires'   => 'min_amount',
			]
		);

		// Add a second free-shipping method to the same zone with a lower threshold.
		$zone                 = new WC_Shipping_Zone( $zone_id );
		$cheaper_instance_id  = $zone->add_shipping_method( 'free_shipping' );
		$cheaper              = WC()->shipping->get_shipping_methods()['free_shipping'];
		$cheaper->instance_id = $cheaper_instance_id;
		update_option(
			"woocommerce_free_shipping_{$cheaper_instance_id}_settings",
			[
				'min_amount' => 25,
				'requires'   => 'min_amount',
			]
		);

		$result = WSN_Free_Shipping_Summarizer::summarize();

		$this->assertTrue( $result['has_free_shipping'] );
		$this->assertCount( 1, $result['zones'] );
		$this->assertSame(
			25.0,
			$result['zones'][0]['min_amount'],
			'Within a zone, the cheapest qualifying threshold is what shoppers care about.'
		);
	}

	/**
	 * Create a WC shipping zone with a single shipping method instance.
	 *
	 * @param string $zone_name   Display name for the zone.
	 * @param string $country     ISO country code to add as a zone location.
	 * @param string $method_id   WC shipping method id (e.g. 'free_shipping', 'flat_rate').
	 * @param array  $settings    Settings for the method instance (e.g. min_amount, requires).
	 * @return int Zone ID for further mutation by the test.
	 */
	private function create_zone_with_method( string $zone_name, string $country, string $method_id, array $settings ): int {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( $zone_name );
		$zone->add_location( $country, 'country' );
		$zone_id                  = $zone->save();
		$this->created_zone_ids[] = $zone_id;

		$instance_id = $zone->add_shipping_method( $method_id );
		if ( ! empty( $settings ) ) {
			update_option( "woocommerce_{$method_id}_{$instance_id}_settings", $settings );
		}
		return $zone_id;
	}
}
