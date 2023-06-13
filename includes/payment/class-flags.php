<?php
/**
 * Class Flags
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

/**
 * All constants, used for payment flags.
 *
 * When adding constants here, increase multiply the last flag by two.
 *
 * This could live in the `Payment` class as well, but we
 * should try to keep the main class undiluted.
 */
final class Flags {
	/**
	 * Indicates if the payment is merchant-initiated.
	 * If the flag is not present, it means that it's a standard
	 * payment, initiated by a customer action on the site.
	 */
	const MERCHANT_INITIATED = 1;

	/**
	 * Indicates if manual capture should be used for the payment.
	 * If the flag is not present, it's automatic capture.
	 */
	const MANUAL_CAPTURE = 2;

	/**
	 * Indicates if this payment will be recurring.
	 * If not, it is just a single payment.
	 *
	 * @todo: Check if this flag is only required for the initial payment.
	 */
	const RECURRING = 4;

	/**
	 * Indicates whether the payment is related to changing
	 * the payment method for a subscription.
	 */
	const CHANGING_SUBSCRIPTION_PAYMENT_METHOD = 8;

	/**
	 * Whether the payment method should be saved upon payment success.
	 */
	const SAVE_PAYMENT_METHOD_TO_STORE = 16;

	/**
	 * Indicates whether the payment method should be saved to the platform.
	 */
	const SAVE_PAYMENT_METHOD_TO_PLATFORM = 32;
}
