<?php
/**
 * Class Flag
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

/**
 * All constants, used for payment flags.
 *
 * When adding constants here, increase multiply the last flag by two.
 *
 * This could live in the `Payment` class as well, but we
 * should try to keep the main class undiluted.
 */
final class Flag {
	/**
	 * Indicates if the payment is merchant-initiated.
	 * If the flag is not present, it means that it's a standard
	 * payment, initiated by a customer action on the site.
	 */
	const MERCHANT_INITIATED = 'MERCHANT_INITIATED';

	/**
	 * Indicates if manual capture should be used for the payment.
	 * If the flag is not present, it's automatic capture.
	 */
	const MANUAL_CAPTURE = 'MANUAL_CAPTURE';

	/**
	 * Indicates if this payment will be recurring.
	 * If not, it is just a single payment.
	 *
	 * @todo: Check if this flag is only required for the initial payment.
	 */
	const RECURRING = 'RECURRING';

	/**
	 * Indicates whether the payment is related to changing
	 * the payment method for a subscription.
	 */
	const CHANGING_SUBSCRIPTION_PAYMENT_METHOD = 'CHANGING_SUBSCRIPTION_PAYMENT_METHOD';

	/**
	 * Whether the payment method should be saved upon payment success.
	 */
	const SAVE_PAYMENT_METHOD_TO_STORE = 'SAVE_PAYMENT_METHOD_TO_STORE';

	/**
	 * Indicates whether the payment method should be saved to the platform.
	 */
	const SAVE_PAYMENT_METHOD_TO_PLATFORM = 'SAVE_PAYMENT_METHOD_TO_PLATFORM';

	/**
	 * Returns all available flags.
	 *
	 * @return string[]
	 */
	public static function get_all() {
		return [
			self::MERCHANT_INITIATED,
			self::MANUAL_CAPTURE,
			self::RECURRING,
			self::CHANGING_SUBSCRIPTION_PAYMENT_METHOD,
			self::SAVE_PAYMENT_METHOD_TO_STORE,
			self::SAVE_PAYMENT_METHOD_TO_PLATFORM,
		];
	}
}
