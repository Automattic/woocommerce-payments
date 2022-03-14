<?php
/**
 * Class UPE_Payment_Gateway_Utilities
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Methods;

use WC_Payments_API_Client;
use WC_Payments_Account;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments_Action_Scheduler_Service;
use Session_Rate_Limiter;
use WC_Payments_Order_Service;

/**
 * Utility functions for UPE payment gateways
 */
class UPE_Single_Method_Payment_Gateway extends UPE_Payment_Gateway {

	/**
	 * Stripe payment method type ID.
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * UPE Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param UPE_Payment_Method                   $payment_method                  - Specific UPE_Payment_Method instance for class.
	 * @param WC_Payments_API_Client               $payments_api_client             - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                         - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service                - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service                   - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service        - Action Scheduler service instance.
	 * @param Session_Rate_Limiter                 $failed_transaction_rate_limiter - Session Rate Limiter instance.
	 * @param WC_Payments_Order_Service            $order_service                   - Order class instance.
	 */
	public function __construct(
		UPE_Payment_Method $payment_method,
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		Session_Rate_Limiter $failed_transaction_rate_limiter,
		WC_Payments_Order_Service $order_service
	) {
		$payment_methods = [ $payment_method->get_id() => $payment_method ];
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service, $payment_methods, $failed_transaction_rate_limiter, $order_service );

		$this->stripe_id = $payment_method->get_id();
		$this->title     = $payment_method->get_title();

		add_action( "wc_ajax_wcpay_create_payment_intent_$this->stripe_id", [ $this, 'create_payment_intent_ajax' ] );
		add_action( "wc_ajax_wcpay_update_payment_inten_ $this->stripe_id", [ $this, 'update_payment_intent_ajax' ] );
		add_action( "wc_ajax_wcpay_init_setup_intent_$this->stripe_id", [ $this, 'init_setup_intent_ajax' ] );

		if ( 'card' !== $this->stripe_id ) {
			$this->id           = self::GATEWAY_ID . '_' . $this->stripe_id;
			$this->method_title = "WooCommerce Payments ($this->title)";
		} else {
			// Only add these filters once.
			add_action( 'wc_ajax_wcpay_log_payment_error', [ $this, 'log_payment_error_ajax' ] );
			add_action( 'wp_ajax_save_upe_appearance', [ $this, 'save_upe_appearance_ajax' ] );
			add_action( 'wp_ajax_nopriv_save_upe_appearance', [ $this, 'save_upe_appearance_ajax' ] );
			add_action( 'switch_theme', [ $this, 'clear_upe_appearance_transient' ] );

			add_action( 'wp', [ $this, 'maybe_process_upe_redirect' ] );

			add_action( 'woocommerce_order_payment_status_changed', [ __CLASS__, 'remove_upe_payment_intent_from_session' ], 10, 0 );
			add_action( 'woocommerce_after_account_payment_methods', [ $this, 'remove_upe_setup_intent_from_session' ], 10, 0 );

			if ( is_admin() ) {
				add_filter( 'woocommerce_gateway_title', [ $this, 'maybe_filter_gateway_title' ], 10, 2 );
			}
		}
	}

}
