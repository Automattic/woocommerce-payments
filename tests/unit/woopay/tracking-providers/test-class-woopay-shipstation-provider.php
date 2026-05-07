<?php
/**
 * Class WooPay_ShipStation_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_ShipStation_Provider;

require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/interface-woopay-tracking-provider.php';
require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/class-woopay-shipstation-provider.php';

/*
 * Load the ShipStation detection stub. This simulates the plugin being active.
 * WC_Shipment_Tracking is intentionally NOT loaded here: once that stub is
 * required it persists for the whole PHPUnit process and would break
 * is_available() and persist_tracking_data() for every subsequent test.
 *
 * The class_exists('WC_Shipment_Tracking') → return false branch of
 * is_available() and persist_tracking_data() is therefore not directly
 * testable in this file. This gap is acceptable because:
 * (a) ShipStation's own source uses the identical sentinel to decide which
 * meta path to write — if WC_Shipment_Tracking is present, ShipStation calls
 * wc_st_add_tracking_number() and the Phase 1 provider handles the order;
 * (b) the provider chain ordering in WooPay_Order_Tracking_Sync ensures
 * Phase 1 (WooPay_Shipment_Tracking_Provider) wins first.
 */
require_once __DIR__ . '/stub-wc-shipstation-integration.php';

/**
 * WooPay_ShipStation_Provider unit tests.
 */
