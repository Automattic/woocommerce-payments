<?php
/**
 * Class Factor
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

/**
 * A class for payment factors.
 *
 * These factors will be used to determine whether to enter the new
 * payment process until it is fully developed and this class gets removed.
 */
class Factor {
	/**
	 * Base flag, used to determine whether the new payment process
	 * can be entered at all, even if no other factors are present.
	 *
	 * Provided by the server, and used only within PaymentProcessingService.
	 * There is no need to provide it to `should_use_new_payment_process`.
	 */
	const NEW_PAYMENT_PROCESS = 'NEW_PAYMENT_PROCESS';

	/**
	 * Zero payment amount, e.g. whether there is no payment to process at all.
	 * This excludes free subscription signups.
	 * Type: Condition
	 */
	const NO_PAYMENT = 'NO_PAYMENT';

	/**
	 * Saved payment method is used.
	 * Type: Condition
	 */
	const USE_SAVED_PM = 'USE_SAVED_PM';

	/**
	 * Requirement to save the payment method (choice or subscriptions).
	 * Type: Condition
	 */
	const SAVE_PM = 'SAVE_PM';

	/**
	 * The order includes a subscription (sign-up).
	 * Type: Condition
	 */
	const SUBSCRIPTION_SIGNUP = 'SUBSCRIPTION_SIGNUP';

	/**
	 * Subscription renewal entry point.
	 * Type: Entry point
	 */
	const SUBSCRIPTION_RENEWAL = 'SUBSCRIPTION_RENEWAL';

	/**
	 * The 3DS/SCA post-authentication process is a separate entry point, not a flow.
	 * Type: Entry point
	 */
	const POST_AUTHENTICATION = 'POST_AUTHENTICATION';

	/**
	 * WooPay: When enabled, it can overwrite the payment method during checkout. We could disable routing when enabled.
	 * Type: Condition
	 */
	const WOOPAY_ENABLED = 'WOOPAY_ENABLED';

	/**
	 * WooPay: WooPay payments (already created intents).
	 * Type: Condition
	 */
	const WOOPAY_PAYMENT = 'WOOPAY_PAYMENT';

	/**
	 * WCPay Subs are working through Subscriptions code, and can be considered together with renewals.
	 * Type: Entry point
	 */
	const WCPAY_SUBSCRIPTION_SIGNUP = 'WCPAY_SUBSCRIPTION_SIGNUP';

	/**
	 * IPP capture (completion).
	 * Currently, it’s mostly independent of the payment process, but could be another entry point, starting with a specific state.
	 * Type: Entry point
	 */
	const IPP_CAPTURE = 'IPP_CAPTURE';

	/**
	 * Stripe Link only works with UPE for now.
	 * So until we (potentially) get to implement UPE, it cannot be a part of the new payment process.
	 * Type: Condition
	 */
	const STRIPE_LINK = 'STRIPE_LINK';

	/**
	 * Deferred UPE requires very little extra code (for both card and LPMs), but thorough testing.
	 * Will become a condition, once there is the one true gateway.
	 * Type: Entry point
	 */
	const DEFERRED_INTENT_SPLIT_UPE = 'DEFERRED_INTENT_SPLIT_UPE';

	/**
	 * Payment request buttons (Google Pay and Apple Pay)
	 * Type: Entry point
	 */
	const PAYMENT_REQUEST = 'PAYMENT_REQUEST';

	/**
	 * Returns all possible factors.
	 *
	 * @return string[]
	 */
	public static function get_all_factors() {
		return [
			self::NEW_PAYMENT_PROCESS,
			self::NO_PAYMENT,
			self::USE_SAVED_PM,
			self::SAVE_PM,
			self::SUBSCRIPTION_SIGNUP,
			self::SUBSCRIPTION_RENEWAL,
			self::POST_AUTHENTICATION,
			self::WOOPAY_ENABLED,
			self::WOOPAY_PAYMENT,
			self::WCPAY_SUBSCRIPTION_SIGNUP,
			self::IPP_CAPTURE,
			self::STRIPE_LINK,
			self::DEFERRED_INTENT_SPLIT_UPE,
			self::PAYMENT_REQUEST,
		];
	}
}
