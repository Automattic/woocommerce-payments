<?php
/**
 * Class WSN_Order_Attribution_Test
 *
 * @package WooCommerce\Payments\WSN
 */

/**
 * Unit tests for WSN_Order_Attribution — the write side of the
 * marketplace order-attribution meta the Hub Overview tab reads.
 *
 * Coverage matrix (in order of business risk):
 *
 *  1. **All 4 channels happy path** — each channel's signal correctly
 *     produces both meta keys with the right channel slug.
 *
 *  2. **`is_empty` boundary** — non-WSN orders (wrong UTM source,
 *     unrecognized UTM content, missing session flag) MUST NOT get
 *     stamped. The read endpoint's `is_empty: true` for non-WSN
 *     traffic relies on this.
 *
 *  3. **Hook priority** — registered at priority 20, AFTER WC core's
 *     `OrderAttributionController` (priority 10). A regression here
 *     means the referral / browser channels silently never stamp
 *     because the UTM meta isn't on the order yet when our handler
 *     reads it.
 *
 *  4. **Meta-key contract** — `WSN_Order_Attribution::META_*` constants
 *     MUST equal the orders-controller's `META_*` constants. Drift
 *     here means writes go to one key, reads look for another — and
 *     the dashboard stays `is_empty` silently.
 *
 *  5. **Single-use express session flag** — cleared after stamping so
 *     a follow-up non-WSN order in the same session isn't misattributed.
 *
 *  6. **Double-stamp guard** — if both hooks fire for the same order
 *     (shouldn't happen — paths are mutually exclusive), the channel
 *     value doesn't get overwritten.
 */
