<?php
/**
 * Class WooPay_AfterShip_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_AfterShip_Provider;

require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/interface-woopay-tracking-provider.php';
require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/class-woopay-aftership-provider.php';

/*
 * Load the AfterShip detection stub. This simulates the plugin being active
 * for the rest of the test suite. PHPUnit auto-loads this stub during its
 * directory scan, so once required it persists for the whole process — the
 * `class_exists('AfterShip') → return false` branch of `is_available()` is
 * therefore not directly testable in this file.
 *
 * The gap is acceptable because the same `class_exists('AfterShip')` check
 * is the standard sentinel AfterShip itself uses, and the provider chain
 * ordering in WooPay_Order_Tracking_Sync ensures higher-priority providers
 * (Fulfillments API, WC Shipment Tracking / AST, ShipStation) run first
 * for merchants who don't have AfterShip installed.
 */
require_once __DIR__ . '/stub-aftership.php';

/**
 * WooPay_AfterShip_Provider unit tests.
 */
class WooPay_AfterShip_Provider_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WooPay_AfterShip_Provider
	 */
	private $provider;

	public function set_up() {
		parent::set_up();
		$this->provider = new WooPay_AfterShip_Provider();
	}

	// -------------------------------------------------------------------------
	// is_available()
	// -------------------------------------------------------------------------

	public function test_is_available_returns_true_when_meta_has_entries() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number' => '398242362749',
					'slug'            => 'fedex',
				],
			]
		);
		$order->save();

		$this->assertTrue( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_meta_absent() {
		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_meta_is_empty_array() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( WooPay_AfterShip_Provider::META_KEY, [] );
		$order->save();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_meta_is_not_array() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( WooPay_AfterShip_Provider::META_KEY, 'not-an-array' );
		$order->save();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	// -------------------------------------------------------------------------
	// get_shipments()
	// -------------------------------------------------------------------------

	public function test_get_shipments_returns_empty_when_meta_absent() {
		$order = WC_Helper_Order::create_order();

		$this->assertEmpty( $this->provider->get_shipments( $order ) );
	}

	public function test_get_shipments_normalizes_tracking_data() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_id'       => '07d0390057d53be7af161ee22dc00eb4',
					'tracking_number'   => '398242362749',
					'slug'              => 'fedex',
					'additional_fields' => [
						'ship_date' => '2026-03-28',
					],
					'line_items'        => [
						[
							'id'       => 3,
							'quantity' => 1,
						],
					],
					'metrics'           => [
						'created_at' => '2026-03-28T20:13:15Z',
						'updated_at' => '2026-03-28T20:13:15Z',
					],
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '398242362749', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'fedex', $shipments[0]['carrier_name'] );
		$this->assertEquals( '2026-03-28', $shipments[0]['date_shipped'] );
		$this->assertEquals( 'fulfilled', $shipments[0]['status'] );
		$this->assertEquals( '', $shipments[0]['tracking_url'] );
		$this->assertEmpty( $shipments[0]['items'] );
	}

	public function test_get_shipments_returns_multiple_shipments() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => '398242362749',
					'slug'              => 'fedex',
					'additional_fields' => [ 'ship_date' => '2026-03-28' ],
				],
				[
					'tracking_number'   => '1Z999AA10123456784',
					'slug'              => 'ups',
					'additional_fields' => [ 'ship_date' => '2026-03-29' ],
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 2, $shipments );
		$this->assertEquals( '398242362749', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'fedex', $shipments[0]['carrier_name'] );
		$this->assertEquals( '1Z999AA10123456784', $shipments[1]['tracking_number'] );
		$this->assertEquals( 'ups', $shipments[1]['carrier_name'] );
	}

	public function test_get_shipments_returns_empty_date_shipped_when_ship_date_absent() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => '398242362749',
					'slug'              => 'fedex',
					'additional_fields' => [],
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
	}

	public function test_get_shipments_returns_empty_date_shipped_when_additional_fields_absent() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number' => '398242362749',
					'slug'            => 'fedex',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
	}

	public function test_get_shipments_returns_empty_date_shipped_when_ship_date_invalid_format() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => '398242362749',
					'slug'              => 'fedex',
					'additional_fields' => [ 'ship_date' => 'not-a-date' ],
				],
				[
					'tracking_number'   => '1Z999AA10123456784',
					'slug'              => 'ups',
					'additional_fields' => [ 'ship_date' => '2026-13-99' ],
				],
				[
					'tracking_number'   => '9400111899223397865584',
					'slug'              => 'usps',
					// Unix timestamp passed as integer — strict Y-m-d round-trip rejects it.
					'additional_fields' => [ 'ship_date' => 1710288000 ],
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 3, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
		$this->assertEquals( '', $shipments[1]['date_shipped'] );
		$this->assertEquals( '', $shipments[2]['date_shipped'] );
	}

	public function test_get_shipments_skips_entries_without_tracking_number() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'slug' => 'fedex',
				],
				[
					'tracking_number' => '398242362749',
					'slug'            => 'fedex',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '398242362749', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_skips_entries_when_tracking_number_sanitizes_to_empty() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number' => '<script></script>',
					'slug'            => 'fedex',
				],
				[
					'tracking_number' => '398242362749',
					'slug'            => 'fedex',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '398242362749', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_skips_non_array_entries() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				'not-an-array',
				[
					'tracking_number' => '398242362749',
					'slug'            => 'fedex',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '398242362749', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_strips_html_from_slug_and_tracking_number() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number' => '<b>1Z999</b>',
					'slug'            => '<script>alert(1)</script>ups',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '1Z999', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'ups', $shipments[0]['carrier_name'] );
	}

	public function test_get_shipments_truncates_pathologically_long_strings() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_AfterShip_Provider::META_KEY,
			[
				[
					'tracking_number' => str_repeat( 'A', 1000 ),
					'slug'            => str_repeat( 'B', 1000 ),
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertSame( 256, mb_strlen( $shipments[0]['tracking_number'] ) );
		$this->assertSame( 256, mb_strlen( $shipments[0]['carrier_name'] ) );
	}

	// -------------------------------------------------------------------------
	// get_hooks()
	// -------------------------------------------------------------------------

	public function test_get_hooks_returns_meta_write_hooks_filtered_by_key() {
		$hooks = $this->provider->get_hooks();

		$this->assertCount( 4, $hooks );

		$expected_hook_names = [
			'added_post_meta',
			'updated_post_meta',
			'added_order_meta',
			'updated_order_meta',
		];

		foreach ( $hooks as $i => $hook ) {
			$this->assertEquals( $expected_hook_names[ $i ], $hook['hook'] );
			$this->assertEquals( 4, $hook['arg_count'] );
			$this->assertEquals( '_aftership_tracking_items', $hook['meta_key'] );
		}
	}
}
