<?php
/**
 * Class WooPay_Shipment_Tracking_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_Shipment_Tracking_Provider;

// Stub the sentinel classes the provider checks for. Both WC Shipment Tracking
// and AST share the same `_wc_shipment_tracking_items` meta key. Loading both
// stubs exercises both detection branches (the provider uses OR — either is
// sufficient for is_available()). Once loaded the stubs persist for the rest
// of the PHPUnit process, so the "neither plugin present" negative branch is
// covered indirectly by integration tests in WooPay_Order_Tracking_Sync_Test
// that do not require this file.
require_once __DIR__ . '/stub-wc-shipment-tracking-actions.php';
require_once __DIR__ . '/stub-wc-advanced-shipment-tracking-actions.php';

/**
 * WooPay_Shipment_Tracking_Provider unit tests.
 */
class WooPay_Shipment_Tracking_Provider_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WooPay_Shipment_Tracking_Provider
	 */
	private $provider;

	public function set_up() {
		parent::set_up();
		$this->provider = new WooPay_Shipment_Tracking_Provider();
	}

	public function test_is_available_returns_true_when_plugin_present_with_data() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wc_shipment_tracking_items', [ [ 'tracking_number' => 'X1' ] ] );
		$order->save();

		$this->assertTrue( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_plugin_present_but_no_meta() {
		$order = WC_Helper_Order::create_order();
		// No `_wc_shipment_tracking_items` meta.

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_meta_is_not_array() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wc_shipment_tracking_items', 'not-an-array' );
		$order->save();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_get_shipments_returns_empty_when_no_meta() {
		$order = WC_Helper_Order::create_order();

		$this->assertEmpty( $this->provider->get_shipments( $order ) );
	}

	public function test_get_shipments_normalizes_tracking_data() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_provider'        => 'UPS',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => '1Z999AA10123456784',
				'date_shipped'             => '1710288000', // 2024-03-13.
			],
		];

		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '1Z999AA10123456784', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'UPS', $shipments[0]['carrier_name'] );
		$this->assertEquals( '2024-03-13', $shipments[0]['date_shipped'] );
		$this->assertEquals( 'fulfilled', $shipments[0]['status'] );
		$this->assertEmpty( $shipments[0]['items'] );
	}

	public function test_get_shipments_uses_custom_provider_as_fallback() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_provider'        => '',
				'custom_tracking_provider' => 'My Custom Carrier',
				'custom_tracking_link'     => 'https://example.com/track/ABC123',
				'tracking_number'          => 'ABC123',
				'date_shipped'             => '1710288000',
			],
		];

		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'My Custom Carrier', $shipments[0]['carrier_name'] );
		$this->assertEquals( 'https://example.com/track/ABC123', $shipments[0]['tracking_url'] );
	}

	public function test_get_shipments_skips_entries_with_empty_tracking_number() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_provider'        => 'UPS',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => '',
				'date_shipped'             => '1710288000',
			],
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => '398242362749',
				'date_shipped'             => '1710288000',
			],
		];

		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '398242362749', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_handles_zero_timestamp() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_provider'        => 'USPS',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => '9400111899223',
				'date_shipped'             => '0',
			],
		];

		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
	}

	public function test_get_shipments_handles_multiple_tracking_entries() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_provider'        => 'UPS',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => '1Z111',
				'date_shipped'             => '1710288000',
			],
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => '7890',
				'date_shipped'             => '1710374400',
			],
		];

		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 2, $shipments );
		$this->assertEquals( 'UPS', $shipments[0]['carrier_name'] );
		$this->assertEquals( 'FedEx', $shipments[1]['carrier_name'] );
	}

	public function test_get_shipments_strips_html_from_carrier_and_tracking_number() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_provider' => '<script>alert(1)</script>UPS',
				'tracking_number'   => '<b>123</b>',
				'date_shipped'      => '1710288000',
			],
		];
		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'UPS', $shipments[0]['carrier_name'] );
		$this->assertEquals( '123', $shipments[0]['tracking_number'] );
	}

	public function test_get_shipments_rejects_javascript_url_scheme() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_number'      => 'X1',
				'tracking_provider'    => 'UPS',
				'custom_tracking_link' => 'javascript:alert(1)',
				'date_shipped'         => '1710288000',
			],
		];
		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['tracking_url'], 'javascript: scheme must be stripped.' );
	}

	public function test_get_shipments_accepts_https_url() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_number'      => 'X1',
				'tracking_provider'    => 'UPS',
				'custom_tracking_link' => 'https://wwwapps.ups.com/track/?tracknum=X1',
				'date_shipped'         => '1710288000',
			],
		];
		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'https://wwwapps.ups.com/track/?tracknum=X1', $shipments[0]['tracking_url'] );
	}

	public function test_get_shipments_truncates_pathologically_long_strings() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_number'   => str_repeat( 'A', 1000 ),
				'tracking_provider' => str_repeat( 'B', 1000 ),
				'date_shipped'      => '1710288000',
			],
		];
		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertSame( 256, mb_strlen( $shipments[0]['tracking_number'] ) );
		$this->assertSame( 256, mb_strlen( $shipments[0]['carrier_name'] ) );
	}

	public function test_get_hooks_returns_expected_hooks() {
		$hooks = $this->provider->get_hooks();

		$this->assertCount( 2, $hooks );
		$this->assertEquals( 'woocommerce_shipment_tracking_added', $hooks[0]['hook'] );
		$this->assertEquals( 2, $hooks[0]['arg_count'] );
		$this->assertEquals( 'woocommerce_shipment_tracking_deleted', $hooks[1]['hook'] );
		$this->assertEquals( 2, $hooks[1]['arg_count'] );
	}
}
