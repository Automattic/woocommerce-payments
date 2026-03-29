<?php
/**
 * Class WooPay_Shipment_Tracking_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

declare( strict_types=1 );

use WCPay\WooPay\Tracking_Providers\WooPay_Shipment_Tracking_Provider;

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

	public function test_is_available_returns_false_when_no_tracking_plugin_class_exists() {
		$order = WC_Helper_Order::create_order();

		// Neither WC_Shipment_Tracking_Actions nor WC_Advanced_Shipment_Tracking_Actions exists
		// in the test environment, so is_available should return false.
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

	public function test_get_hooks_returns_expected_hooks() {
		$hooks = $this->provider->get_hooks();

		$this->assertCount( 2, $hooks );
		$this->assertEquals( 'woocommerce_shipment_tracking_added', $hooks[0]['hook'] );
		$this->assertEquals( 2, $hooks[0]['arg_count'] );
		$this->assertEquals( 'woocommerce_shipment_tracking_deleted', $hooks[1]['hook'] );
		$this->assertEquals( 2, $hooks[1]['arg_count'] );
	}
}