class WooPay_ShipStation_Provider_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WooPay_ShipStation_Provider
	 */
	private $provider;

	public function set_up() {
		parent::set_up();
		$this->provider = new WooPay_ShipStation_Provider();
	}

	// -------------------------------------------------------------------------
	// is_available()
	// -------------------------------------------------------------------------

	public function test_is_available_returns_true_when_owned_meta_populated() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => '9400111899223397865584',
					'carrier_name'    => 'usps',
					'date_shipped'    => 1710288000,
				],
			]
		);
		$order->save();

		$this->assertTrue( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_owned_meta_absent() {
		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_owned_meta_empty_array() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( WooPay_ShipStation_Provider::META_KEY, [] );
		$order->save();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_owned_meta_not_array() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( WooPay_ShipStation_Provider::META_KEY, 'not-an-array' );
		$order->save();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	// -------------------------------------------------------------------------
	// get_shipments()
	// -------------------------------------------------------------------------

	public function test_get_shipments_returns_empty_when_owned_meta_absent() {
		$order = WC_Helper_Order::create_order();

		$this->assertEmpty( $this->provider->get_shipments( $order ) );
	}

	public function test_get_shipments_normalizes_persisted_entry() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => '9400111899223397865584',
					'carrier_name'    => 'usps',
					'date_shipped'    => 1710288000, // 2024-03-13.
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '9400111899223397865584', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'usps', $shipments[0]['carrier_name'] );
		$this->assertEquals( '2024-03-13', $shipments[0]['date_shipped'] );
		$this->assertEquals( 'fulfilled', $shipments[0]['status'] );
		$this->assertEquals( '', $shipments[0]['tracking_url'] );
		$this->assertEmpty( $shipments[0]['items'] );
	}

	public function test_get_shipments_returns_multiple_persisted_entries() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => '9400111899223397865584',
					'carrier_name'    => 'usps',
					'date_shipped'    => 1710288000,
				],
				[
					'tracking_number' => '1Z999AA10123456784',
					'carrier_name'    => 'ups',
					'date_shipped'    => 1710374400,
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 2, $shipments );
		$this->assertEquals( '9400111899223397865584', $shipments[0]['tracking_number'] );
		$this->assertEquals( '1Z999AA10123456784', $shipments[1]['tracking_number'] );
	}

	public function test_get_shipments_returns_empty_date_shipped_when_timestamp_zero() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => '1Z999AA10123456784',
					'carrier_name'    => 'ups',
					'date_shipped'    => 0,
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
	}

	public function test_get_shipments_returns_empty_date_shipped_when_field_absent() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => '1Z999AA10123456784',
					'carrier_name'    => 'ups',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
	}

	public function test_get_shipments_strips_html_from_carrier_and_tracking_number() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => '<b>1Z999</b>',
					'carrier_name'    => '<script>alert(1)</script>ups',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '1Z999', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'ups', $shipments[0]['carrier_name'] );
	}

	public function test_get_shipments_skips_entries_when_tracking_number_sanitizes_to_empty() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => '<script></script>',
					'carrier_name'    => 'ups',
				],
				[
					'tracking_number' => '1Z999',
					'carrier_name'    => 'ups',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '1Z999', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_skips_non_array_entries() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				'not-an-array',
				[
					'tracking_number' => '1Z999',
					'carrier_name'    => 'ups',
				],
			]
		);
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '1Z999', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_truncates_pathologically_long_strings() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_ShipStation_Provider::META_KEY,
			[
				[
					'tracking_number' => str_repeat( 'A', 1000 ),
					'carrier_name'    => str_repeat( 'B', 1000 ),
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
	// persist_tracking_data()
	// -------------------------------------------------------------------------

	public function test_persist_tracking_data_appends_entry_to_owned_meta() {
		$order = WC_Helper_Order::create_order();

		WooPay_ShipStation_Provider::persist_tracking_data(
			$order,
			[
				'tracking_number' => '9400111899223397865584',
				'carrier'         => 'usps',
				'ship_date'       => 1710288000,
			]
		);

		$reloaded = wc_get_order( $order->get_id() );
		$items    = $reloaded->get_meta( WooPay_ShipStation_Provider::META_KEY );

		$this->assertIsArray( $items );
		$this->assertCount( 1, $items );
		$this->assertSame( '9400111899223397865584', $items[0]['tracking_number'] );
		$this->assertSame( 'usps', $items[0]['carrier_name'] );
		$this->assertSame( 1710288000, $items[0]['date_shipped'] );
	}

	public function test_persist_tracking_data_appends_to_existing_entries() {
		$order = WC_Helper_Order::create_order();

		WooPay_ShipStation_Provider::persist_tracking_data(
			$order,
			[
				'tracking_number' => '9400111899223397865584',
				'carrier'         => 'usps',
				'ship_date'       => 1710288000,
			]
		);

		$order = wc_get_order( $order->get_id() );

		WooPay_ShipStation_Provider::persist_tracking_data(
			$order,
			[
				'tracking_number' => '1Z999AA10123456784',
				'carrier'         => 'ups',
				'ship_date'       => 1710374400,
			]
		);

		$reloaded = wc_get_order( $order->get_id() );
		$items    = $reloaded->get_meta( WooPay_ShipStation_Provider::META_KEY );

		$this->assertCount( 2, $items );
		$this->assertSame( '9400111899223397865584', $items[0]['tracking_number'] );
		$this->assertSame( '1Z999AA10123456784', $items[1]['tracking_number'] );
	}

	public function test_persist_tracking_data_skips_when_payload_not_array() {
		$order = WC_Helper_Order::create_order();

		WooPay_ShipStation_Provider::persist_tracking_data( $order, 'not-an-array' );

		$this->assertEmpty( $order->get_meta( WooPay_ShipStation_Provider::META_KEY ) );
	}

	public function test_persist_tracking_data_skips_when_order_not_wc_order() {
		// A non-order argument should be ignored without fatals.
		WooPay_ShipStation_Provider::persist_tracking_data(
			'not-an-order',
			[
				'tracking_number' => '1Z999',
				'carrier'         => 'ups',
				'ship_date'       => 1710288000,
			]
		);

		// Nothing to assert beyond "no fatal" — passing reaching this line is the contract.
		$this->assertTrue( true );
	}

	public function test_persist_tracking_data_skips_when_tracking_number_missing() {
		$order = WC_Helper_Order::create_order();

		WooPay_ShipStation_Provider::persist_tracking_data(
			$order,
			[
				'carrier'   => 'ups',
				'ship_date' => 1710288000,
			]
		);

		$this->assertEmpty( $order->get_meta( WooPay_ShipStation_Provider::META_KEY ) );
	}

	public function test_persist_tracking_data_skips_when_tracking_number_whitespace() {
		$order = WC_Helper_Order::create_order();

		WooPay_ShipStation_Provider::persist_tracking_data(
			$order,
			[
				'tracking_number' => '   ',
				'carrier'         => 'ups',
				'ship_date'       => 1710288000,
			]
		);

		$this->assertEmpty( $order->get_meta( WooPay_ShipStation_Provider::META_KEY ) );
	}

	// -------------------------------------------------------------------------
	// register_persistence_hooks()
	// -------------------------------------------------------------------------

	public function test_register_persistence_hooks_registers_callback_at_priority_9() {
		// Ensure clean baseline.
		remove_action(
			'woocommerce_shipstation_shipnotify',
			[ WooPay_ShipStation_Provider::class, 'persist_tracking_data' ],
			9
		);

		WooPay_ShipStation_Provider::register_persistence_hooks();

		$this->assertSame(
			9,
			has_action(
				'woocommerce_shipstation_shipnotify',
				[ WooPay_ShipStation_Provider::class, 'persist_tracking_data' ]
			)
		);

		// Cleanup so other tests aren't affected by the global hook registration.
		remove_action(
			'woocommerce_shipstation_shipnotify',
			[ WooPay_ShipStation_Provider::class, 'persist_tracking_data' ],
			9
		);
	}

	// -------------------------------------------------------------------------
	// get_hooks()
	// -------------------------------------------------------------------------

	public function test_get_hooks_returns_expected_hook() {
		$hooks = $this->provider->get_hooks();

		$this->assertCount( 1, $hooks );
		$this->assertEquals( 'woocommerce_shipstation_shipnotify', $hooks[0]['hook'] );
		$this->assertEquals( 2, $hooks[0]['arg_count'] );
	}
}
