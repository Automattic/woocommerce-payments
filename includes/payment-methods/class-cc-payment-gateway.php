<?php
/**
 * Class CC_Payment_Gateway
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
 * Credit card Payment method.
 * Right now behaves exactly like WC_Payment_Gateway_WCPay for max compatibility.
 */
class CC_Payment_Gateway extends WC_Payment_Gateway_WCPay {
}