class WSN_Order_Attribution_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WSN_Order_Attribution
	 */
	private $attribution;

	public function set_up() {
		parent::set_up();
		$this->attribution = new WSN_Order_Attribution();

		// Defensive — clear any session flag bleed from prior tests.
		if ( WC()->session ) {
			WC()->session->set( WSN_Order_Attribution::SESSION_KEY_EXPRESS_CHANNEL, null );
		}
	}

	public function tear_down() {
		if ( WC()->session ) {
			WC()->session->set( WSN_Order_Attribution::SESSION_KEY_EXPRESS_CHANNEL, null );
		}
		parent::tear_down();
	}

	// ---- Channel happy paths ----

	public function test_stamp_classic_writes_meta_for_wsn_pdp() {
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			WSN_Order_Attribution::CHANNEL_PDP
		);

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame( '1', (string) $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
		$this->assertSame( WSN_Order_Attribution::CHANNEL_PDP, $fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ) );
	}

	public function test_stamp_classic_writes_meta_for_wsn_storefront() {
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			WSN_Order_Attribution::CHANNEL_STOREFRONT
		);

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame( WSN_Order_Attribution::CHANNEL_STOREFRONT, $fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ) );
	}

	public function test_stamp_classic_writes_meta_for_wsn_cart() {
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			WSN_Order_Attribution::CHANNEL_CART
		);

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame( WSN_Order_Attribution::CHANNEL_CART, $fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ) );
	}

	public function test_stamp_express_writes_meta_when_session_flag_set() {
		$this->assertNotNull( WC()->session, 'Test prerequisite: WC()->session must be initialized.' );
		WC()->session->set(
			WSN_Order_Attribution::SESSION_KEY_EXPRESS_CHANNEL,
			WSN_Order_Attribution::CHANNEL_EXPRESS
		);
		$order = wc_create_order();

		$this->attribution->stamp_express_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame( '1', (string) $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
		$this->assertSame( WSN_Order_Attribution::CHANNEL_EXPRESS, $fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ) );
		$this->assertEmpty(
			WC()->session->get( WSN_Order_Attribution::SESSION_KEY_EXPRESS_CHANNEL ),
			'Single-use: the express session flag MUST be cleared after stamping so a follow-up non-WSN order in the same session is not misattributed.'
		);
	}

	// ---- is_empty boundary ----

	public function test_stamp_classic_skips_when_utm_source_not_wsn() {
		$order = $this->create_order_with_wc_attribution( 'google', WSN_Order_Attribution::CHANNEL_PDP );

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty(
			$fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ),
			'Non-WSN utm_source MUST NOT stamp marketplace meta — preserves is_empty for non-WSN traffic.'
		);
	}

	public function test_stamp_classic_skips_when_utm_content_unrecognized() {
		// Future channel added on WSN client side but not yet in this class's BROWSER_CHANNELS list.
		// Defense in depth — we'd rather silently skip than mis-label.
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			'wsn-some-future-surface'
		);

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
	}

	public function test_stamp_classic_skips_when_no_utm_meta() {
		// Boundary: an order placed via classic checkout with no UTM at all
		// (organic merchant visit) must NOT be stamped.
		$order = wc_create_order();

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
	}

	public function test_stamp_express_skips_when_session_flag_missing() {
		$this->assertNotNull( WC()->session );
		// Confirm tear_down/set_up cleared the flag from a previous test.
		WC()->session->set( WSN_Order_Attribution::SESSION_KEY_EXPRESS_CHANNEL, null );
		$order = wc_create_order();

		$this->attribution->stamp_express_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
	}

	// ---- Double-stamp guard ----

	public function test_stamp_does_not_overwrite_when_meta_already_present() {
		// Pre-stamp the order with one channel; then fire a handler with a different
		// signal. The guard prevents the channel from flipping mid-flight.
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			WSN_Order_Attribution::CHANNEL_PDP
		);
		$order->update_meta_data( WSN_Order_Attribution::META_IS_MARKETPLACE, true );
		$order->update_meta_data( WSN_Order_Attribution::META_CHANNEL, WSN_Order_Attribution::CHANNEL_CART );
		$order->save();

		// Fire the classic handler — it would otherwise overwrite to wsn-pdp.
		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_CART,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ),
			'When meta is already present, stamp() must NOT overwrite — keeps the channel stable if the mutually-exclusive-paths invariant ever breaks.'
		);
	}

	// ---- Hook registration + priority ----

	public function test_init_hooks_registers_at_priority_20() {
		$this->attribution->init_hooks();

		$classic_priority = has_action(
			'woocommerce_checkout_order_created',
			[ $this->attribution, 'stamp_classic_order_attribution' ]
		);
		$express_priority = has_action(
			'woocommerce_store_api_checkout_order_processed',
			[ $this->attribution, 'stamp_express_order_attribution' ]
		);

		$this->assertSame(
			WSN_Order_Attribution::HOOK_PRIORITY,
			$classic_priority,
			'Classic hook MUST register at priority 20 — running before WC core OrderAttributionController (priority 10) means _wc_order_attribution_utm_* is not yet on the order when our handler reads it, and the referral / browser channels would silently never stamp.'
		);
		$this->assertSame(
			WSN_Order_Attribution::HOOK_PRIORITY,
			$express_priority,
			'Express hook MUST also register at priority 20 for consistency.'
		);

		// Cleanup so this registration doesn't leak into other tests.
		remove_action(
			'woocommerce_checkout_order_created',
			[ $this->attribution, 'stamp_classic_order_attribution' ],
			WSN_Order_Attribution::HOOK_PRIORITY
		);
		remove_action(
			'woocommerce_store_api_checkout_order_processed',
			[ $this->attribution, 'stamp_express_order_attribution' ],
			WSN_Order_Attribution::HOOK_PRIORITY
		);
	}

	// ---- Meta-key contract with the read side ----

	public function test_meta_key_constants_match_orders_controller() {
		// Load-bearing — if these drift, writes land in one key and reads
		// look in another, and the Hub Overview tab stays `is_empty` silently.
		$this->assertSame(
			WSN_Order_Attribution::META_IS_MARKETPLACE,
			WC_REST_Payments_WSN_Orders_Controller::META_IS_MARKETPLACE,
			'Marketplace meta key drift between writer and reader. The Overview tab will never light up.'
		);
		$this->assertSame(
			WSN_Order_Attribution::META_CHANNEL,
			WC_REST_Payments_WSN_Orders_Controller::META_CHANNEL,
			'Channel meta key drift between writer and reader. The Overview tab will never light up.'
		);
	}

	// ---- Helpers ----

	/**
	 * Build a test order with WC core's order-attribution meta pre-populated
	 * (simulating the post-priority-10-handler state our priority-20 handler
	 * reads).
	 *
	 * @param string $utm_source  Value for `_wc_order_attribution_utm_source`.
	 * @param string $utm_content Value for `_wc_order_attribution_utm_content`.
	 * @return \WC_Order
	 */
	private function create_order_with_wc_attribution( string $utm_source, string $utm_content ): \WC_Order {
		$order = wc_create_order();
		$order->update_meta_data( '_wc_order_attribution_utm_source', $utm_source );
		$order->update_meta_data( '_wc_order_attribution_utm_content', $utm_content );
		$order->save();
		return $order;
	}
}
