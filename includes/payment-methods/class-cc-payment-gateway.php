<?php
/**
 * Class CC_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payment_Gateway_WCPay;

/**
 * Credit Card Payment method.
 * Right now behaves exactly like WC_Payment_Gateway_WCPay for max compatibility.
 */
class CC_Payment_Gateway extends WC_Payment_Gateway_WCPay {

}
