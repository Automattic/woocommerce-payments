<?php
/**
 * Class OrderService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Order;
use WC_Payments_Account;
use WC_Payments_API_Abstract_Intention;
use WC_Payments_API_Charge;
use WC_Payments_API_Payment_Intention;
use WC_Payments_Explicit_Price_Formatter;
use WC_Payments_Features;
use WC_Payments_Order_Service;
use WC_Payments_Utils;
use WCPay\Constants\Payment_Type;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Service for managing orders.
 *
 * This service's public methods should only require order IDs rather than objects,
 * avoiding direct access to the `$order` object witnin `src` (except for this class).
 */
class OrderService {
	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Legacy order service.
	 *
	 * TEMPORARY: The legacy order service should be migrated here
	 * once `WC_Order` parameters have been converted to order IDS.

	 * @todo: Add a GH issue link here.
	 * @var WC_Payments_Order_Service
	 */
	private $legacy_service;

	/**
	 * Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Hooks proxy.
	 *
	 * @var HooksProxy
	 */
	private $hooks_proxy;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_Order_Service $legacy_service The legacy order service.
	 * @param LegacyProxy               $legacy_proxy   Proxy for accessing non-src functionality.
	 * @param WC_Payments_Account       $account        Account object.
	 * @param HooksProxy                $hooks_proxy    Proxy for triggering hooks.
	 */
	public function __construct(
		WC_Payments_Order_Service $legacy_service,
		LegacyProxy $legacy_proxy,
		WC_Payments_Account $account,
		HooksProxy $hooks_proxy
	) {
		$this->legacy_service = $legacy_service;
		$this->legacy_proxy   = $legacy_proxy;
		$this->account        = $account;
		$this->hooks_proxy    = $hooks_proxy;
	}

	/**
	 * Retrieves the order object.
	 *
	 * Please restrain from using this method!
	 * It can only be used to (temporarily) provide the order object
	 * to legacy (`includes`) services, which are not adapted to work
	 * with order IDs yet.
	 *
	 * @see https://github.com/Automattic/woocommerce-payments/issues/7367
	 * @param int $order_id ID of the order.
	 * @return WC_Order Order object.
	 * @throws Order_Not_Found_Exception If the order could not be found.
	 */
	public function _deprecated_get_order( int $order_id ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		return $this->get_order( $order_id );
	}

	/**
	 * Set the payment metadata for payment method id.
	 *
	 * @param int    $order_id          ID of the order.
	 * @param string $payment_method_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_payment_method_id( int $order_id, string $payment_method_id ) {
		$this->legacy_service->set_payment_method_id_for_order( $order_id, $payment_method_id );
	}

	/**
	 * Generates payment metadata from order details.
	 *
	 * @param int          $order_id     ID of the order.
	 * @param Payment_Type $payment_type Type of the payment (recurring or not).
	 * @return array                     The metadat athat will be sent to the server.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_payment_metadata( int $order_id, Payment_Type $payment_type = null ) {
		$order = $this->get_order( $order_id );

		$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email    = sanitize_email( $order->get_billing_email() );
		$metadata = [
			'customer_name'        => $name,
			'customer_email'       => $email,
			'site_url'             => esc_url( get_site_url() ),
			'order_id'             => $order->get_id(),
			'order_number'         => $order->get_order_number(),
			'order_key'            => $order->get_order_key(),
			'payment_type'         => $payment_type,
			'checkout_type'        => $order->get_created_via(),
			'client_version'       => WCPAY_VERSION_NUMBER,
			'subscription_payment' => 'no',
		];

		if (
			'recurring' === (string) $payment_type
			&& $this->legacy_proxy->call_function( 'function_exists', 'wcs_order_contains_subscription' )
			&& $this->legacy_proxy->call_function( 'wcs_order_contains_subscription', $order, 'any' )
		) {
			$use_stripe_billing = $this->legacy_proxy->call_static( WC_Payments_Features::class, 'should_use_stripe_billing' );
			$is_renewal         = $this->legacy_proxy->call_function( 'wcs_order_contains_renewal', $order );

			$metadata['subscription_payment'] = $is_renewal ? 'renewal' : 'initial';
			$metadata['payment_context']      = $use_stripe_billing ? 'wcpay_subscription' : 'regular_subscription';
		}

		return $this->hooks_proxy->apply_filters( 'wcpay_metadata_from_order', $metadata, $order, $payment_type );
	}

	/**
	 * Imports the data from an order to a payment context.
	 *
	 * @param int            $order_id ID of the order.
	 * @param PaymentContext $context  A payment context, awaiting order data.
	 * @throws Order_Not_Found_Exception
	 */
	public function import_order_data_to_payment_context( int $order_id, PaymentContext $context ) {
		$order = $this->get_order( $order_id );

		$currency = strtolower( $order->get_currency() );
		$amount   = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
		$user     = $order->get_user();

		$context->set_currency( $currency );
		$context->set_amount( $amount );
		// In case we don't have user, we are setting user id to be 0 which could cause more harm since we don't have a real user.
		$context->set_user_id( $user->ID ?? null );
	}

