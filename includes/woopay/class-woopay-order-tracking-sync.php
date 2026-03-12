<?php
/**
 * Class WooPay_Order_Tracking_Sync
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use WC_Payments_Account;
use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * This class introduces webhooks to deliver order tracking updates to the
 * associated orders in WooPay.
 *
 * Listens for WooCommerce Fulfillment events and sends tracking data
 * (carrier, tracking number, tracking URL) to WooPay via webhook.
 */
class WooPay_Order_Tracking_Sync {
	const WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED = 'wcpay_webhook_platform_checkout_order_tracking_updated';

	/**
	 * WC_Payments_Account instance to get information about the account.
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
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 * @param WC_Payments_Account    $account - WooCommerce Payments account.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account ) {

		$this->payments_api_client = $payments_api_client;
		$this->account             = $account;

		add_filter( 'woocommerce_webhook_topic_hooks', [ __CLASS__, 'add_topics' ], 20, 2 );
		add_filter( 'woocommerce_webhook_payload', [ __CLASS__, 'create_payload' ], 10, 4 );
		add_filter( 'woocommerce_valid_webhook_resources', [ __CLASS__, 'add_resource' ], 10, 1 );
		add_filter( 'woocommerce_valid_webhook_events', [ __CLASS__, 'add_event' ], 10, 1 );

		add_action( 'woocommerce_fulfillment_after_create', [ __CLASS__, 'send_webhook' ], 10, 1 );
		add_action( 'woocommerce_fulfillment_after_update', [ __CLASS__, 'send_webhook_on_update' ], 10, 3 );
		add_action( 'woocommerce_fulfillment_after_fulfill', [ __CLASS__, 'send_webhook' ], 10, 1 );
		add_action( 'woocommerce_fulfillment_after_delete', [ __CLASS__, 'send_webhook' ], 10, 1 );

		add_action( 'admin_init', [ $this, 'maybe_create_woopay_tracking_webhook' ], 10 );
	}

	/**
	 * Return the webhook name.
	 *
	 * @return string
	 */
	private static function get_webhook_name() {
		return __( 'WooPayments woopay order tracking sync', 'woocommerce-payments' );
	}

	/**
	 * Maybe create the WooPay tracking webhook under certain conditions.
	 */
	public function maybe_create_woopay_tracking_webhook() {
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
	private static function is_webhook_created() {
		return ! empty( self::get_webhook() );
	}

	/**
	 * Return array with the webhook id for the woopay order tracking sync.
	 *
	 * @return array
	 */
	public static function get_webhook() {
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
	 *
	 * @return void
	 */
	private function register_webhook() {
		$webhook = new \WC_Webhook();
		$webhook->set_name( self::get_webhook_name() );
		$webhook->set_user_id( get_current_user_id() );
		$webhook->set_topic( 'order.tracking_updated' );
		$webhook->set_secret( wp_generate_password( 50, false ) );
		$webhook->set_delivery_url( WooPay_Utilities::get_woopay_rest_url( 'merchant-notification' ) );
		$webhook->set_status( 'active' );
		$webhook->save();

		try {
			$this->payments_api_client->update_woopay( [ 'tracking_webhook_secret' => $webhook->get_secret() ] );
		} catch ( API_Exception $e ) {
			$webhook->delete();
		}
	}

	/**
	 * Add order tracking webhook topic.
	 *
	 * @param array $topic_hooks List of WooCommerce's standard webhook topics and hooks.
	 * @return array
	 */
	public static function add_topics( $topic_hooks ) {
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
	public static function create_payload( $payload, $resource_name, $resource_id, $id ) {
		$webhook = wc_get_webhook( $id );
		if ( ! $webhook ) {
			return $payload;
		}

		if ( 'order.tracking_updated' !== $webhook->get_topic() ) {
			return $payload;
		}

		if ( 0 !== strpos( $webhook->get_delivery_url(), WooPay_Utilities::get_woopay_rest_url( 'merchant-notification' ) ) ) {
			return $payload;
		}

		$order = wc_get_order( $resource_id );
		if ( ! $order ) {
			return $payload;
		}

		$shipments = self::get_order_shipments( $order );

		return [
			'blog_id'   => \Jetpack_Options::get_option( 'id' ),
			'order_id'  => $resource_id,
			'shipments' => $shipments,
		];
	}

	/**
	 * Get all shipment tracking data for an order from its fulfillments.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array Array of shipment data.
	 */
	private static function get_order_shipments( $order ) {
		$shipments = [];

		if ( ! function_exists( 'wc_get_fulfillments' ) ) {
			return $shipments;
		}

		$fulfillments = wc_get_fulfillments( [ 'entity_id' => $order->get_id() ] );

		foreach ( $fulfillments as $fulfillment ) {
			$tracking_number = $fulfillment->get_meta( '_tracking_number' );

			if ( empty( $tracking_number ) ) {
				continue;
			}

			$items = [];
			foreach ( $fulfillment->get_items() as $item ) {
				$items[] = [
					'name'     => $item->get_name(),
					'quantity' => $item->get_quantity(),
				];
			}

			$shipments[] = [
				'tracking_number' => $tracking_number,
				'carrier_name'    => $fulfillment->get_meta( '_shipping_provider' ),
				'tracking_url'    => $fulfillment->get_meta( '_tracking_url' ),
				'date_shipped'    => $fulfillment->get_date_created() ? $fulfillment->get_date_created()->format( 'Y-m-d' ) : null,
				'status'          => $fulfillment->get_status(),
				'items'           => $items,
			];
		}

		return $shipments;
	}

	/**
	 * Add webhook resource for order.
	 *
	 * @param array $resources List of available resources.
	 * @return array
	 */
	public static function add_resource( $resources ) {
		$resources[] = 'order';

		return $resources;
	}

	/**
	 * Add tracking_updated as a valid webhook event.
	 *
	 * @param array $topic_events List of available topic events.
	 * @return array
	 */
	public static function add_event( $topic_events ) {
		$topic_events[] = 'tracking_updated';

		return $topic_events;
	}

	/**
	 * Trigger webhook delivery for a fulfillment event.
	 *
	 * @param object $fulfillment The WC_Fulfillment object.
	 * @return void
	 */
	public static function send_webhook( $fulfillment ) {
		$order_id = $fulfillment->get_entity_id();
		$order    = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return;
		}

		do_action( self::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED, $order_id );
	}

	/**
	 * Trigger webhook delivery on fulfillment update, only if tracking-relevant props changed.
	 *
	 * @param object $fulfillment  The WC_Fulfillment object.
	 * @param array  $changed_props Properties that changed.
	 * @param array  $old_state     Previous state.
	 * @return void
	 */
	public static function send_webhook_on_update( $fulfillment, $changed_props, $old_state ) {
		self::send_webhook( $fulfillment );
	}

	/**
	 * Removes the tracking webhook if WooPay is disabled.
	 *
	 * @return void
	 */
	public static function remove_webhook() {

		if ( self::is_webhook_created() ) {
			$webhook_id = self::get_webhook()[0];
			$webhook    = new \WC_Webhook( $webhook_id );
			$webhook->delete();
		}
	}
}
