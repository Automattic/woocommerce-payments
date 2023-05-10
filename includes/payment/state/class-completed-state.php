<?php
/**
 * Class Completed_State
 *
 * @package WooCommerce\Payments
 */

 namespace WCPay\Payment\State;

use Exception;
use WC_Payments;
use WCPay\Payment\Duplicate_Payment_Prevention_Service;
use WCPay\Payment\Payment;
use WCPay\Payment_Process\Step;
use WCPay\Session_Rate_Limiter;

/**
 * Represents a payment in the complete state, which requires no further processing.
 */
final class Completed_State extends Payment_State {

}
