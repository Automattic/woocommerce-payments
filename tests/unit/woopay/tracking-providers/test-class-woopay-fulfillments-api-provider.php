<?php
/**
 * Class WooPay_Fulfillments_API_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_Fulfillments_API_Provider;
use WCPay\WooPay\WooPay_Order_Tracking_Sync;

require_once __DIR__ . '/stub-wc-fulfillment.php';
require_once __DIR__ . '/fake-fulfillment.php';
require_once __DIR__ . '/fake-fulfillments-data-store.php';

/**
 * WooPay_Fulfillments_API_Provider unit tests.
 */
class WooPay_Fulfillments_API_Provider_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WooPay_Fulfillments_API_Provider
	 */
	private $provider;

	public function set_up() {
		parent::set_up();
		$this->provider                                = new WooPay_Fulfillments_API_Provider();
		Fake_Fulfillments_Data_Store::$next_result     = [];
		Fake_Fulfillments_Data_Store::$read_call_count = 0;

		// WC_Data_Store::load() reads the woocommerce_data_stores filter on each
		// invocation (instances are not class-level memoized), so a filter is
		// sufficient to swap in our fake without any reflection trickery.
		add_filter( 'woocommerce_data_stores', [ $this, 'register_fake_data_store' ] );
	}

	public function tear_down() {
		remove_filter( 'woocommerce_data_stores', [ $this, 'register_fake_data_store' ] );
		Fake_Fulfillments_Data_Store::$next_result     = [];
		Fake_Fulfillments_Data_Store::$read_call_count = 0;
		parent::tear_down();
	}

	public function register_fake_data_store( $stores ) {
		$stores['order-fulfillment'] = Fake_Fulfillments_Data_Store::class;
		return $stores;
	}

	public function test_is_available_returns_false_when_no_fulfillments() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [];

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_true_when_fulfillments_exist() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [ new Fake_Fulfillment( [ '_tracking_number' => 'X1' ] ) ];

		$this->assertTrue( $this->provider->is_available( $order ) );
	}

	public function test_get_shipments_normalizes_fulfillment_data() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			new Fake_Fulfillment(
				[
					'_tracking_number'   => '1Z999AA10123456784',
					'_shipment_provider' => 'UPS',
					'_tracking_url'      => 'https://wwwapps.ups.com/track/?tracknum=1Z999AA10123456784',
					'_date_shipped'      => '2024-03-13',
				]
			),
		];

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '1Z999AA10123456784', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'UPS', $shipments[0]['carrier_name'] );
		$this->assertEquals( 'https://wwwapps.ups.com/track/?tracknum=1Z999AA10123456784', $shipments[0]['tracking_url'] );
		$this->assertEquals( '2024-03-13', $shipments[0]['date_shipped'] );
		$this->assertEquals( 'fulfilled', $shipments[0]['status'] );
	}

	public function test_get_shipments_maps_unfulfilled_status_to_canonical_fulfilled() {
		// WC Fulfillments uses 'fulfilled' / 'unfulfilled' (binary off is_fulfilled).
		// Both must map to canonical STATUS_FULFILLED upstream of the canonical-
		// coercion logger, so 'unfulfilled' with a tracking number doesn't spam logs.
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			new Fake_Fulfillment( [ '_tracking_number' => 'X1' ], 'unfulfilled' ),
		];

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( WooPay_Order_Tracking_Sync::STATUS_FULFILLED, $shipments[0]['status'] );
	}

	public function test_get_shipments_skips_fulfillments_without_tracking_number() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			new Fake_Fulfillment( [ '_tracking_number' => '' ] ),
			new Fake_Fulfillment(
				[
					'_tracking_number'   => 'X2',
					'_shipment_provider' => 'FedEx',
				]
			),
		];

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'X2', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_rejects_javascript_url() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			new Fake_Fulfillment(
				[
					'_tracking_number' => 'X1',
					'_tracking_url'    => 'javascript:alert(1)',
				]
			),
		];

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['tracking_url'] );
	}

	public function test_get_shipments_strips_html_from_carrier() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			new Fake_Fulfillment(
				[
					'_tracking_number'   => 'X1',
					'_shipment_provider' => '<script>alert(1)</script>UPS',
				]
			),
		];

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'UPS', $shipments[0]['carrier_name'] );
	}

	public function test_get_shipments_skips_fulfillments_whose_tracking_number_sanitizes_to_empty() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			// Tags-only — strips to '' and must be skipped.
			new Fake_Fulfillment( [ '_tracking_number' => '<script></script>' ] ),
			// Whitespace-only — trims to '' and must be skipped.
			new Fake_Fulfillment( [ '_tracking_number' => "   \t  " ] ),
			// Valid entry.
			new Fake_Fulfillment(
				[
					'_tracking_number'   => 'X2',
					'_shipment_provider' => 'FedEx',
				]
			),
		];

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'X2', $shipments[0]['tracking_number'] );
	}

	public function test_provider_chain_invocation_reads_fulfillments_only_once_per_order() {
		$order = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			new Fake_Fulfillment(
				[
					'_tracking_number'   => 'X1',
					'_shipment_provider' => 'UPS',
				]
			),
		];

		// Simulate the provider chain: is_available() then get_shipments() for the same order.
		$this->assertTrue( $this->provider->is_available( $order ) );
		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertSame(
			1,
			Fake_Fulfillments_Data_Store::$read_call_count,
			'is_available() and get_shipments() must share one DataStore read for the same order.'
		);
	}

	public function test_cache_is_per_order_not_global() {
		$order_a = WC_Helper_Order::create_order();
		$order_b = WC_Helper_Order::create_order();

		Fake_Fulfillments_Data_Store::$next_result = [
			new Fake_Fulfillment( [ '_tracking_number' => 'X1' ] ),
		];

		$this->provider->is_available( $order_a );
		$this->provider->is_available( $order_b );

		$this->assertSame(
			2,
			Fake_Fulfillments_Data_Store::$read_call_count,
			'Different orders must each trigger a fresh DataStore read.'
		);
	}

	public function test_get_hooks_returns_fulfillment_lifecycle_hooks() {
		$hooks = $this->provider->get_hooks();

		$this->assertCount( 4, $hooks );
		$hook_names = array_column( $hooks, 'hook' );
		$this->assertContains( 'woocommerce_fulfillment_after_create', $hook_names );
		$this->assertContains( 'woocommerce_fulfillment_after_update', $hook_names );
		$this->assertContains( 'woocommerce_fulfillment_after_delete', $hook_names );
		$this->assertContains( 'woocommerce_fulfillment_after_fulfill', $hook_names );
	}
}
