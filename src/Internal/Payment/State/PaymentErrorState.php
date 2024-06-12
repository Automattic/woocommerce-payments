<?php
/**
 * Class PaymentErrorState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

/**
 * Erroneous state, caused by invalid/non-working customer input.
 *
 * Though this is an erroneous state, it is not unexpected, and
 * represents an error on the buyer's side, which cannot be fixed through code.
 */
class PaymentErrorState extends AbstractPaymentState {

}
