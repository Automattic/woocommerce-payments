<?php
/**
 * Class Sepa_UPE_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

/**
 * SEPA Payment Gateway inheriting from UPE Gateway.
 */
class Sepa_UPE_Payment_Gateway extends UPE_Payment_Gateway {

	use UPE_Payment_Gateway_Utilities;

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

		$this->set_gateway_properties( $payment_method );
	}

}
