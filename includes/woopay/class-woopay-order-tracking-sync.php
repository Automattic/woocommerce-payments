<?php
/**
 * Class WooPay_Order_Tracking_Sync
 *
 * @package WooCommerce\Payments
 */

declare( strict_types=1 );

namespace WCPay\WooPay;

use WC_Payments_Account;
use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;
use WCPay\WooPay\Tracking_Providers\WooPay_Tracking_Provider;
use WCPay\WooPay\Tracking_Providers\WooPay_Shipment_Tracking_Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Delivers order tracking updates to WooPay via webhooks.
 *
 * Uses an adapter pattern: each shipping plugin gets a provider class
 * that normalizes tracking data into a common format. Providers are
 * tried in priority order; the first to return data wins.
 */
class WooPay_Order_Tracking_Sync {
	const WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED = 'wcpay_webhook_platform_checkout_order_tracking_updated';

	/**
	 * Transient prefix for webhook debouncing.
	 */
	const DEBOUNCE_TRANSIENT_PREFIX = 'woopay_tracking_webhook_';

	/**
	 * Debounce window in seconds.
	 */
	const DEBOUNCE_SECONDS = 5;

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
	 * Cached providers array.
	 *
	 * @var WooPay_Tracking_Provider[]|null
	 */
	private static $providers = null;

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
				add_action(
					$hook_config['hook'],
					[ __CLASS__, 'send_webhook' ],
					10,
					$hook_config['arg_count']
				);
			}
		}

		add_action( 'admin_init', [ $this, 'maybe_create_woopay_order_webhook' ], 10 );
	}

	/**
	 * Get the ordered list of tracking providers.
	 *
	 * @return WooPay_Tracking_Provider[]
	 */
	public static function get_providers(): array {
		if ( null !== self::$providers ) {
			return self::$providers;
		}

		$providers = [
			new WooPay_Shipment_Tracking_Provider(),
		];

		/**
		 * Filters the list of tracking providers.
		 *
		 * @param WooPay_Tracking_Provider[] $providers Ordered array of providers.
		 */
		self::$providers = apply_filters( 'wcpay_woopay_tracking_providers', $providers );

		return self::$providers;
	}

	/**
	 * Reset the cached providers (for testing).
	 */
	public static function reset_providers(): void {
		self::$providers = null;
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
	 * Return true if webhook was already created.
	 *
	 * @return bool
	 */
	private static function is_webhook_created(): bool {
		return ! empty( self::get_webhook() );
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

		$webhooks = $data_store->search_webhooks( $args );
		return $webhooks;
	}

	/**
	 * Register the webhook on WooCommerce.
	 */
	private function register_webhook(): void {
		$webhook = new \WC_Webhook();
		$webhook->set_name( self::get_webhook_name() );
		$webhook->set_user_id( get_current_user_id() );
		$webhook->set_topic( 'order.tracking_updated' );
		$webhook->set_secret( wp_generate_password( 50, false ) );
		$webhook->set_delivery_url( WooPay_Utilities::get_woopay_rest_url( 'merchant-notification' ) );
		$webhook->set_status( 'active' );
		$webhook->save();

		try {
			$this->payments_api_client->update_woopay( [ 'webhook_secret' => $webhook->get_secret() ] );
		} catch ( API_Exception $e ) {
			$webhook->delete();
		}
	}

	/**
	 * Add order webhook topic.
	 *
	 * @param array $topic_hooks List of WooCommerce's standard webhook topics and hooks.
	 * @return array
	 */
	public static function add_topics( $topic_hooks ): array {
		$topic_hooks['order.tracking_updated'][] = self::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED;

		return $topic_hooks;
	}

	/**
	 * Setup payload for the webhook delivery.
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

		// Only modify payload for tracking webhooks.
		if ( 'order.tracking_updated' !== $webhook->get_topic() ) {
			return $payload;
		}

		$order = wc_get_order( $resource_id );
		if ( ! $order ) {
			return $payload;
		}

		return [
			'blog_id'   => \Jetpack_Options::get_option( 'id' ),
			'order_id'  => $resource_id,
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
		$topic_events[] = 'tracking_updated';

		return $topic_events;
	}

	/**
	 * Trigger webhook delivery.
	 *
	 * Normalizes the first argument from various hook signatures to an order ID,
	 * checks if the order is a WooPay order, debounces duplicate calls, and fires
	 * the webhook action.
	 *
	 * @param mixed ...$args Hook arguments (varies by provider).
	 */
	public static function send_webhook( ...$args ): void {
		$order_id = self::resolve_order_id( $args );
		if ( null === $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return;
		}

		// Debounce: skip if a webhook was recently fired for this order.
		$transient_key = self::DEBOUNCE_TRANSIENT_PREFIX . $order_id;
		if ( false !== get_transient( $transient_key ) ) {
			return;
		}

		set_transient( $transient_key, true, self::DEBOUNCE_SECONDS );

		do_action( self::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED, $order_id );
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
	 * Get normalized shipments for an order using the provider chain.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array[] Normalized shipments array.
	 */
	public static function get_order_shipments( \WC_Order $order ): array {
		foreach ( self::get_providers() as $provider ) {
			if ( $provider->is_available( $order ) ) {
				$shipments = $provider->get_shipments( $order );
				if ( ! empty( $shipments ) ) {
					return $shipments;
				}
			}
		}

		return [];
	}

	/**
	 * Removes the webhook if woopay is disabled.
	 */
	public static function remove_webhook(): void {
		if ( self::is_webhook_created() ) {
			$webhook_id = self::get_webhook()[0];
			$webhook    = new \WC_Webhook( $webhook_id );
			$webhook->delete();
		}
	}
}
