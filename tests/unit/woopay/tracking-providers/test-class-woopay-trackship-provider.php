<?php
/**
 * Class WooPay_TrackShip_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Order_Tracking_Sync;
use WCPay\WooPay\Tracking_Providers\WooPay_TrackShip_Provider;

require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/interface-woopay-tracking-provider.php';
require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/interface-woopay-status-overlay-provider.php';
require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/class-woopay-trackship-provider.php';

/*
 * Load the TrackShip detection stub. Once required it persists for the whole
 * PHPUnit process — so the `class_exists('Trackship_For_Woocommerce') → false`
 * branch of `is_available()` is not directly testable here. The gap is
 * acceptable because this provider produces no primary shipments
 * (`get_shipments()` always returns `[]`), so the only consequence of a
 * falsely-true `is_available()` would be running the overlay path on orders
 * with no relevant meta — which is a fast no-op.
 */
require_once __DIR__ . '/stub-trackship.php';

/**
 * WooPay_TrackShip_Provider unit tests.
 */
class WooPay_TrackShip_Provider_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WooPay_TrackShip_Provider
	 */
	private $provider;

	public function set_up() {
		parent::set_up();
		$this->provider = new WooPay_TrackShip_Provider();

		// Clean any prior hook registration between tests so the priority-9
		// assertions don't false-positive from leftover state.
		remove_action(
			WooPay_TrackShip_Provider::TRACKING_HOOK,
			[ WooPay_TrackShip_Provider::class, 'persist_tracking_data' ],
			9
		);
	}

	public function tear_down() {
		remove_action(
			WooPay_TrackShip_Provider::TRACKING_HOOK,
			[ WooPay_TrackShip_Provider::class, 'persist_tracking_data' ],
			9
		);
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// is_available() / get_shipments() / get_hooks()
	// -------------------------------------------------------------------------

	public function test_is_available_returns_true_when_trackship_loaded() {
		$order = WC_Helper_Order::create_order();

		$this->assertTrue( $this->provider->is_available( $order ) );
	}

	public function test_get_shipments_always_returns_empty() {
		// TrackShip is a status enricher, not a primary producer. Even with
		// meta populated, get_shipments() never produces primary entries.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_TrackShip_Provider::META_KEY,
			[
				[
					'tracking_number' => '1Z999',
					'status'          => WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT,
				],
			]
		);
		$order->save();

		$this->assertSame( [], $this->provider->get_shipments( $order ) );
	}

	public function test_get_hooks_returns_empty_array() {
		// We intentionally don't trigger send_webhook on TrackShip's status
		// hook — the enrichment is captured into meta and forwarded by the
		// next Phase 1 webhook.
		$this->assertSame( [], $this->provider->get_hooks() );
	}

	// -------------------------------------------------------------------------
	// register_persistence_hooks()
	// -------------------------------------------------------------------------

	public function test_register_persistence_hooks_registers_listener_at_priority_9() {
		WooPay_TrackShip_Provider::register_persistence_hooks();

		$this->assertSame(
			9,
			has_action(
				WooPay_TrackShip_Provider::TRACKING_HOOK,
				[ WooPay_TrackShip_Provider::class, 'persist_tracking_data' ]
			)
		);
	}

	// -------------------------------------------------------------------------
	// persist_tracking_data() — happy paths
	// -------------------------------------------------------------------------

	public function test_persist_writes_canonical_entry_for_in_transit() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data(
			$order->get_id(),
			'',
			'in_transit',
			'1Z999AA10123456784'
		);

		$reloaded = wc_get_order( $order->get_id() );
		$entries  = $reloaded->get_meta( WooPay_TrackShip_Provider::META_KEY );

		$this->assertIsArray( $entries );
		$this->assertCount( 1, $entries );
		$this->assertSame( '1Z999AA10123456784', $entries[0]['tracking_number'] );
		$this->assertSame( WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT, $entries[0]['status'] );
		$this->assertArrayHasKey( 'status_updated_at', $entries[0] );
		// ISO 8601 UTC: 2026-05-13T12:34:56Z — 20 chars exactly.
		$this->assertSame( 20, strlen( $entries[0]['status_updated_at'] ) );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			$entries[0]['status_updated_at']
		);
	}

	public function test_persist_upserts_existing_entry_for_same_tracking_number() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data(
			$order->get_id(),
			'',
			'in_transit',
			'1Z999'
		);
		WooPay_TrackShip_Provider::persist_tracking_data(
			$order->get_id(),
			'in_transit',
			'out_for_delivery',
			'1Z999'
		);

		$reloaded = wc_get_order( $order->get_id() );
		$entries  = $reloaded->get_meta( WooPay_TrackShip_Provider::META_KEY );

		// Still one entry per tracking number — last writer wins.
		$this->assertCount( 1, $entries );
		$this->assertSame(
			WooPay_Order_Tracking_Sync::STATUS_OUT_FOR_DELIVERY,
			$entries[0]['status']
		);
	}

	public function test_persist_keeps_separate_entries_for_different_tracking_numbers() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', 'in_transit', '1Z999' );
		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', 'out_for_delivery', '9400111' );

		$reloaded = wc_get_order( $order->get_id() );
		$entries  = $reloaded->get_meta( WooPay_TrackShip_Provider::META_KEY );

		$this->assertCount( 2, $entries );
	}

	// -------------------------------------------------------------------------
	// persist_tracking_data() — early-exit conditions
	// -------------------------------------------------------------------------

	public function test_persist_skips_when_order_id_not_numeric() {
		WooPay_TrackShip_Provider::persist_tracking_data( 'not-a-number', '', 'in_transit', '1Z999' );
		// No fatal, nothing to assert beyond that.
		$this->assertTrue( true );
	}

	public function test_persist_skips_when_tracking_event_status_empty() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', '', '1Z999' );

		$this->assertEmpty( $order->get_meta( WooPay_TrackShip_Provider::META_KEY ) );
	}

	public function test_persist_skips_when_tracking_number_empty() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', 'in_transit', '' );

		$this->assertEmpty( $order->get_meta( WooPay_TrackShip_Provider::META_KEY ) );
	}

	public function test_persist_skips_when_tracking_number_whitespace_only() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', 'in_transit', "   \t  " );

		$this->assertEmpty( $order->get_meta( WooPay_TrackShip_Provider::META_KEY ) );
	}

	public function test_persist_strips_html_from_tracking_number() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data(
			$order->get_id(),
			'',
			'in_transit',
			'<script>alert(1)</script>1Z999AA10123456784'
		);

		$reloaded = wc_get_order( $order->get_id() );
		$entries  = $reloaded->get_meta( WooPay_TrackShip_Provider::META_KEY );
		$this->assertCount( 1, $entries );
		$this->assertSame( '1Z999AA10123456784', $entries[0]['tracking_number'] );
	}

	public function test_persist_skips_when_tracking_number_sanitizes_to_empty() {
		// Tags-only input: wp_strip_all_tags + trim produces ''.
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data(
			$order->get_id(),
			'',
			'in_transit',
			'<script></script>'
		);

		$this->assertEmpty( $order->get_meta( WooPay_TrackShip_Provider::META_KEY ) );
	}

	public function test_persist_truncates_pathologically_long_tracking_number() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data(
			$order->get_id(),
			'',
			'in_transit',
			str_repeat( 'A', 1000 )
		);

		$reloaded = wc_get_order( $order->get_id() );
		$entries  = $reloaded->get_meta( WooPay_TrackShip_Provider::META_KEY );
		$this->assertCount( 1, $entries );
		$this->assertSame(
			WooPay_TrackShip_Provider::STRING_FIELD_MAX_LEN,
			mb_strlen( $entries[0]['tracking_number'] )
		);
	}

	public function test_persist_skips_when_previous_equals_new_status() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data(
			$order->get_id(),
			'in_transit',
			'in_transit',
			'1Z999'
		);

		$this->assertEmpty( $order->get_meta( WooPay_TrackShip_Provider::META_KEY ) );
	}

	public function test_persist_proceeds_when_previous_is_empty_string_first_event() {
		// TrackShip's REST endpoint passes empty string for previous_status
		// on the first event for a shipment (not null). The early-exit must
		// not treat `'' === 'in_transit'` as a duplicate.
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', 'in_transit', '1Z999' );

		$reloaded = wc_get_order( $order->get_id() );
		$entries  = $reloaded->get_meta( WooPay_TrackShip_Provider::META_KEY );
		$this->assertCount( 1, $entries );
		$this->assertSame( WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT, $entries[0]['status'] );
	}

	public function test_persist_drops_unknown_status_silently() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', 'unknown', '1Z999' );

		$this->assertEmpty( $order->get_meta( WooPay_TrackShip_Provider::META_KEY ) );
	}

	public function test_persist_drops_garbage_status_silently() {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', 'wibble', '1Z999' );

		$this->assertEmpty( $order->get_meta( WooPay_TrackShip_Provider::META_KEY ) );
	}

	public function test_persist_skips_when_order_does_not_exist() {
		WooPay_TrackShip_Provider::persist_tracking_data( 999999, '', 'in_transit', '1Z999' );
		// No fatal. Nothing to assert beyond that.
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// Status normalization table — verify mapping for every TrackShip value
	// -------------------------------------------------------------------------

	public function provide_status_mappings(): array {
		return [
			'pre_transit collapses to in_transit' => [ 'pre_transit', WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT ],
			'in_transit passes through'           => [ 'in_transit', WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT ],
			'out_for_delivery passes through'     => [ 'out_for_delivery', WooPay_Order_Tracking_Sync::STATUS_OUT_FOR_DELIVERY ],
			'available_for_pickup is distinct'    => [ 'available_for_pickup', WooPay_Order_Tracking_Sync::STATUS_AVAILABLE_FOR_PICKUP ],
			'delivered passes through'            => [ 'delivered', WooPay_Order_Tracking_Sync::STATUS_DELIVERED ],
			'exception passes through'            => [ 'exception', WooPay_Order_Tracking_Sync::STATUS_EXCEPTION ],
			'failure rolls up under exception'    => [ 'failure', WooPay_Order_Tracking_Sync::STATUS_EXCEPTION ],
			'return_to_sender rolls up'           => [ 'return_to_sender', WooPay_Order_Tracking_Sync::STATUS_EXCEPTION ],
			'on_hold rolls up'                    => [ 'on_hold', WooPay_Order_Tracking_Sync::STATUS_EXCEPTION ],
		];
	}

	/**
	 * @dataProvider provide_status_mappings
	 */
	public function test_normalize_status_maps_correctly( string $input, string $expected_canonical ) {
		$order = WC_Helper_Order::create_order();

		WooPay_TrackShip_Provider::persist_tracking_data( $order->get_id(), '', $input, '1Z999' );

		// `persist_tracking_data` updates a fresh `wc_get_order()` instance
		// internally; reload to pick up the change.
		$reloaded = wc_get_order( $order->get_id() );
		$entries  = $reloaded->get_meta( WooPay_TrackShip_Provider::META_KEY );
		$this->assertCount( 1, $entries, sprintf( 'Input %s should produce one entry', $input ) );
		$this->assertSame( $expected_canonical, $entries[0]['status'] );
	}

	// -------------------------------------------------------------------------
	// overlay() — enrichment semantics
	// -------------------------------------------------------------------------

	public function test_overlay_returns_input_unchanged_when_no_meta() {
		$order     = WC_Helper_Order::create_order();
		$shipments = [
			[
				'tracking_number' => '1Z999',
				'carrier_name'    => 'UPS',
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
			],
		];

		$result = $this->provider->overlay( $order, $shipments );

		$this->assertSame( $shipments, $result );
	}

	public function test_overlay_returns_empty_when_shipments_empty() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_TrackShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => '1Z999',
					'status'            => WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT,
					'status_updated_at' => '2026-05-13T12:00:00Z',
				],
			]
		);
		$order->save();

		$this->assertSame( [], $this->provider->overlay( $order, [] ) );
	}

	public function test_overlay_enriches_matching_shipment_by_tracking_number() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_TrackShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => '1Z999',
					'status'            => WooPay_Order_Tracking_Sync::STATUS_OUT_FOR_DELIVERY,
					'status_updated_at' => '2026-05-13T12:00:00Z',
				],
			]
		);
		$order->save();

		$shipments = [
			[
				'tracking_number' => '1Z999',
				'carrier_name'    => 'UPS',
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
			],
		];

		$result = $this->provider->overlay( $order, $shipments );

		$this->assertCount( 1, $result );
		$this->assertSame( WooPay_Order_Tracking_Sync::STATUS_OUT_FOR_DELIVERY, $result[0]['status'] );
		$this->assertSame( '2026-05-13T12:00:00Z', $result[0]['status_updated_at'] );
		$this->assertSame( 'UPS', $result[0]['carrier_name'], 'Non-overlaid fields must pass through.' );
	}

	public function test_overlay_skips_shipments_without_meta_match() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_TrackShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => '1Z999',
					'status'            => WooPay_Order_Tracking_Sync::STATUS_OUT_FOR_DELIVERY,
					'status_updated_at' => '2026-05-13T12:00:00Z',
				],
			]
		);
		$order->save();

		$shipments = [
			[
				'tracking_number' => '1Z999',
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
			],
			[
				'tracking_number' => '9400111',
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
			],
		];

		$result = $this->provider->overlay( $order, $shipments );

		$this->assertCount( 2, $result );
		$this->assertSame( WooPay_Order_Tracking_Sync::STATUS_OUT_FOR_DELIVERY, $result[0]['status'] );
		$this->assertSame( WooPay_Order_Tracking_Sync::STATUS_FULFILLED, $result[1]['status'] );
		$this->assertArrayNotHasKey( 'status_updated_at', $result[1] );
	}

	public function test_overlay_preserves_cardinality() {
		// Same-cardinality contract: overlay never adds or removes shipments.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_TrackShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => 'ORPHAN',
					'status'            => WooPay_Order_Tracking_Sync::STATUS_DELIVERED,
					'status_updated_at' => '2026-05-13T12:00:00Z',
				],
			]
		);
		$order->save();

		$shipments = [
			[
				'tracking_number' => '1Z999',
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
			],
		];

		$result = $this->provider->overlay( $order, $shipments );

		// Meta entry for 'ORPHAN' must NOT spawn a new shipment.
		$this->assertCount( 1, $result );
		$this->assertSame( '1Z999', $result[0]['tracking_number'] );
	}

	public function test_overlay_is_idempotent() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_TrackShip_Provider::META_KEY,
			[
				[
					'tracking_number'   => '1Z999',
					'status'            => WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT,
					'status_updated_at' => '2026-05-13T12:00:00Z',
				],
			]
		);
		$order->save();

		$shipments = [
			[
				'tracking_number' => '1Z999',
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
			],
		];

		$once  = $this->provider->overlay( $order, $shipments );
		$twice = $this->provider->overlay( $order, $once );

		$this->assertSame( $once, $twice );
	}

	public function test_overlay_skips_non_array_meta_entries() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data(
			WooPay_TrackShip_Provider::META_KEY,
			[
				'garbage',
				[
					'tracking_number'   => '1Z999',
					'status'            => WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT,
					'status_updated_at' => '2026-05-13T12:00:00Z',
				],
			]
		);
		$order->save();

		$shipments = [
			[
				'tracking_number' => '1Z999',
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
			],
		];

		$result = $this->provider->overlay( $order, $shipments );

		$this->assertSame( WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT, $result[0]['status'] );
	}
}
