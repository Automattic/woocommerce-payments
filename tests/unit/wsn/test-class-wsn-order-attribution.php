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
		// assertNull (not assertEmpty) — the class explicitly writes null
		// when clearing the flag; assertEmpty would also pass for '' or 0
		// which would mask a bug where the clear writes something other
		// than null.
		$this->assertNull(
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

	// ---- Sub-flag wireup gate ----

	public function test_sub_flag_helper_returns_true_when_option_is_one() {
		// Direct contract test for is_wsn_order_attribution_enabled() —
		// load-bearing because the wireup in WC_Payments::init() gates the
		// class instantiation on this value. A typo / inverted check would
		// silently turn the writer fully on or off across the fleet.
		update_option( WC_Payments_Features::WSN_ORDER_ATTRIBUTION_FLAG_NAME, '1' );
		$this->assertTrue( WC_Payments_Features::is_wsn_order_attribution_enabled() );
		delete_option( WC_Payments_Features::WSN_ORDER_ATTRIBUTION_FLAG_NAME );
	}

	public function test_sub_flag_helper_returns_false_when_option_is_zero() {
		update_option( WC_Payments_Features::WSN_ORDER_ATTRIBUTION_FLAG_NAME, '0' );
		$this->assertFalse( WC_Payments_Features::is_wsn_order_attribution_enabled() );
		delete_option( WC_Payments_Features::WSN_ORDER_ATTRIBUTION_FLAG_NAME );
	}

	public function test_sub_flag_helper_returns_false_by_default() {
		// No option set — default-off invariant. Critical for dark-ship
		// rollout: if this returned true by default, every WCPay install
		// would silently begin stamping orders at the next deploy.
		delete_option( WC_Payments_Features::WSN_ORDER_ATTRIBUTION_FLAG_NAME );
		$this->assertFalse( WC_Payments_Features::is_wsn_order_attribution_enabled() );
	}

	public function test_sub_flag_constant_has_expected_value() {
		// Pins the option-key string. If this constant gets renamed
		// without updating the wireup in class-wc-payments.php (or vice
		// versa), the option that's set via wp-cli won't match the option
		// the code reads — the gate would default-off silently.
		$this->assertSame(
			'_wcpay_feature_wsn_order_attribution',
			WC_Payments_Features::WSN_ORDER_ATTRIBUTION_FLAG_NAME
		);
	}

	// ---- Behavioral priority test ----

	public function test_handler_runs_after_priority_10_handler_in_action_chain() {
		// Behavioral counterpart to test_init_hooks_registers_at_priority_20.
		// That test asserts the literal priority value; a regression to e.g.
		// HOOK_PRIORITY=5 (below WC core's OrderAttributionController at 10)
		// would still pass if someone bumped the assertion too. This test
		// proves the priority constraint behaviorally: register a priority-10
		// handler that writes the UTM meta (simulating WC core's behavior),
		// register our priority-20 handler, fire the action, and assert our
		// handler saw the UTM. If our handler runs BEFORE priority 10, the
		// UTM isn't there and the marketplace meta is never written.

		$order = wc_create_order(); // No UTM meta yet — bare order.

		// Simulate WC core's OrderAttributionController at default priority 10.
		add_action(
			'woocommerce_checkout_order_created',
			function ( \WC_Order $stamped ) {
				$stamped->update_meta_data(
					'_wc_order_attribution_utm_source',
					WSN_Order_Attribution::UTM_SOURCE_WSN
				);
				$stamped->update_meta_data(
					'_wc_order_attribution_utm_content',
					WSN_Order_Attribution::CHANNEL_PDP
				);
				$stamped->save_meta_data();
			},
			10, // Matches WC core's OrderAttributionController priority.
			1
		);

		$this->attribution->init_hooks();

		do_action( 'woocommerce_checkout_order_created', $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_PDP,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ),
			'Our handler must run AFTER priority 10 — otherwise the UTM meta is not on the order when we read it, and the marketplace meta is never written. A regression to HOOK_PRIORITY <= 10 surfaces here.'
		);

		// Cleanup so the simulated WC-core handler + our handler don't
		// leak into subsequent tests.
		remove_all_actions( 'woocommerce_checkout_order_created' );
		remove_all_actions( 'woocommerce_store_api_checkout_order_processed' );
	}

	// ---- sanitize_key boundary ----

	public function test_stamp_classic_normalizes_uppercase_utm_content_via_sanitize_key() {
		// sanitize_key() lowercases — a misbehaving UTM source that
		// emitted `WSN-PDP` should still match the whitelist after
		// normalization. Pins the implementation choice; a refactor that
		// dropped sanitize_key() for trim() would silently change this.
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			'WSN-PDP'
		);

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_PDP,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL )
		);
	}

	public function test_stamp_classic_strips_disallowed_characters_via_sanitize_key() {
		// sanitize_key() strips characters outside [a-z0-9_-]. A trailing
		// slash gets removed, but the result `wsn-pdp` matches the
		// whitelist exactly — by design (sanitize_key normalizes so
		// minor adversarial variants land in the whitelist). Pins this
		// established behavior.
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			'wsn-pdp/'
		);

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_PDP,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ),
			'sanitize_key strips the slash; the result wsn-pdp matches the whitelist.'
		);
	}

	// ---- Wrong/non-string express session value ----

	public function test_stamp_express_skips_when_session_carries_wrong_channel_slug() {
		// Cross-channel pollution defense: even a valid WSN slug in the
		// session, if it's not specifically `wsn-express`, must not
		// stamp express. Strict-equals in the handler handles this; the
		// test pins the contract.
		WC()->session->set(
			WSN_Order_Attribution::SESSION_KEY_EXPRESS_CHANNEL,
			WSN_Order_Attribution::CHANNEL_PDP
		);
		$order = wc_create_order();

		$this->attribution->stamp_express_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty(
			$fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ),
			'Session containing a non-express WSN slug must not stamp express attribution.'
		);
	}

	public function test_stamp_express_skips_when_session_value_is_unexpected_type() {
		// Some upstream caller (test or bridge bug) writes junk to the
		// session key. Strict-equals handles arrays/objects/ints/null —
		// they all !== the expected string. Pin the contract.
		WC()->session->set( WSN_Order_Attribution::SESSION_KEY_EXPRESS_CHANNEL, [ 'wsn-express' ] );
		$order = wc_create_order();

		$this->attribution->stamp_express_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
	}

	// ---- Customer-spoofable UTM (documented design decision) ----

	public function test_stamp_classic_writes_meta_when_shopper_supplies_wsn_utm_directly() {
		// This test documents an ACCEPTED behavior, not a defended one.
		// WC core's OrderAttributionController writes UTM meta from
		// shopper-controlled form fields per the standard design — so a
		// shopper can self-stamp a non-WSN order with WSN attribution by
		// submitting the WSN UTM values directly on checkout. The bounded
		// impact (merchant's own admin-only Hub stat noise; no PII /
		// payment / billing / cross-tenant) is acceptable; the class
		// docblock documents this trust-model decision.
		//
		// If a future requirement adds real provenance (e.g., a signed
		// referrer cookie WCPay-side or a server-verified handoff token),
		// THIS test should flip — at which point a regression in the
		// provenance check would surface here as a failed assertion.
		$order = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			WSN_Order_Attribution::CHANNEL_PDP
		);

		$this->attribution->stamp_classic_order_attribution( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_PDP,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ),
			'Documented: browser-channel stamping inherits WC core Order Attribution trust model — shopper-spoofable by design, bounded to dashboard noise on the merchant own admin-only view.'
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