	/**
	 * God method for updating orders once a payment has succeeded.
	 *
	 * @param int                                $order_id ID of the order that was just paid.
	 * @param WC_Payments_API_Abstract_Intention $intent   Remote object. To be abstracted.
	 * @param PaymentContext                     $context  Context for the payment.
	 * @throws Order_Not_Found_Exception
	 */
	public function update_order_from_successful_intent(
		int $order_id,
		WC_Payments_API_Abstract_Intention $intent,
		PaymentContext $context
	) {
		$order = $this->get_order( $order_id );

		$charge                 = null;
		$charge_id              = null;
		$payment_transaction_id = null;
		if ( $intent instanceof WC_Payments_API_Payment_Intention ) {
			$charge                 = $intent->get_charge();
			$charge_id              = $intent->get_charge()->get_id();
			$payment_transaction    = $charge ? $charge->get_balance_transaction() : null;
			$payment_transaction_id = $payment_transaction['id'] ?? '';
		}

		$this->legacy_service->attach_intent_info_to_order__legacy(
			$order,
			$intent->get_id(),
			$intent->get_status(),
			$context->get_payment_method()->get_id(),
			$context->get_customer_id(),
			$charge_id,
			$context->get_currency(),
			$payment_transaction_id,
		);

		$this->legacy_service->attach_transaction_fee_to_order( $order, $charge );
		$this->legacy_service->update_order_status_from_intent( $order, $intent );
		$this->set_mode( $order_id, $context->get_mode() );

		if ( ! is_null( $charge ) ) {
			$this->attach_exchange_info_to_order( $order_id, $charge );
		}
	}

