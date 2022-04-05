<?php
/**
 * Class Platform_Checkout_Webhooks
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class introduces webhooks to delivery order updates to the associated
 * orders in the platform checkout.
 *
 * Platform Checkout Webhooks are enqueued to their associated actions, delivered, and logged.
 */
class Platform_Checkout_Webhooks {

	/**
	 * Platform checkout default delivery URL.
	 *
	 * TODO: change it to the actual URL we'll use in the platform checkout.
	 *
	 * @var string
	 */
	protected static $delivery_url = 'http://wcpay.test/wp-json/wc/v3/test';

	/**
	 * Setup webhook for the Platform Checkout.
	 */
	public static function init() {
		add_filter( 'woocommerce_webhook_topic_hooks', [ __CLASS__, 'add_topics' ], 20, 2 );
		add_filter( 'woocommerce_webhook_payload', [ __CLASS__, 'create_payload' ], 10, 4 );
		add_filter( 'woocommerce_valid_webhook_resources', [ __CLASS__, 'add_resource' ], 10, 1 );
		//phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// add_filter( 'woocommerce_valid_webhook_events', [__CLASS__, 'add_event'], 10, 1 ); .
		add_filter( 'woocommerce_webhook_topics', [ __CLASS__, 'add_topics_admin_menu' ], 10, 1 );

		self::maybe_create_platform_checkout_order_webhook();
	}

	/**
	 * Maybe create the Platform Checkout webhook under certain conditions.
	 *
	 * @return null|void
	 */
	private static function maybe_create_platform_checkout_order_webhook() {
		$active_webhooks               = self::get_active_platform_checkout_webhooks();
		$is_platform_checkout_eligible = WC_Payments_Features::is_platform_checkout_eligible();
		$is_platform_checkout_enabled  = 'yes' === WC_Payments::get_gateway()->get_option( 'platform_checkout', 'no' );

		if ( ! ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) ) {
			return;
		}

		if ( count( $active_webhooks ) ) {
			return;
		}

		if ( ! ( $is_platform_checkout_eligible && $is_platform_checkout_enabled ) ) {
			return;
		}

		$user = wp_get_current_user();

		$webhook = new WC_Webhook();
		$webhook->set_name( __( 'Platform Checkout order status sync', 'woocommerce-payments' ) );
		$webhook->set_user_id( $user->ID );
		// TODO: change to the custom `order.status_changed` event.
		$webhook->set_topic( 'order.updated' );
		$webhook->set_secret( wp_generate_password( 50, false ) );
		$webhook->set_delivery_url( self::$delivery_url );
		$webhook->set_status( 'active' );
		$webhook->save();
	}

	/**
	 * Get all existing active Platform Checkout webhooks.
	 *
	 * @return Platform_Checkout_Webhook[]
	 */
	public static function get_active_platform_checkout_webhooks() {
		$data_store  = WC_Data_Store::load( 'webhook' );
		$webhook_ids = $data_store->search_webhooks(
			[
				'search' => 'Platform Checkout',
				'status' => 'active',
			]
		);
		return self::collect_webhooks_for_output( $webhook_ids );
	}

	/**
	 * Collect Webhooks for output
	 *
	 * @param array|object $webhook_ids List of Webhook IDs.
	 *
	 * @return Platform_Checkout_Webhook[]|array
	 */
	protected static function collect_webhooks_for_output( $webhook_ids ) {
		if ( ! is_array( $webhook_ids ) ) {
			return [];
		}

		$webhooks = [];
		foreach ( $webhook_ids as $webhook_id ) {
			$webhook = new Platform_Checkout_Webhook( $webhook_id );
			if ( 0 !== $webhook->get_id() && $webhook->is_platform_checkout_webhook() ) {
				$webhooks[] = $webhook;
			}
		}
		return $webhooks;
	}

	/**
	 * Add order webhook topic
	 *
	 * @param array $topic_hooks List of WooCommerce's standard webhook topics and hooks.
	 */
	public static function add_topics( $topic_hooks ) {
		$topic_hooks['order.status_changed'][] = 'wcpay_webhook_order_status_changed';

		return $topic_hooks;
	}

	/**
	 * Add order topics to the Webhooks dropdown menu in when creating a new webhook.
	 *
	 * @param array $topics List of WooCommerce's standard webhook topics.
	 */
	public static function add_topics_admin_menu( $topics ) {
		$front_end_topics = [
			'order.status_changed' => __( 'Order status changed', 'woocommerce-payments' ),
		];

		return array_merge( $topics, $front_end_topics );
	}

	/**
	 * Setup payload for the order webhook delivery.
	 *
	 * @param array   $payload     Data to be sent out by the webhook.
	 * @param string  $resource    Type/name of the resource.
	 * @param integer $resource_id ID of the resource.
	 * @param integer $id          ID of the webhook.
	 */
	public static function create_payload( $payload, $resource, $resource_id, $id ) {
		return $payload;
	}

	/**
	 * Add webhook resource for order.
	 *
	 * @param array $resources List of available resources.
	 */
	public static function add_resource( $resources ) {
		$resources[] = 'order';

		return $resources;
	}

}
