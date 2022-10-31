<?php
/**
 * Class Platform_Checkout_Order_Success_Page
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Platform_Checkout;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Order;
use WC_Payment_Gateway_WCPay;
use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Order_Service;
use WC_Payments_Utils;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Logger;
use WCPay\Platform_Checkout_Tracker;

/**
 * Handling for when customers come back to the merchant store after platform checkout.
 */
class Platform_Checkout_Order_Success_Page {
	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woopay';

	/**
	 * WCPay order service instance.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * WCPay Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WCPay API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WCPay action scheduler service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Account                  $account The merchant account object.
	 * @param WC_Payments_API_Client               $api_client The payments api client object.
	 * @param WC_Payments_Order_Service            $order_service The order service object.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service The action scheduler service object.
	 */
	public function __construct(
		WC_Payments_Account $account,
		WC_Payments_API_Client $api_client,
		WC_Payments_Order_Service $order_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service
	) {
		$this->account                  = $account;
		$this->order_service            = $order_service;
		$this->payments_api_client      = $api_client;
		$this->action_scheduler_service = $action_scheduler_service;

		add_filter( 'wp', [ $this, 'maybe_process_platform_checkout_redirect' ] );
	}

	/**
	 * Doc comment.
	 *
	 * @return void
	 */
	public function maybe_process_platform_checkout_redirect() {
		if ( ! is_order_received_page() ) {
			return;
		}

		$payment_method = isset( $_GET['wc_payment_method'] ) ? wc_clean( wp_unslash( $_GET['wc_payment_method'] ) ) : '';
		if ( self::GATEWAY_ID !== $payment_method ) {
			return;
		}

		$from_woopay = isset( $_GET['from_woopay'] ) ? filter_var( wc_clean( wp_unslash( $_GET['from_woopay'] ) ), FILTER_VALIDATE_BOOL ) : false;
		if ( ! $from_woopay ) {
			return;
		}

		$order_id  = isset( $_GET['order_id'] ) ? wc_clean( wp_unslash( $_GET['order_id'] ) ) : '';
		$last_4    = isset( $_GET['last4'] ) ? wc_clean( wp_unslash( $_GET['last4'] ) ) : '';
		$intent_id = isset( $_GET['payment_intent'] ) ? wc_clean( wp_unslash( $_GET['payment_intent'] ) ) : '';

		if ( empty( $order_id ) || $order_id <= 0 ) {
			return;
		}

		// We want to be sure we're actually supposed to process something before trying to verify
		// a nonce that may not be present.
		check_ajax_referer( 'woopay_checkout' );

		$order = wc_get_order( $order_id );

		if ( ! $order || ! is_object( $order ) ) {
			return;
		}

		if ( $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
			return;
		}

		$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->add_meta_data( 'is_woopay', true, true );
		$order->add_meta_data( 'last4', $last_4, true );
		$order->set_status( 'pending' );
		$order->save();

		if ( $this->should_process_order_now( $order ) ) {
			$this->process_order_now( $order, $intent_id, $last_4 );

			// Make sure tracks are fired when appropriate.
			$platform_checkout_tracker = new Platform_Checkout_Tracker( WC_Payments::get_wc_payments_http() );
			$platform_checkout_tracker->checkout_order_processed();
		}
	}

	/**
	 * Processes redirect payments.
	 *
	 * @param WC_Order $order The order ID being processed.
	 * @param string   $intent_id The Stripe setup/payment intent ID for the order payment.
	 * @param string   $last_4 The last 4 digits of the card used to pay for the order.
	 *
	 * @return void
	 * @throws Process_Payment_Exception When the payment intent has an error.
	 */
	public function process_order_now( WC_Order $order, string $intent_id, string $last_4 ) {
		// Instantiate variables that may not be defined when we get to the catch clause.
		$status    = '';
		$charge_id = '';

		try {
			$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
			$order->add_meta_data( 'is_woopay', true, true );
			$order->add_meta_data( 'last4', $last_4, true );
			$order->set_status( 'pending' );
			$order->set_payment_method_title( 'WooPay' );
			$order->save();

			Logger::log( "Begin processing WooPay redirect payment for order {$order->get_id()} for the amount of {$order->get_total()}" );

			// Get user/customer for order.
			$intent            = $this->payments_api_client->get_intent( $intent_id );
			$customer_id       = $intent->get_customer_id() ?? '';
			$status            = $intent->get_status();
			$charge            = $intent->get_charge();
			$charge_id         = $charge ? $charge->get_id() : '';
			$currency          = $intent->get_currency();
			$payment_method_id = $intent->get_payment_method_id() ?? '';
			$error             = $intent->get_last_payment_error();

			if ( ! empty( $error ) ) {
				Logger::log( 'Error when processing payment: ' . $error['message'] );
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'woopay_payment_intent_error'
				);
			} else {
				// Update the customer with order data async.
				$this->action_scheduler_service->schedule_job(
					time(),
					WC_Payment_Gateway_WCPay::UPDATE_CUSTOMER_WITH_ORDER_DATA,
					[
						'order_id'     => $order->get_id(),
						'customer_id'  => $customer_id,
						'is_test_mode' => WC_Payments::get_gateway()->is_in_test_mode(),
						'is_woopay'    => true,
					]
				);

				$this->order_service->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method_id, $customer_id, $charge_id, $currency );
				$this->order_service->attach_exchange_info_to_order( $order, $charge_id, $this->account );
				$this->order_service->update_order_status_from_intent( $order, $intent_id, $status, $charge_id );
			}
		} catch ( Exception $e ) {
			Logger::log( 'Error: ' . $e->getMessage() );

			/* translators: localized exception message */
			$message = sprintf( __( 'WooPay payment failed to process: %s', 'woocommerce-payments' ), $e->getMessage() );
			$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id, $message );

			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Used to check whether a process should be processed immediately.
	 *
	 * @param WC_Order $order The order to be processed.
	 *
	 * @return bool  True if order should be processed now, false otherwise.
	 */
	private function should_process_order_now( WC_Order $order ): bool {
		if ( $order->has_downloadable_item() ) {
			return true;
		}

		return false;
	}
}