	/**
	 * Sets the '_wcpay_mode' meta data on an order.
	 *
	 * @param string $order_id The order id.
	 * @param string $mode  Mode from the context.
	 * @throws Order_Not_Found_Exception
	 */
	public function set_mode( string $order_id, string $mode ) : void {
		$order = $this->get_order( $order_id );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, $mode );
		$order->save_meta_data();
	}

	/**
	 * Gets the '_wcpay_mode' meta data on an order.
	 *
	 * @param string $order_id The order id.
	 *
	 * @return string The mode.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_mode( string $order_id ) : string {
		$order = $this->get_order( $order_id );
		return $order->get_meta( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, true );
	}

	/**
	 * Updates the order with the necessary details whenever an intent requires action.
	 *
	 * @param int                                $order_id ID of the order.
	 * @param WC_Payments_API_Abstract_Intention $intent   Remote object. To be abstracted soon.
	 * @param PaymentContext                     $context  Context for the payment.
	 * @throws Order_Not_Found_Exception
	 */
	public function update_order_from_intent_that_requires_action(
		int $order_id,
		WC_Payments_API_Abstract_Intention $intent,
		PaymentContext $context
	) {
		$order = $this->get_order( $order_id );

		$this->legacy_service->attach_intent_info_to_order__legacy(
			$order,
			$intent->get_id(),
			$intent->get_status(),
			$context->get_payment_method()->get_id(),
			$context->get_customer_id(),
			'',
			$context->get_currency()
		);

		$this->legacy_service->update_order_status_from_intent( $order, $intent );
	}

	/**
	 * Given the charge data, checks if there was an exchange and adds it to the given order as metadata
	 *
	 * @param int                    $order_id The order to update.
	 * @param WC_Payments_API_Charge $charge   Charge object.
	 * @throws Order_Not_Found_Exception
	 */
	public function attach_exchange_info_to_order( int $order_id, WC_Payments_API_Charge $charge ) {
		$order = $this->get_order( $order_id );

		// This is a good example of something, which should be a service.
		$currency_store   = $this->legacy_proxy->call_function( 'get_option', 'woocommerce_currency' );
		$currency_store   = strtolower( $currency_store );
		$currency_order   = strtolower( $order->get_currency() );
		$currency_account = strtolower( $this->account->get_account_default_currency() );

		// If the default currency for the store is different from the currency for the merchant's Stripe account,
		// the conversion rate provided by Stripe won't make sense, so we should not attach it to the order meta data
		// and instead we'll rely on the _wcpay_multi_currency_order_exchange_rate meta key for analytics.
		if ( $currency_store !== $currency_account ) {
			return;
		}

		// If the account and order currency are the same, there was no exchange.
		if ( $currency_order === $currency_account ) {
			return;
		}

		// Without the balance transaction, we cannot check the exchange rate.
		$transaction   = $charge->get_balance_transaction();
		$exchange_rate = $transaction['exchange_rate'] ?? null;
		if ( is_null( $exchange_rate ) ) {
			return;
		}

		// This is a pure method and can remain static.
		$exchange_rate = WC_Payments_Utils::interpret_string_exchange_rate( $exchange_rate, $currency_order, $currency_account );
		$order->update_meta_data( '_wcpay_multi_currency_stripe_exchange_rate', $exchange_rate );
		$order->save_meta_data();
	}

	/**
	 * Gets currently attached intent ID of the order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string|null Intent ID for the order. Null if no intent ID attached to order.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_intent_id( int $order_id ): ?string {
		$order     = $this->get_order( $order_id );
		$intent_id = (string) $order->get_meta( '_intent_id', true );
		if ( empty( $intent_id ) ) {
			return null;
		}
		return $intent_id;
	}

	/**
	 * Gets cart hash for the given order ID.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return string Cart hash for the order.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_cart_hash( int $order_id ): string {
		$order = $this->get_order( $order_id );
		return $order->get_cart_hash();
	}

	/**
	 * Gets customer ID for the given order ID.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return int Customer ID for the order.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_customer_id( int $order_id ): int {
		return $this->get_order( $order_id )->get_customer_id();
	}

	/**
	 * Checks if the order has one of paid statuses.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return bool True if the order has one of paid statuses, false otherwise.
	 * @throws Order_Not_Found_Exception
	 */
	public function is_paid( int $order_id ): bool {
		return $this->get_order( $order_id )
			->has_status(
				$this->legacy_proxy->call_function( 'wc_get_is_paid_statuses' )
			);
	}

	/**
	 * Checks if the order has one of pending statuses.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return bool True if the order has one of pending statuses, false otherwise.
	 * @throws Order_Not_Found_Exception
	 */
	public function is_pending( int $order_id ) {
		return $this->get_order( $order_id )
			->has_status(
				$this->legacy_proxy->call_function( 'wc_get_is_pending_statuses' )
			);
	}

	/**
	 * Validate phone number provided in the order.
	 *
	 * @param  int $order_id ID of the order.
	 *
	 * @return bool
	 * @throws Order_Not_Found_Exception
	 */
	public function is_valid_phone_number( int $order_id ): bool {
		$order = $this->get_order( $order_id );
		return strlen( $order->get_billing_phone() ) < 20;
	}

	/**
	 * Adds note to order.
	 *
	 * @param int    $order_id  ID of the order.
	 * @param string $note      Note content.
	 *
	 * @return int Note ID.
	 * @throws Order_Not_Found_Exception
	 */
	public function add_note( int $order_id, string $note ): int {
		return $this->get_order( $order_id )->add_order_note( $note );
	}

	/**
	 * Adds a note to order when rate limiter is triggered.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return int Note ID.
	 * @throws Order_Not_Found_Exception
	 */
	public function add_rate_limiter_note( int $order_id ) {
		$order = $this->get_order( $order_id );

		$wc_price       = $this->legacy_proxy->call_function( 'wc_price', $order->get_total(), [ 'currency' => $order->get_currency() ] );
		$explicit_price = $this->legacy_proxy->call_static(
			WC_Payments_Explicit_Price_Formatter::class,
			'get_explicit_price',
			$wc_price,
			$order
		);

		$note = sprintf(
			$this->legacy_proxy->call_static(
				WC_Payments_Utils::class,
				'esc_interpolated_html',
				/* translators: %1: the failed payment amount */
				__(
					'A payment of %1$s <strong>failed</strong> to complete because of too many failed transactions. A rate limiter was enabled for the user to prevent more attempts temporarily.',
					'woocommerce-payments'
				),
				[
					'strong' => '<strong>',
				]
			),
			$explicit_price
		);

		return $order->add_order_note( $note );
	}

	/**
	 * Deletes order.
	 *
	 * @param int  $order_id     ID of the order.
	 * @param bool $force_delete Should the order be deleted permanently.
	 *
	 * @return bool Result of the deletion.
	 * @throws Order_Not_Found_Exception
	 */
	public function delete( int $order_id, bool $force_delete = false ): bool {
		return $this->get_order( $order_id )->delete( $force_delete );
	}

	/**
	 * Retrieves the order object.
	 *
	 * This method should be only used internally within this service.
	 * Other `src` methods and services should not access and manipulate
	 * order data directly, utilizing this service instead.
	 *
	 * Unlike the legacy service, this one only accepts integer IDs,
	 * and returns only the `WC_Order` object, no refunds.
	 *
	 * @param int $order_id ID of the order.
	 * @return WC_Order Order object.
	 * @throws Order_Not_Found_Exception If the order could not be found.
	 */
	protected function get_order( int $order_id ): WC_Order {
		$order = $this->legacy_proxy->call_function( 'wc_get_order', $order_id );
		if ( ! $order instanceof WC_Order ) {
			throw new Order_Not_Found_Exception(
				sprintf(
					// Translators: %d is the ID of an order.
					__( 'The requested order (ID %d) was not found.', 'woocommerce-payments' ),
					$order_id
				),
				'order_not_found'
			);
		}
		return $order;
	}

}
