<?php
/**
 * Class Fraud_Ruleset_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing errors in the fraud prevention and ruleset logic.
 */
class Fraud_Ruleset_Exception extends Exception {}
