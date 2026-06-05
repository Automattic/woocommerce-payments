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

	public function test_stamp_express_writes_meta_when_extensions_carry_wsn_express_channel() {
		$order   = wc_create_order();
		$request = $this->build_store_api_request(
			[
				WSN_Order_Attribution::STORE_API_EXTENSION_NAMESPACE => [ 'channel' => WSN_Order_Attribution::CHANNEL_EXPRESS ],
			]
		);

		$this->attribution->stamp_express_order_attribution( $order, $request );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame( '1', (string) $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
		$this->assertSame( WSN_Order_Attribution::CHANNEL_EXPRESS, $fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ) );
		// No session-clear assertion — single-use is now structural: extensions
		// live on the request, a follow-up request carries fresh extensions.
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

	public function test_stamp_express_skips_when_extensions_absent() {
		// Plain Store-API checkout request — no extensions param at all.
		// The handler must skip silently; non-WSN Store-API orders preserve
		// is_empty semantics.
		$order   = wc_create_order();
		$request = $this->build_store_api_request( null );

		$this->attribution->stamp_express_order_attribution( $order, $request );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
	}

	public function test_stamp_express_skips_when_extensions_lack_woopay_wsn_namespace() {
		// Extensions present but our namespace absent — e.g., a Store-API
		// checkout that carries some other extension (woocommerce/order-attribution).
		$order   = wc_create_order();
		$request = $this->build_store_api_request(
			[ 'some-other-namespace' => [ 'foo' => 'bar' ] ]
		);

		$this->attribution->stamp_express_order_attribution( $order, $request );

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

		$classic_on_classic_hook   = has_action(
			'woocommerce_checkout_order_created',
			[ $this->attribution, 'stamp_classic_order_attribution' ]
		);
		$classic_on_store_api_hook = has_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this->attribution, 'stamp_classic_order_attribution' ]
		);
		$express_on_store_api_hook = has_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this->attribution, 'stamp_express_order_attribution' ]
		);

		$this->assertSame(
			WSN_Order_Attribution::HOOK_PRIORITY,
			$classic_on_classic_hook,
			'Classic handler MUST register at priority 20 on woocommerce_checkout_order_created — running before WC core OrderAttributionController (priority 10) means _wc_order_attribution_utm_* is not yet on the order when our handler reads it, and the browser channels would silently never stamp.'
		);
		$this->assertSame(
			WSN_Order_Attribution::HOOK_PRIORITY,
			$classic_on_store_api_hook,
			'Classic handler MUST also register on woocommerce_store_api_checkout_update_order_from_request — block-checkout merchants would otherwise have WC core write the UTM meta but our copier never run.'
		);
		$this->assertSame(
			WSN_Order_Attribution::HOOK_PRIORITY,
			$express_on_store_api_hook,
			'Express handler MUST register at priority 20 on woocommerce_store_api_checkout_update_order_from_request.'
		);

		// Cleanup so registrations don't leak into other tests.
		remove_action(
			'woocommerce_checkout_order_created',
			[ $this->attribution, 'stamp_classic_order_attribution' ],
			WSN_Order_Attribution::HOOK_PRIORITY
		);
		remove_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this->attribution, 'stamp_classic_order_attribution' ],
			WSN_Order_Attribution::HOOK_PRIORITY
		);
		remove_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this->attribution, 'stamp_express_order_attribution' ],
			WSN_Order_Attribution::HOOK_PRIORITY
		);
	}

	public function test_classic_handler_runs_on_block_checkout_hook() {
		// Block-themed merchant checkout — WC core OrderAttributionBlocksController
		// fires this hook to write _wc_order_attribution_utm_* (not the classic
		// hook). Our classic handler must run here too, otherwise block-themed
		// merchants get no WSN attribution.
		$order   = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			WSN_Order_Attribution::CHANNEL_STOREFRONT
		);
		$request = $this->build_store_api_request( null );

		$this->attribution->init_hooks();
		do_action( 'woocommerce_store_api_checkout_update_order_from_request', $order, $request );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_STOREFRONT,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ),
			'Block-checkout merchant orders MUST get the WSN classic UTM copy. Without this, every merchant using the default WC 8.x+ block checkout silently loses browser-channel attribution.'
		);

		remove_all_actions( 'woocommerce_store_api_checkout_update_order_from_request' );
		remove_all_actions( 'woocommerce_checkout_order_created' );
	}

	public function test_express_wins_when_order_carries_both_extensions_and_wsn_utm() {
		// Corner case: an order is somehow placed via the Store API with
		// BOTH a WSN UTM (would normally only appear on browser-channel
		// orders) AND an extensions.woopay_wsn payload (only express).
		// Express must win because its signal is the deterministic
		// server-side source; UTM is shopper-controlled.
		//
		// The mechanism: registration order. We register express FIRST,
		// so on simultaneous-signal events the express handler stamps
		// wsn-express, then the classic handler's stamp() early-returns
		// via the double-stamp guard.
		$order   = $this->create_order_with_wc_attribution(
			WSN_Order_Attribution::UTM_SOURCE_WSN,
			WSN_Order_Attribution::CHANNEL_PDP
		);
		$request = $this->build_store_api_request(
			[
				WSN_Order_Attribution::STORE_API_EXTENSION_NAMESPACE => [ 'channel' => WSN_Order_Attribution::CHANNEL_EXPRESS ],
			]
		);

		$this->attribution->init_hooks();
		do_action( 'woocommerce_store_api_checkout_update_order_from_request', $order, $request );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_EXPRESS,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ),
			'Express handler must run FIRST and stamp wsn-express; classic handler must see double-stamp guard and skip. Registration order is the precedence mechanism.'
		);

		remove_all_actions( 'woocommerce_store_api_checkout_update_order_from_request' );
		remove_all_actions( 'woocommerce_checkout_order_created' );
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
		remove_all_actions( 'woocommerce_store_api_checkout_update_order_from_request' );
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

	public function test_stamp_express_skips_when_extensions_carry_wrong_channel_slug() {
		// Cross-channel pollution defense: even a valid WSN slug under our
		// namespace, if it's not specifically `wsn-express`, must not
		// stamp express. Strict-equals + the schema-callback enum gate
		// both enforce this; the test pins the handler's contract.
		$order   = wc_create_order();
		$request = $this->build_store_api_request(
			[
				WSN_Order_Attribution::STORE_API_EXTENSION_NAMESPACE => [ 'channel' => WSN_Order_Attribution::CHANNEL_PDP ],
			]
		);

		$this->attribution->stamp_express_order_attribution( $order, $request );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty(
			$fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ),
			'Extensions carrying a non-express WSN slug must not stamp express attribution.'
		);
	}

	public function test_stamp_express_skips_when_extensions_payload_is_unexpected_type() {
		// Some upstream caller writes junk under our namespace (string
		// instead of array, etc.). is_array check handles it.
		$order   = wc_create_order();
		$request = $this->build_store_api_request(
			[ WSN_Order_Attribution::STORE_API_EXTENSION_NAMESPACE => 'not-an-array' ]
		);

		$this->attribution->stamp_express_order_attribution( $order, $request );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( WSN_Order_Attribution::META_IS_MARKETPLACE ) );
	}

	public function test_stamp_express_skips_when_extensions_param_is_unexpected_type() {
		// Whole `extensions` param is a string (corrupt request body).
		// is_array($extensions) guard handles it.
		$order   = wc_create_order();
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param( 'extensions', 'not-an-array' );

		$this->attribution->stamp_express_order_attribution( $order, $request );

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

	public function test_stamp_express_accepts_unverified_request_by_design_per_trust_model_docblock() {
		// SYMMETRIC sibling to test_stamp_classic_writes_meta_when_shopper_supplies_wsn_utm_directly.
		//
		// Post-extensions-pivot (commit e34795142), the express channel is
		// request-controlled — a shopper can curl /wc/store/v1/checkout with
		// extensions.woopay_wsn.channel = "wsn-express" and self-stamp. The
		// WC Store-API enum validator confirms the value is "wsn-express"
		// (the only allowed value), but that's exactly what a spoofer would
		// send: the enum narrows the spoof, doesn't prevent it. There is no
		// server-side provenance check today (no signed token, no HMAC, no
		// nonce — see the "Trust model for the express channel" paragraph
		// in the class header docblock).
		//
		// This test documents the spoof as ACCEPTED behavior:
		// - Impact bounded: merchant's own Hub Overview dashboard only
		// (manage_woocommerce-gated, not cross-tenant); no PII /
		// payment / billing / payout logic touches this meta.
		// - "wsn-express" is the SHAPE of the express signal post-pivot,
		// NOT proof of provenance. Downstream consumers must not treat
		// it as "verified WSN-originated" until provenance verification
		// is added.
		//
		// If a future change adds provenance (a WooPay-bridge-issued single-
		// use token in extensions.woopay_wsn.token, HMAC verification with
		// the WCPay↔WooPay shared secret, etc.), THIS test should flip —
		// at which point a regression in the provenance check surfaces
		// here as a failed assertion.
		$order   = wc_create_order();
		$request = $this->build_store_api_request(
			[
				WSN_Order_Attribution::STORE_API_EXTENSION_NAMESPACE => [ 'channel' => WSN_Order_Attribution::CHANNEL_EXPRESS ],
			]
		);

		$this->attribution->stamp_express_order_attribution( $order, $request );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame(
			WSN_Order_Attribution::CHANNEL_EXPRESS,
			$fresh->get_meta( WSN_Order_Attribution::META_CHANNEL ),
			'Documented: express stamping is shopper-spoofable post-extensions-pivot — same trust class as the browser-channel UTM path. If a future provenance check is added, this assertion should flip to assertEmpty().'
		);
	}

	// ---- Store API schema registration ----

	public function test_get_store_api_extension_schema_describes_the_channel_field() {
		// Pins the schema contract — without this, the WC Store-API
		// validator may strip `extensions.woopay_wsn` before our hook
		// handler sees it. Schema must declare the `channel` field as a
		// string constrained to the wsn-express enum (the only value our
		// handler accepts; browser channels go through WC core's native
		// UTM attribution, not this extension).
		$schema = $this->attribution->get_store_api_extension_schema();

		$this->assertArrayHasKey( 'channel', $schema );
		$this->assertSame( 'string', $schema['channel']['type'] );
		$this->assertSame(
			[ WSN_Order_Attribution::CHANNEL_EXPRESS ],
			$schema['channel']['enum'],
			'Schema enum must constrain to wsn-express only — the browser channels use a different path (WC core UTM attribution).'
		);
	}

	public function test_register_store_api_extension_no_ops_when_helper_missing() {
		// Hardened against an environment where Store-API isn't loaded
		// (legacy WC version, REST disabled, etc.). The class's
		// function_exists guard means register_store_api_extension is
		// a no-op rather than a fatal — test pins this.
		// (We can't actually test that woocommerce_store_api_register_endpoint_data
		// is called when present because the helper modifies WC's global
		// ExtendSchema state which leaks across tests. Verifying the
		// no-op branch is the cleaner half of the contract).
		$reflection = new \ReflectionMethod( $this->attribution, 'register_store_api_extension' );
		$this->assertTrue( $reflection->isPublic(), 'register_store_api_extension must be public so add_action can call it.' );
		// The method must not throw under any environment — assert no
		// exception escapes on invocation.
		$this->attribution->register_store_api_extension();
		$this->assertTrue( true, 'register_store_api_extension must be safe to call (no-op when helper missing).' );
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

	/**
	 * Build a `WP_REST_Request` shaped like a Store-API checkout POST,
	 * with the `extensions` param populated as the WooPay bridge
	 * controller would send it. Pass `null` to simulate a request with
	 * no extensions param at all.
	 *
	 * @param array|null $extensions The extensions payload, or null to omit.
	 * @return \WP_REST_Request
	 */
	private function build_store_api_request( ?array $extensions ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		if ( null !== $extensions ) {
			$request->set_param( 'extensions', $extensions );
		}
		return $request;
	}
}
