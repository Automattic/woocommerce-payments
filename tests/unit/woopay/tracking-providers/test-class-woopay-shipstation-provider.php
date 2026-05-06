<?php
/**
 * Class WooPay_ShipStation_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_ShipStation_Provider;

require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/interface-woopay-tracking-provider.php';
require_once __DIR__ . '/../../../../includes/woopay/tracking-providers/class-woopay-shipstation-provider.php';

// Load the ShipStation detection stub. This simulates the plugin being active.
// WC_Shipment_Tracking is intentionally NOT loaded here: once that stub is
// required it persists for the whole PHPUnit process and would break
// is_available() for every subsequent test. The "WC ST present → returns false"
// exclusion branch is covered in WooPay_Order_Tracking_Sync_Test instead,
// which can inject mock providers without class pollution.
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

	public function test_is_available_returns_true_when_shipstation_standalone_with_tracking_number() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_tracking_number', '9400111899223397865584' );
		$order->save();

		$this->assertTrue( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_no_tracking_number_meta() {
		$order = WC_Helper_Order::create_order();
		// No _tracking_number meta.

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	public function test_is_available_returns_false_when_tracking_number_is_empty_string() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_tracking_number', '' );
		$order->save();

		$this->assertFalse( $this->provider->is_available( $order ) );
	}

	// -------------------------------------------------------------------------
	// get_shipments()
	// -------------------------------------------------------------------------

	public function test_get_shipments_returns_empty_when_no_tracking_number_meta() {
		$order = WC_Helper_Order::create_order();

		$this->assertEmpty( $this->provider->get_shipments( $order ) );
	}

	public function test_get_shipments_normalizes_tracking_data() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_tracking_number', '9400111899223397865584' );
		$order->update_meta_data( '_tracking_provider', 'usps' );
		$order->update_meta_data( '_date_shipped', '1710288000' ); // 2024-03-13.
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

	public function test_get_shipments_returns_empty_date_shipped_when_date_meta_absent() {
		// Modern standalone ShipStation does not write _date_shipped.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_tracking_number', '1Z999AA10123456784' );
		$order->update_meta_data( '_tracking_provider', 'ups' );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
	}

	public function test_get_shipments_returns_empty_date_shipped_when_timestamp_is_zero() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_tracking_number', '1Z999AA10123456784' );
		$order->update_meta_data( '_date_shipped', '0' );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '', $shipments[0]['date_shipped'] );
	}

	public function test_get_shipments_strips_html_from_carrier_and_tracking_number() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_tracking_number', '<b>1Z999</b>' );
		$order->update_meta_data( '_tracking_provider', '<script>alert(1)</script>ups' );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '1Z999', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'ups', $shipments[0]['carrier_name'] );
	}

	public function test_get_shipments_skips_when_tracking_number_sanitizes_to_empty() {
		$order = WC_Helper_Order::create_order();
		// Tags-only value strips to '' after sanitization.
		$order->update_meta_data( '_tracking_number', '<script></script>' );
		$order->save();

		$this->assertEmpty( $this->provider->get_shipments( $order ) );
	}

	public function test_get_shipments_truncates_pathologically_long_strings() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_tracking_number', str_repeat( 'A', 1000 ) );
		$order->update_meta_data( '_tracking_provider', str_repeat( 'B', 1000 ) );
		$order->save();

		$shipments = $this->provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertSame( 256, mb_strlen( $shipments[0]['tracking_number'] ) );
		$this->assertSame( 256, mb_strlen( $shipments[0]['carrier_name'] ) );
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
