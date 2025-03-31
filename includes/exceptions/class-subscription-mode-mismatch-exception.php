<?php
/**
 * Class Subscription_Mode_Mismatch_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription_Mode_Mismatch_Exception class.
 */
class Subscription_Mode_Mismatch_Exception extends Base_Exception {

	/**
	 * Subscription_Mode_Mismatch_Exception constructor.
	 *
	 * @param string          $message  The exception message.
	 * @param int             $code     The exception code.
	 * @param \Throwable|null $previous The previous exception.
	 */
	public function __construct( $message = 'The subscription mode does not match the current WooPayments mode.', $code = 0, $previous = null ) {
		parent::__construct( $message, 'subscription_mode_mismatch', $code, $previous );
	}
}
