<?php
/**
 * Class WSN_Order_Attribution
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes the marketplace order-attribution meta the Hub Overview tab
 * reads — `_woopay_marketplace_order` + `_woopay_marketplace_channel` —
 * at order creation time on both classic and Store-API checkout paths.
 *
 * **Four channels (LOCKED 2026-06-04):**
 *
 *   - `wsn-pdp`        — PDP "View on / Buy on merchant" link (browser)
 *   - `wsn-storefront` — Storefront "Visit website" link (browser)
 *   - `wsn-cart`       — Cart/drawer "Checkout on merchant" redirect (browser)
 *   - `wsn-express`    — Headless WooPay express checkout
 *
 * **Capture mechanism:**
 *
 * The three browser channels are real navigations to the merchant's own
 * domain — WC core's Order Attribution captures the UTM the WSN client
 * emits (`utm_source=woo-shopping-network`,
 * `utm_content=wsn-{pdp|storefront|cart}`) and writes the standard
 * `_wc_order_attribution_utm_*` order meta. This class then copies that
 * into the WSN-owned namespace so the Hub Overview tab reads one stable
 * key (and stays insensitive to non-WSN UTM noise).
 *
 * The browser-channel copier hooks BOTH checkout paths because WC has
 * two of them and they're mutually exclusive for any given order:
 *
 *   - `woocommerce_checkout_order_created` — classic (shortcode-based)
 *     checkout. The original WC checkout page; PHP form submission.
 *     WC core's `OrderAttributionController` writes the UTM meta here
 *     at priority 10.
 *
 *   - `woocommerce_store_api_checkout_update_order_from_request` —
 *     block-based checkout (default in WC 8.x+). Submits via JavaScript
 *     to the `/wc/store/v1/checkout` REST endpoint. WC core's
 *     `OrderAttributionBlocksController` writes the UTM meta here.
 *     Without hooking this path, a merchant on a block theme silently
 *     never gets WSN attribution.
 *
 * The express channel is headless — UTM is structurally blind to it,
 * because the checkout completes off the merchant domain. The WooPay
 * `wsn-checkout` bridge controller (Alefe's `woopay-wsn-bridge-endpoint`
 * branch) sends `extensions.woopay_wsn.channel = "wsn-express"` in the
 * single Store-API checkout POST body. This class reads the value from
 * the request via `$request->get_param('extensions')` inside the
 * `woocommerce_store_api_checkout_update_order_from_request` hook —
 * matching the canonical Store-API extension pattern WC core uses for
 * its own block-checkout Order Attribution (see
 * `OrderAttributionBlocksController::extend_api()`). Single-use by
 * construction: a fresh request → a fresh extensions payload, no
 * cleanup needed.
 *
 * Schema registration: the `woopay_wsn` namespace is declared via
 * `woocommerce_store_api_register_endpoint_data()` on
 * `woocommerce_blocks_loaded`. Without registration WC's Store-API
 * schema validator may strip unknown extension namespaces before our
 * hook handler sees them.
 *
 * **Trust model for the browser channels:** the underlying
 * `_wc_order_attribution_utm_*` meta is shopper-controlled (WC core
 * writes it from the shopper's checkout form fields per the standard
 * Order Attribution design). A shopper can therefore self-stamp a
 * non-WSN order with WSN attribution by submitting the WSN UTM values
 * directly. **Impact is bounded:** the only consumer is the
 * merchant's own Hub Overview dashboard (gated by `manage_woocommerce`,
 * not exposed cross-tenant); meta values are whitelisted to four
 * known slugs; no PII, payment, billing, or payout logic touches this.
 * Inherits the same trust model WC core applies to its own Order
 * Attribution writes — accepted by design.
 *
 * **Hook priority constraint:** WC core's `OrderAttributionController`
 * hooks `woocommerce_checkout_order_created` at default priority 10.
 * Our handler MUST run at priority 20+ so the
 * `_wc_order_attribution_utm_*` meta is already on the order when
 * `stamp_classic_order_attribution()` reads it. A regression here would
 * mean the referral / browser channels silently never stamp.
 *
 * **Wireup:** instantiated in `WC_Payments::init()` (NOT
 * `WSN_Hub::init_hooks()`), because checkout hooks fire on shopper-side
 * requests — the Hub's wireup only runs on Hub admin requests.
 *
 * Owned by RSM-3945 (sibling write-side to the WSN Profile sync).
 */
class WSN_Order_Attribution {

	/**
	 * Boolean marker — "this order originated through the Network."
	 * The Hub Overview tab's read endpoint gates aggregation on this key.
	 *
	 * @var string
	 */
	const META_IS_MARKETPLACE = '_woopay_marketplace_order';

