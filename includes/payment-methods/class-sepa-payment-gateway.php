<?php
/**
 * Class Sepa_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

/**
 * SEPA Payment Gateway inheriting from UPE Gateway.
 */
class Sepa_Payment_Gateway extends UPE_Payment_Gateway {

	const STRIPE_ID = 'sepa_debit';

	/**
	 * UPE Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client             - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                         - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service                - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service                   - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service        - Action Scheduler service instance.
	 * @param Session_Rate_Limiter                 $failed_transaction_rate_limiter - Session Rate Limiter instance.
	 * @param WC_Payments_Order_Service            $order_service                   - Order class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		Session_Rate_Limiter $failed_transaction_rate_limiter,
		WC_Payments_Order_Service $order_service
	) {
		$payment_method  = new Sepa_Payment_Method( $token_service );
		$payment_methods = [ $payment_method->get_id() => $payment_method ];
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service, $failed_transaction_rate_limiter, $order_service );

		$this->method_title   = __( 'WooCommerce Payments SEPA Direct Debit', 'woocommerce-payments' );
		$this->title          = __( 'SEPA Direct Debit', 'woocommerce-payments' );
		$this->checkout_title = $this->title;
	}

	/**
	 * Sets the title on checkout correctly before the title is displayed.
	 * Override parent function with static title.
	 *
	 * @param string $title The title of the gateway being filtered.
	 * @param string $id    The id of the gateway being filtered.
	 *
	 * @return string Filtered gateway title.
	 */
	public function maybe_filter_gateway_title( $title, $id ) {
		return $this->title;
	}

	/**
	 * Adds the id and client secret of payment intent needed to mount the UPE element in frontend to WC session.
	 *
	 * @param string $intent_id     The payment intent id.
	 * @param string $client_secret The payment intent client secret.
	 */
	private function add_upe_payment_intent_to_session( string $intent_id = '', string $client_secret = '' ) {
		$cart_hash = 'undefined';

		if ( isset( $_COOKIE['woocommerce_cart_hash'] ) ) {
			$cart_hash = sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_cart_hash'] ) );
		}

		$value = $cart_hash . '-' . $intent_id . '-' . $client_secret;

		WC()->session->set( self::KEY_UPE_PAYMENT_INTENT, $value );
	}

	/**
	 * Returns session key for UPE SEPA payment intents.
	 * Overrides parent.
	 *
	 * @return string
	 */
	public function get_payment_intent_session_key() {
		return self::KEY_UPE_PAYMENT_INTENT . '_' . self::STRIPE_ID;
	}

	/**
	 * Returns session key for UPE SEPA setup intents.
	 * Overrides parent.
	 *
	 * @return string
	 */
	public function get_setup_intent_session_key() {
		return self::KEY_UPE_SETUP_INTENT . '_' . self::STRIPE_ID;
	}

	/**
	 * Removes the payment intent created for UPE from WC session.
	 * Overrides parent maybe?
	 */
	public static function remove_upe_payment_intent_from_session() {
		if ( isset( WC()->session ) ) {
			WC()->session->__unset( self::KEY_UPE_PAYMENT_INTENT . '_' . self::STRIPE_ID );
		}
	}
}
