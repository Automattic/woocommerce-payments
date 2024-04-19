<?php
/**
 * Class WooPay_Webhooks
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use WC_Payments_Account;
use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * This class introduces webhooks to delivery order updates to the associated
 * orders in the woopay.
 *
 * WooPay Webhooks are enqueued to their associated actions, delivered, and logged.
 */
class WooPay_Order_Status_Sync {
	const WCPAY_WEBHOOK_WOOPAY_ORDER_STATUS_CHANGED = 'wcpay_webhook_platform_checkout_order_status_changed';

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * Setup webhook for the WooPay Order Status Sync.
	 *
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 * @param WC_Payments_Account    $account - WooCommerce Payments account.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account ) {

		$this->payments_api_client = $payments_api_client;
		$this->account			   = $account;

		add_filter( 'woocommerce_webhook_topic_hooks', [ __CLASS__, 'add_topics' ], 20, 2 );
		add_filter( 'woocommerce_webhook_payload', [ __CLASS__, 'create_payload' ], 10, 4 );
		add_filter( 'woocommerce_valid_webhook_resources', [ __CLASS__, 'add_resource' ], 10, 1 );
		add_filter( 'woocommerce_valid_webhook_events', [ __CLASS__, 'add_event' ], 10, 1 );
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'send_webhook' ], 10, 3 );

		add_action( 'admin_init', [ $this, 'maybe_create_woopay_order_webhook' ], 10 );
	}

	/**
	 * Return the webhook name.
	 *
	 * @return string
	 */
	private static function get_webhook_name() {
		return __( 'WCPay woopay order status sync', 'woocommerce-payments' );
	}

	/**
	 * Maybe create the WooPay webhook under certain conditions.
	 */
	public function maybe_create_woopay_order_webhook() {
		if ( ! current_user_can( 'manage_woocommerce' ) || self::is_webhook_created() ) {
			return;
		}

		if ( ! $this->account->is_stripe_connected() || $this->account->is_account_under_review() || $this->account->is_account_rejected() ) {
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
	 * Return array with the webhook id for the woopay order status sync.
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
		$webhook->set_topic( 'order.status_changed' );
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
	 * Add order webhook topic
	 *
	 * @param array $topic_hooks List of WooCommerce's standard webhook topics and hooks.
	 */
	public static function add_topics( $topic_hooks ) {
		$topic_hooks['order.status_changed'][] = self::WCPAY_WEBHOOK_WOOPAY_ORDER_STATUS_CHANGED;

		return $topic_hooks;
	}

	/**
	 * Setup payload for the webhook delivery.
	 *
	 * @param array   $payload     Data to be sent out by the webhook.
	 * @param string  $resource    Type/name of the resource.
	 * @param integer $resource_id ID of the resource.
	 * @param integer $id          ID of the webhook.
	 */
	public static function create_payload( $payload, $resource, $resource_id, $id ) {
		$webhook = wc_get_webhook( $id );
		if ( 0 !== strpos( $webhook->get_delivery_url(), WooPay_Utilities::get_woopay_rest_url( 'merchant-notification' ) ) ) {
			// This is not a WooPay webhook, so we don't need to modify the payload.
			return $payload;
		}

		return [
			'blog_id'      => \Jetpack_Options::get_option( 'id' ),
			'order_id'     => $resource_id,
			'order_status' => $payload['status'],
		];
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

	/**
	 * Undocumented function
	 *
	 * @param array $topic_events List of available topic events.
	 */
	public static function add_event( $topic_events ) {
		$topic_events[] = 'status_changed';

		return $topic_events;
	}

	/**
	 * Trigger webhook delivery.
	 *
	 * @param int    $order_id Order id.
	 * @param string $previous_status the old WooCommerce order status.
	 * @param string $next_status the new WooCommerce order status.
	 * @return void
	 */
	public static function send_webhook( $order_id, $previous_status, $next_status ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_meta( 'is_woopay' ) ) {
			do_action( self::WCPAY_WEBHOOK_WOOPAY_ORDER_STATUS_CHANGED, $order_id, $next_status );
		}
	}

	/**
	 * Removes the webhook if woopay is disabled.
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
