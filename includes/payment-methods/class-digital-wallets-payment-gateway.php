<?php
/**
 * Class Digital_Wallets_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;

/**
 * Digital Wallets
 */
class Digital_Wallets_Payment_Gateway extends WC_Payment_Gateway_WCPay {
	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments_digital_wallets';

	const METHOD_ENABLED_KEY = 'digital_wallets_enabled';

	/**
	 * Digital Wallets Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                  - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service         - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service            - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service - Action Scheduler service instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, WC_Payments_Customer_Service $customer_service, WC_Payments_Token_Service $token_service, WC_Payments_Action_Scheduler_Service $action_scheduler_service ) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service );
		$this->method_title       = __( 'WooCommerce Payments - Digital Wallets', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via Digital Wallets.', 'woocommerce-payments' );
		$this->title              = __( 'Digital Wallets', 'woocommerce-payments' );
		$this->description        = __( 'Mandate Information.', 'woocommerce-payments' );
	}
}
