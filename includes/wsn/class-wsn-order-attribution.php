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
 * domain — WC core's `OrderAttributionController` captures the UTM the
 * WSN client emits (`utm_source=woo-shopping-network`,
 * `utm_content=wsn-{pdp|storefront|cart}`) and writes the standard
 * `_wc_order_attribution_utm_*` order meta. This class then copies that
 * into the WSN-owned namespace so the Hub Overview tab reads one stable
 * key (and stays insensitive to non-WSN UTM noise).
 *
 * The express channel is headless — UTM is structurally blind to it,
 * because the checkout completes off the merchant domain. The WooPay
 * `wsn-checkout` bridge controller (Alefe's `woopay-wsn-bridge-endpoint`
 * branch) sets a WC session flag `woopay_wsn_channel = 'wsn-express'`
 * during the Cart-Token handoff that survives to order placement. This
 * class reads the flag on the Store-API order-processed hook and clears
 * it after stamping.
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
	 * WC session key set by the WooPay `wsn-checkout` bridge controller
	 * to mark a Cart-Token handoff as a WSN-express origin. The flag
	 * survives the handoff because the merchant WC session is shared
	 * across the Store API order-placement path.
	 *
	 * @var string
	 */
	const SESSION_KEY_EXPRESS_CHANNEL = 'woopay_wsn_channel';

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
	 * Register the two checkout hooks. Idempotent — `add_action` dedupes
	 * the same (hook, callback, priority) triple.
	 */
	public function init_hooks(): void {
		add_action(
			'woocommerce_checkout_order_created',
			[ $this, 'stamp_classic_order_attribution' ],
			self::HOOK_PRIORITY,
			1
		);
		add_action(
			'woocommerce_store_api_checkout_order_processed',
			[ $this, 'stamp_express_order_attribution' ],
			self::HOOK_PRIORITY,
			1
		);
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
	 * Headless WooPay express checkout — read the WC session flag the
	 * WooPay bridge controller set during the Cart-Token handoff. The
	 * flag is single-use: clear it after a successful stamp so it
	 * doesn't bleed into a subsequent order on the same session.
	 *
	 * Skips silently when:
	 *  - `WC()->session` isn't available (defensive — WC initializes
	 *    session early in the Store-API request lifecycle, so this
	 *    should always be non-null at hook fire time; null-check is
	 *    belt-and-suspenders)
	 *  - The session flag is missing or doesn't equal `wsn-express`
	 *
	 * @param \WC_Order $order The order being placed.
	 */
	public function stamp_express_order_attribution( \WC_Order $order ): void {
		if ( ! WC()->session ) {
			return;
		}

		$channel = WC()->session->get( self::SESSION_KEY_EXPRESS_CHANNEL );
		if ( self::CHANNEL_EXPRESS !== $channel ) {
			return;
		}

		$this->stamp( $order, self::CHANNEL_EXPRESS );

		// Single-use — clear the flag so a follow-up non-WSN order
		// placed in the same session isn't misattributed.
		WC()->session->set( self::SESSION_KEY_EXPRESS_CHANNEL, null );
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