	/**
	 * Channel slug — one of the four CHANNEL_* constants. Tells the
	 * Overview tab which surface the order originated from.
	 *
	 * @var string
	 */
	const META_CHANNEL = '_woopay_marketplace_channel';

	const CHANNEL_PDP        = 'wsn-pdp';
	const CHANNEL_STOREFRONT = 'wsn-storefront';
	const CHANNEL_CART       = 'wsn-cart';
	const CHANNEL_EXPRESS    = 'wsn-express';

	/**
	 * The three browser channels — surfaces where the WSN client emits
	 * `utm_content=wsn-<slug>` and WC core's Order Attribution captures
	 * it natively. Used to whitelist what we copy from
	 * `_wc_order_attribution_utm_content` (defends against future channel
	 * additions silently leaking through if the WSN client adds a new
	 * surface before this class is updated).
	 *
	 * @var string[]
	 */
	const BROWSER_CHANNELS = [
		self::CHANNEL_PDP,
		self::CHANNEL_STOREFRONT,
		self::CHANNEL_CART,
	];

	/**
	 * The UTM source value the WSN client always emits on
	 * merchant-domain links. Used as the WSN-vs-everything-else gate
	 * for the browser channels.
	 *
	 * @var string
	 */
	const UTM_SOURCE_WSN = 'woo-shopping-network';

	/**
	 * Store API extension namespace under which WooPay's bridge sends
	 * the WSN origin signal — read as
	 * `$request->get_param('extensions')['woopay_wsn']['channel']`. Used
	 * both at schema-registration time and at request-read time so the
	 * two halves can't drift.
	 *
	 * @var string
	 */
	const STORE_API_EXTENSION_NAMESPACE = 'woopay_wsn';

	/**
	 * Hook priority — MUST be greater than 10 so WC core's
	 * `OrderAttributionController::set_order_attribution_data` (registered
	 * at default priority 10) has already written
	 * `_wc_order_attribution_utm_*` to the order by the time our handler
	 * reads it. 20 leaves room for other consumers between us and core.
	 *
	 * @var int
	 */
	const HOOK_PRIORITY = 20;

