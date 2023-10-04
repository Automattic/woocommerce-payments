<?php
/**
 * Class SystemErrorState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

/**
 * Erroneous state, caused by a bug or an edge case.
 *
 * Consider this state an exception: Ideally it would never be entered,
 * but in case any sort of software issue arrises, it will be used.
 */
class SystemErrorState extends AbstractPaymentState {

}
