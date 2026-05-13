<?php
/**
 * Class WooPay_Order_Tracking_Sync
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use WC_Payments;
use WC_Payments_Account;
use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;
use WCPay\WooPay\Tracking_Providers\WooPay_Tracking_Provider;
use WCPay\WooPay\Tracking_Providers\WooPay_Status_Overlay_Provider;
use WCPay\WooPay\Tracking_Providers\WooPay_Fulfillments_API_Provider;
use WCPay\WooPay\Tracking_Providers\WooPay_ShipStation_Provider;
use WCPay\WooPay\Tracking_Providers\WooPay_Shipment_Tracking_Provider;
use WCPay\WooPay\Tracking_Providers\WooPay_AfterShip_Provider;
use WCPay\WooPay\Tracking_Providers\WooPay_TrackShip_Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Delivers order tracking updates to WooPay via webhooks.
 *
 * Uses an adapter pattern: each shipping plugin gets a provider class
 * that normalizes tracking data into a common format. Providers are
 * tried in priority order; the first to return data wins.
 *
 * Modeled on WooPay_Order_Status_Sync; differs in supporting variadic
 * hook signatures (different shipping plugins fire hooks with different
 * argument shapes) and a 5-second debounce to coalesce duplicate fires.
 */
class WooPay_Order_Tracking_Sync {
	const WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED = 'wcpay_webhook_platform_checkout_order_tracking_updated';
	const WEBHOOK_TOPIC                               = 'order.tracking_updated';
	const WEBHOOK_EVENT                               = 'tracking_updated';
	const WEBHOOK_ID_OPTION                           = 'wcpay_woopay_tracking_webhook_id';
	const DEBOUNCE_TRANSIENT_PREFIX                   = 'woopay_tracking_webhook_';
	const DEBOUNCE_SECONDS                            = 5;

	// Canonical shipment status vocabulary. The WooPay receiver is coded against
	// this exact set (client/utils/shipments.js#SHIPMENT_STATUSES on the
	// shipping-tracker-banner-redesign branch). Providers MUST emit one of
	// these values; raw provider-specific strings are rejected by
	// ensure_canonical_status() and downgraded to STATUS_FULFILLED.
	//
	// Note: "pending" is intentionally NOT a shipment status — "order placed
	// with no tracking yet" is an order-level state, signalled by zero
	// shipments in the payload. The receiver renders that as "Ordered {date}".
	const STATUS_FULFILLED            = 'fulfilled';
	const STATUS_IN_TRANSIT           = 'in_transit';
	const STATUS_OUT_FOR_DELIVERY     = 'out_for_delivery';
	const STATUS_AVAILABLE_FOR_PICKUP = 'available_for_pickup';
	const STATUS_DELIVERED            = 'delivered';
	const STATUS_EXCEPTION            = 'exception';

	const SHIPMENT_STATUSES = [
		self::STATUS_FULFILLED,
		self::STATUS_IN_TRANSIT,
		self::STATUS_OUT_FOR_DELIVERY,
		self::STATUS_AVAILABLE_FOR_PICKUP,
		self::STATUS_DELIVERED,
		self::STATUS_EXCEPTION,
	];

	/**
	 * Resolved primary tracking providers (produce shipments).
	 *
	 * @var WooPay_Tracking_Provider[]|null
	 */
	private static $providers = null;

