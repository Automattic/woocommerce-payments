<?php
/**
 * Class Giropay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

/**
 * Giropay Payment Method class extending UPE base class
 */
class Giropay_Payment_Method extends UPE_Payment_Method {

	const STRIPE_ID = 'giropay';

	const TITLE = 'Giropay';

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $can_reuse_payment_method = false;
}