	/**
	 * Register the checkout hooks + the Store API schema declaration.
	 * Idempotent — `add_action` dedupes the same (hook, callback, priority)
	 * triple.
	 *
	 * Registration order matters: the express handler is registered
	 * FIRST on `woocommerce_store_api_checkout_update_order_from_request`.
	 * Both handlers run at the same priority (20), and WP fires
	 * same-priority callbacks in FIFO registration order. So on the
	 * (rare) corner case of a Store-API order carrying BOTH a
	 * `extensions.woopay_wsn` payload AND a WSN UTM, the express
	 * handler stamps `wsn-express` first and the classic handler's
	 * `stamp()` early-returns via the double-stamp guard. Express wins
	 * because it's the deterministic source (server-side extension),
	 * UTM is shopper-controlled.
	 *
	 * The classic handler hooks BOTH `woocommerce_checkout_order_created`
	 * (classic checkout) AND `woocommerce_store_api_checkout_update_order_from_request`
	 * (block checkout) because WC has two mutually-exclusive checkout
	 * paths and WC core's own Order Attribution has two handlers for
	 * the same reason. Hooking only the classic path would silently
	 * drop attribution on every block-themed merchant store.
	 */
	public function init_hooks(): void {
		// Express FIRST so it wins on simultaneous-signal corner cases
		// (see class docblock + registration-order rationale above).
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this, 'stamp_express_order_attribution' ],
			self::HOOK_PRIORITY,
			2
		);

		add_action(
			'woocommerce_checkout_order_created',
			[ $this, 'stamp_classic_order_attribution' ],
			self::HOOK_PRIORITY,
			1
		);
		// Same handler, second hook — block-checkout coverage. The
		// handler reads UTM order meta WC core writes; the request arg
		// is unused, so the same 1-arg signature is fine on both hooks
		// (WP only passes the number of args declared via $accepted_args).
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this, 'stamp_classic_order_attribution' ],
			self::HOOK_PRIORITY,
			1
		);

		add_action(
			'woocommerce_blocks_loaded',
			[ $this, 'register_store_api_extension' ]
		);
	}

	/**
	 * Declare the `woopay_wsn` extension namespace on the Store API
	 * checkout endpoint so requests carrying `extensions.woopay_wsn`
	 * survive schema validation and surface on the hook's request arg.
	 *
	 * Without registration, WC's Store-API schema validator may strip
	 * unknown extension namespaces before our hook handler sees them.
	 * Mirrors the pattern at
	 * `WooCommerce\Internal\Orders\OrderAttributionBlocksController::extend_api()`.
	 */
	public function register_store_api_extension(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => 'checkout',
				'namespace'       => self::STORE_API_EXTENSION_NAMESPACE,
				'schema_callback' => [ $this, 'get_store_api_extension_schema' ],
			]
		);
	}

	/**
	 * Schema callback for the `woopay_wsn` extension. Tells WC's
	 * Store-API validator that the payload is an object carrying a
	 * single `channel` string field. The enum constrains the accepted
	 * value to `wsn-express` — the only channel that needs the express
	 * extension path (browser channels are captured via WC core's
	 * native UTM attribution, not this extension).
	 *
	 * @return array
	 */
	public function get_store_api_extension_schema(): array {
		return [
			'channel' => [
				'description' => __( 'WSN origin channel for headless WooPay-express checkouts.', 'woocommerce-payments' ),
				'type'        => 'string',
				'enum'        => [ self::CHANNEL_EXPRESS ],
				'readonly'    => false,
			],
		];
	}

	/**
	 * Classic merchant checkout — copy WC's native UTM attribution into
	 * the WSN-namespace meta when the source matches our brand and the
	 * content slug is one of the recognized browser channels.
	 *
	 * Skips silently when:
	 *  - WC core hasn't populated the UTM meta (priority bug — see class docblock)
	 *  - UTM source is something other than `woo-shopping-network`
	 *  - UTM content is unrecognized (future channel added on WSN client side
	 *    before this constant list is updated — defense in depth)
	 *
	 * @param \WC_Order $order The order being created.
	 */
	public function stamp_classic_order_attribution( \WC_Order $order ): void {
		$utm_source = (string) $order->get_meta( '_wc_order_attribution_utm_source' );
		if ( self::UTM_SOURCE_WSN !== $utm_source ) {
			return;
		}

		$channel = sanitize_key( (string) $order->get_meta( '_wc_order_attribution_utm_content' ) );
		if ( ! in_array( $channel, self::BROWSER_CHANNELS, true ) ) {
			return;
		}

		$this->stamp( $order, $channel );
	}

	/**
	 * Headless WooPay express checkout — read the channel slug from the
	 * Store API request's `extensions.woopay_wsn.channel` field, set by
	 * the WooPay bridge controller on the single checkout POST.
	 *
	 * Single-use by construction: extensions live on the request, not
	 * on shared session state, so a follow-up non-WSN checkout in the
	 * same session can't inherit attribution.
	 *
	 * Skips silently when:
	 *  - The extensions param is absent or non-array (no WSN signal)
	 *  - The woopay_wsn namespace is absent (non-WSN Store-API checkout)
	 *  - The channel value isn't `wsn-express` (only value this path
	 *    accepts; the browser channels go through WC core's native
	 *    UTM attribution on the classic hook)
	 *
	 * @param \WC_Order        $order   The order being placed.
	 * @param \WP_REST_Request $request The Store-API checkout request.
	 */
	public function stamp_express_order_attribution( \WC_Order $order, \WP_REST_Request $request ): void {
		$extensions = $request->get_param( 'extensions' );
		if ( ! is_array( $extensions ) ) {
			return;
		}

		$payload = $extensions[ self::STORE_API_EXTENSION_NAMESPACE ] ?? null;
		if ( ! is_array( $payload ) ) {
			return;
		}

		$channel = sanitize_key( (string) ( $payload['channel'] ?? '' ) );
		if ( self::CHANNEL_EXPRESS !== $channel ) {
			return;
		}

		$this->stamp( $order, self::CHANNEL_EXPRESS );
	}

	/**
	 * Write the marketplace meta. Guards against double-stamping if both
	 * hooks somehow fire for the same order (theoretical — the two
	 * checkout paths are mutually exclusive in production, but defense
	 * in depth keeps the channel value stable if the invariant breaks).
	 *
	 * Uses `$order->save_meta_data()` (not the full `$order->save()`)
	 * to mirror what WC core's `OrderAttributionController` does for
	 * its own attribution writes at the same point in the same hook
	 * chain. The full `save()` would trigger a double-persist of the
	 * just-created order — WC core called `$order->save()` already at
	 * `class-wc-checkout.php:471` milliseconds earlier — firing
	 * `woocommerce_update_order` to all subscribers (WCPay's own
	 * `schedule_order_tracking`, HPOS sync, OrdersScheduler, etc.)
	 * unnecessarily on every WSN order. `save_meta_data()` writes only
	 * the meta rows.
	 *
	 * @param \WC_Order $order   The order to stamp.
	 * @param string    $channel The channel slug to record (one of CHANNEL_*).
	 */
	private function stamp( \WC_Order $order, string $channel ): void {
		if ( $order->get_meta( self::META_IS_MARKETPLACE, true ) ) {
			return;
		}

		$order->update_meta_data( self::META_IS_MARKETPLACE, true );
		$order->update_meta_data( self::META_CHANNEL, $channel );
		$order->save_meta_data();
	}
}