	/**
	 * Resolved status-overlay providers (enrich shipments with carrier status).
	 *
	 * @var WooPay_Status_Overlay_Provider[]|null
	 */
	private static $overlay_providers = null;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Client for making requests to the WooCommerce Payments API.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * Setup webhook for the WooPay Order Tracking Sync.
	 *
	 * @param WC_Payments_API_Client $payments_api_client WooCommerce Payments API client.
	 * @param WC_Payments_Account    $account             WooCommerce Payments account.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account ) {
		$this->payments_api_client = $payments_api_client;
		$this->account             = $account;

		add_filter( 'woocommerce_webhook_topic_hooks', [ __CLASS__, 'add_topics' ], 20, 2 );
		add_filter( 'woocommerce_webhook_payload', [ __CLASS__, 'create_payload' ], 10, 4 );
		add_filter( 'woocommerce_valid_webhook_resources', [ __CLASS__, 'add_resource' ], 10, 1 );
		add_filter( 'woocommerce_valid_webhook_events', [ __CLASS__, 'add_event' ], 10, 1 );

		// Register hooks from all available providers.
		foreach ( self::get_providers() as $provider ) {
			foreach ( $provider->get_hooks() as $hook_config ) {
				if ( isset( $hook_config['meta_key'] ) ) {
					// Meta-write hooks (added_post_meta, updated_post_meta,
					// added_order_meta, updated_order_meta) all fire with the
					// signature ($meta_id, $object_id, $meta_key, $meta_value).
					// arg_count is hard-forced to 4 here regardless of what
					// the hook spec declares — the closure below requires all
					// four parameters, and forwarding fewer would fatal with
					// ArgumentCountError on hook fire if a third-party provider
					// declared `meta_key` but forgot `arg_count`.
					//
					// We can't register `send_webhook` directly because every
					// meta key on every object would trigger it; the closure
					// short-circuits on key mismatch and forwards just the
					// order ID to send_webhook so resolve_order_id() handles
					// it via its existing numeric path.
					$expected_key = $hook_config['meta_key'];
					add_action(
						$hook_config['hook'],
						function ( $meta_id, $object_id, $key, $value ) use ( $expected_key ) {
							if ( $key !== $expected_key ) {
								return;
							}
							self::send_webhook( $object_id );
						},
						10,
						4
					);
				} else {
					add_action(
						$hook_config['hook'],
						[ __CLASS__, 'send_webhook' ],
						10,
						(int) ( $hook_config['arg_count'] ?? 1 )
					);
				}
			}

			// Some providers persist hook arguments before send_webhook fires.
			// Required when a plugin's hook delivers tracking data only as a
			// transient argument (e.g. ShipStation in standalone mode) — by
			// the time WC_Webhook delivery builds the payload, the hook arg
			// is gone, so the provider must capture it into stable storage.
			//
			// Dispatched via call_user_func with a string-class callable so
			// PHPStan can resolve the static method from the runtime class
			// instead of the WooPay_Tracking_Provider interface (which
			// intentionally does not declare this optional method).
			if ( method_exists( $provider, 'register_persistence_hooks' ) ) {
				call_user_func( [ get_class( $provider ), 'register_persistence_hooks' ] );
			}
		}

		add_action( 'admin_init', [ $this, 'maybe_create_woopay_order_webhook' ], 10 );
	}

	/**
	 * Get the ordered list of tracking providers.
	 *
	 * Filterable via `wcpay_woopay_tracking_providers`. The first provider in
	 * the list whose `is_available()` returns true and `get_shipments()` returns
	 * non-empty data wins — there is no merging across providers.
	 *
	 * @return WooPay_Tracking_Provider[]
	 */
	public static function get_providers(): array {
		if ( null !== self::$providers ) {
			return self::$providers;
		}

		$default_providers = [
			// Priority 1: WC Core Fulfillments API (WC 10.2+, behind feature flag).
			new WooPay_Fulfillments_API_Provider(),
			// Priority 2: WC Shipment Tracking + Advanced Shipment Tracking (de facto standard, ~85% coverage).
			new WooPay_Shipment_Tracking_Provider(),
			// Priority 3: ShipStation standalone (no WC Shipment Tracking bridge).
			new WooPay_ShipStation_Provider(),
			// Priority 4: AfterShip WooCommerce Tracking (1M+ downloads, separate meta key).
			new WooPay_AfterShip_Provider(),
			// Priority 5: TrackShip — primary chain entry returns empty
			// (it's a status enricher, not a tracking-data producer), but
			// the entry is required here so the sync constructor invokes its
			// register_persistence_hooks() to listen on
			// trackship_shipment_status_trigger. The real enrichment work
			// happens via the WooPay_Status_Overlay_Provider interface.
			new WooPay_TrackShip_Provider(),
		];

		/**
		 * Filters the list of tracking providers.
		 *
		 * Order matters: providers are tried in array order and the first
		 * non-empty result wins. To insert a custom provider at higher
		 * priority, prepend it.
		 *
		 * @param WooPay_Tracking_Provider[] $providers Ordered array of providers.
		 */
		$filtered = (array) apply_filters( 'wcpay_woopay_tracking_providers', $default_providers );

		// Filter out non-conforming entries — a third-party filter callback
		// returning the wrong shape should not be able to fatal the sync
		// orchestrator. array_filter preserves keys, so re-index for
		// downstream consumers that walk by numeric index.
		self::$providers = array_values(
			array_filter(
				$filtered,
				static fn( $p ) => $p instanceof WooPay_Tracking_Provider
			)
		);

		return self::$providers;
	}

	/**
	 * Get the ordered list of status-overlay providers.
	 *
	 * Overlay providers enrich shipments produced by the primary chain with
	 * carrier-status data (status, status_updated_at). Filterable via
	 * `wcpay_woopay_status_overlay_providers`. Unlike the primary chain,
	 * ALL overlays run in priority order — each may enrich the result.
	 *
	 * @return WooPay_Status_Overlay_Provider[]
	 */
	public static function get_overlay_providers(): array {
		if ( null !== self::$overlay_providers ) {
			return self::$overlay_providers;
		}

		$default_overlays = [
			// Priority 5: TrackShip — first carrier-status enricher in the chain.
			new WooPay_TrackShip_Provider(),
		];

		/**
		 * Filters the list of status-overlay providers.
		 *
		 * Order matters: overlays run in array order; later overlays may
		 * overwrite fields set by earlier ones for the same shipment.
		 *
		 * @param WooPay_Status_Overlay_Provider[] $overlay_providers Ordered array of overlays.
		 */
		$filtered = (array) apply_filters( 'wcpay_woopay_status_overlay_providers', $default_overlays );

		// Filter out non-conforming entries — a third-party filter callback
		// returning the wrong shape should not be able to fatal the sync
		// orchestrator's overlay pass.
		self::$overlay_providers = array_values(
			array_filter(
				$filtered,
				static fn( $p ) => $p instanceof WooPay_Status_Overlay_Provider
			)
		);

		return self::$overlay_providers;
	}

	/**
	 * Reset the cached primary providers. Test-only: production code should not call this.
	 *
	 * @internal
	 */
	public static function reset_providers(): void {
		self::$providers = null;
	}

	/**
	 * Reset the cached overlay providers. Test-only: production code should not call this.
	 *
	 * @internal
	 */
	public static function reset_overlay_providers(): void {
		self::$overlay_providers = null;
	}

	/**
	 * Maybe create the WooPay webhook under certain conditions.
	 */
	public function maybe_create_woopay_order_webhook(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || self::is_webhook_created() ) {
			return;
		}

		if ( ! $this->account->is_stripe_account_valid() || $this->account->is_account_under_review() || $this->account->is_account_rejected() ) {
			return;
		}

		$this->register_webhook();
	}

	/**
	 * Return array with the webhook id for the woopay order tracking sync.
	 *
	 * @return array
	 */
	public static function get_webhook(): array {
		$data_store = \WC_Data_Store::load( 'webhook' );

		$args = [
			'search' => self::get_webhook_name(),
			'status' => 'active',
			'limit'  => 1,
		];

		return $data_store->search_webhooks( $args );
	}

	/**
	 * Add order webhook topic.
	 *
	 * @param array $topic_hooks List of WooCommerce's standard webhook topics and hooks.
	 * @return array
	 */
	public static function add_topics( $topic_hooks ): array {
		$topic_hooks[ self::WEBHOOK_TOPIC ][] = self::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED;

		return $topic_hooks;
	}

	/**
	 * Setup payload for the webhook delivery.
	 *
	 * The `is_woopay` re-check here is defense-in-depth: `send_webhook()`
	 * already gates emission, but if any other code path triggers this
	 * webhook for a non-WooPay order we should not assemble a payload either.
	 *
	 * @param array   $payload       Data to be sent out by the webhook.
	 * @param string  $resource_name Type/name of the resource.
	 * @param integer $resource_id   ID of the resource.
	 * @param integer $id            ID of the webhook.
	 * @return array
	 */
	public static function create_payload( $payload, $resource_name, $resource_id, $id ): array {
		$webhook = wc_get_webhook( $id );
		if ( ! $webhook ) {
			return $payload;
		}

		if ( 0 !== strpos( $webhook->get_delivery_url(), WooPay_Utilities::get_woopay_rest_url( 'merchant-notification' ) ) ) {
			return $payload;
		}

		// Topic guard: WooPay_Order_Status_Sync registers the same payload
		// filter against the same delivery URL, so without this check
		// whichever filter runs last would clobber the other webhook's
		// payload. Status-sync has a mirroring guard.
		if ( self::WEBHOOK_TOPIC !== $webhook->get_topic() ) {
			return $payload;
		}

		$order = wc_get_order( $resource_id );
		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return $payload;
		}

		return [
			'blog_id'   => \Jetpack_Options::get_option( 'id' ),
			'order_id'  => (int) $resource_id,
			'shipments' => self::get_order_shipments( $order ),
		];
	}

	/**
	 * Add webhook resource for order.
	 *
	 * @param array $resources List of available resources.
	 * @return array
	 */
	public static function add_resource( $resources ): array {
		$resources[] = 'order';

		return $resources;
	}

	/**
	 * Add tracking_updated event.
	 *
	 * @param array $topic_events List of available topic events.
	 * @return array
	 */
	public static function add_event( $topic_events ): array {
		$topic_events[] = self::WEBHOOK_EVENT;

		return $topic_events;
	}

	/**
	 * Trigger webhook delivery.
	 *
	 * Order of checks (cheapest → most expensive):
	 *   1. WooPay enabled at all on this shop? (gateway option lookup)
	 *   2. Resolvable order ID from the hook arg shape?
	 *   3. Per-order debounce already set?
	 *   4. Order exists and is a WooPay order?
	 *
	 * Ordering matters because some hooks (e.g. shipment-tracking) can fire
	 * thousands of times during CSV imports; the first three checks must be
	 * O(1) so non-WooPay merchants pay essentially nothing.
	 *
	 * @param mixed ...$args Hook arguments (varies by provider).
	 */
	public static function send_webhook( ...$args ): void {
		// Cheap account-level gate first — saves N × wc_get_order() during bulk imports
		// on shops where WooPay isn't enabled at all.
		if ( ! self::is_woopay_active_on_shop() ) {
			return;
		}

		$order_id = self::resolve_order_id( $args );
		if ( null === $order_id ) {
			return;
		}

		// Debounce before order load: if the same order fires multiple hooks in
		// quick succession (e.g. add + delete + re-add by AST migration), skip
		// the work entirely after the first one.
		$transient_key = self::DEBOUNCE_TRANSIENT_PREFIX . $order_id;
		if ( false !== get_transient( $transient_key ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return;
		}

		set_transient( $transient_key, true, self::DEBOUNCE_SECONDS );

		do_action( self::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED, $order_id );
	}

	/**
	 * Get normalized shipments for an order, with overlay enrichment.
	 *
	 * Two-pass orchestration:
	 *   1. **Primary chain** — first provider with `is_available() === true`
	 *      AND non-empty `get_shipments()` wins. Produces tracking_number,
	 *      carrier_name, tracking_url, date_shipped, items, plus a default
	 *      `status` of `STATUS_FULFILLED`.
	 *   2. **Overlay chain** — every registered overlay provider runs in
	 *      priority order over the result. Each may enrich `status` and
	 *      `status_updated_at` by matching shipments on `tracking_number`.
	 *      Overlays are skipped if the primary chain produced nothing —
	 *      enrichers, not producers.
	 *
	 * Finally, every shipment's `status` is run through
	 * `ensure_canonical_status()` so the wire payload is guaranteed to use
	 * only values from `SHIPMENT_STATUSES`, regardless of which provider
	 * emitted them.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array[] Normalized shipments array.
	 */
	public static function get_order_shipments( \WC_Order $order ): array {
		$shipments = [];

		foreach ( self::get_providers() as $provider ) {
			if ( ! $provider->is_available( $order ) ) {
				continue;
			}
			$result = $provider->get_shipments( $order );
			if ( ! empty( $result ) ) {
				$shipments = $result;
				break;
			}
		}

		if ( empty( $shipments ) ) {
			return [];
		}

		foreach ( self::get_overlay_providers() as $overlay ) {
			$shipments = $overlay->overlay( $order, $shipments );
		}

		return array_map(
			static function ( $shipment ) {
				if ( isset( $shipment['status'] ) ) {
					$shipment['status'] = self::ensure_canonical_status( (string) $shipment['status'] );
				} else {
					$shipment['status'] = self::STATUS_FULFILLED;
				}
				return $shipment;
			},
			$shipments
		);
	}

	/**
	 * Removes the webhook if woopay is disabled.
	 *
	 * Prefer the cached webhook ID for deletion since `is_webhook_created()`
	 * may have validated against the cache without re-running the
	 * (translation-name-dependent) `get_webhook()` search. Fall back to the
	 * search and guard the array access.
	 */
	public static function remove_webhook(): void {
		$webhook_id = (int) get_option( self::WEBHOOK_ID_OPTION, 0 );

		if ( $webhook_id <= 0 ) {
			$webhooks = self::get_webhook();
			if ( empty( $webhooks ) ) {
				return;
			}
			$webhook_id = (int) $webhooks[0];
		}

		$webhook = new \WC_Webhook( $webhook_id );
		$webhook->delete();
		delete_option( self::WEBHOOK_ID_OPTION );
	}

	/**
	 * Register the webhook on WooCommerce.
	 */
	protected function register_webhook(): void {
		// Shared with WooPay_Order_Status_Sync: WooPay stores a single
		// webhook secret per site, so all merchant-notification webhooks
		// (status_changed, tracking_updated, …) sign with the same secret.
		$secret = WooPay_Utilities::get_or_create_webhook_secret();

		$webhook = new \WC_Webhook();
		$webhook->set_name( self::get_webhook_name() );
		$webhook->set_user_id( get_current_user_id() );
		$webhook->set_topic( self::WEBHOOK_TOPIC );
		$webhook->set_secret( $secret );
		$webhook->set_delivery_url( WooPay_Utilities::get_woopay_rest_url( 'merchant-notification' ) );
		$webhook->set_status( 'active' );
		$webhook->save();

		try {
			$this->payments_api_client->update_woopay( [ 'webhook_secret' => $secret ] );
			update_option( self::WEBHOOK_ID_OPTION, (int) $webhook->get_id(), false );
		} catch ( API_Exception $e ) {
			$webhook->delete();
		}
	}

	/**
	 * Return the webhook name.
	 *
	 * @return string
	 */
	private static function get_webhook_name(): string {
		return __( 'WooPayments woopay order tracking sync', 'woocommerce-payments' );
	}

	/**
	 * Return true if webhook was already created.
	 *
	 * Cached via wp_option so the per-`admin_init` `search_webhooks` DB query
	 * only fires the first time. The cached ID is verified against the
	 * webhook data store to detect manual deletion via the WC admin UI.
	 *
	 * @return bool
	 */
	private static function is_webhook_created(): bool {
		$cached_id = (int) get_option( self::WEBHOOK_ID_OPTION, 0 );
		if ( $cached_id > 0 ) {
			$webhook = wc_get_webhook( $cached_id );
			if ( $webhook && 'active' === $webhook->get_status() ) {
				return true;
			}
			// Cached webhook was removed externally — clear the cache and re-search.
			delete_option( self::WEBHOOK_ID_OPTION );
		}

		$webhooks = self::get_webhook();
		if ( empty( $webhooks ) ) {
			return false;
		}
		update_option( self::WEBHOOK_ID_OPTION, (int) $webhooks[0], false );
		return true;
	}

	/**
	 * Cheap shop-level gate: is WooPay enabled here at all?
	 *
	 * Returns false on shops that never opted into WooPay so non-WooPay
	 * merchants don't pay `wc_get_order()` per shipping-plugin hook fire.
	 *
	 * @return bool
	 */
	private static function is_woopay_active_on_shop(): bool {
		if ( ! class_exists( 'WC_Payments' ) ) {
			return false;
		}
		$gateway = WC_Payments::get_gateway();
		if ( ! $gateway ) {
			return false;
		}
		return 'yes' === $gateway->get_option( 'platform_checkout', 'no' );
	}

	/**
	 * Resolve the first hook argument to an order ID.
	 *
	 * Handles different hook signatures:
	 * - Numeric order ID (WC Shipment Tracking / AST hooks)
	 * - WC_Order instance (ShipStation hooks)
	 * - Fulfillment object with get_entity_id() (WC Fulfillments API hooks)
	 *
	 * @param array $args Hook arguments.
	 * @return int|null Order ID or null if unresolvable.
	 */
	private static function resolve_order_id( array $args ): ?int {
		if ( empty( $args ) ) {
			return null;
		}

		$first_arg = $args[0];

		// Numeric order ID (WC Shipment Tracking, AST).
		if ( is_numeric( $first_arg ) ) {
			return (int) $first_arg;
		}

		// WC_Order instance (ShipStation).
		if ( $first_arg instanceof \WC_Order ) {
			return $first_arg->get_id();
		}

		// Fulfillment object (WC Fulfillments API).
		if ( is_object( $first_arg ) && method_exists( $first_arg, 'get_entity_id' ) ) {
			$entity_id = $first_arg->get_entity_id();
			return is_numeric( $entity_id ) ? (int) $entity_id : null;
		}

		return null;
	}

	/**
	 * Coerce a shipment status to a canonical value from `SHIPMENT_STATUSES`.
	 *
	 * Defensive: catches future providers emitting non-canonical values,
	 * mapping bugs, and unsanitized raw vendor-specific statuses leaking
	 * through. Falls back to `STATUS_FULFILLED` so the wire payload always
	 * carries a renderable value.
	 *
	 * Logging contract: a non-canonical value is logged via wc_get_logger
	 * for diagnosability. Control characters are stripped and the value is
	 * truncated before logging — defense-in-depth against log-injection
	 * (newline-spoofed log lines) if a compromised provider emits a status
	 * containing CR/LF. Canonical statuses are all short alphanumeric
	 * tokens, so the 64-char cap is a generous bound on legitimate values.
	 *
	 * @param string $status Status emitted by a provider or overlay.
	 * @return string A value guaranteed to be in `SHIPMENT_STATUSES`.
	 */
	private static function ensure_canonical_status( string $status ): string {
		if ( in_array( $status, self::SHIPMENT_STATUSES, true ) ) {
			return $status;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			$safe_for_log = substr(
				(string) preg_replace( '/[\x00-\x1f\x7f]+/', ' ', $status ),
				0,
				64
			);
			wc_get_logger()->notice(
				sprintf( 'Non-canonical shipment status emitted: "%s". Falling back to fulfilled.', $safe_for_log ),
				[ 'source' => 'woopay-order-tracking-sync' ]
			);
		}

		return self::STATUS_FULFILLED;
	}
}
