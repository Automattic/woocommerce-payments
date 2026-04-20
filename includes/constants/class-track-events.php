<?php
/**
 * Class Track_Events
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Event names for WooCommerce Analytics.
 *
 * @phpstan-immutable
 */
class Track_Events extends Base_Constant {
	// Payment method events.
	public const PAYMENT_METHOD_ENABLED                     = 'wcpay_payment_method_enabled';
	public const PAYMENT_METHOD_DISABLED                    = 'wcpay_payment_method_disabled';
	public const PAYMENT_METHOD_PROMOTION_DISMISSED         = 'wcpay_payment_method_promotion_dismissed';
	public const PAYMENT_METHOD_PROMOTION_ACTIVATED         = 'wcpay_payment_method_promotion_activated';
	public const PAYMENT_METHOD_PROMOTION_ACTIVATION_FAILED = 'wcpay_payment_method_promotion_activation_failed';
}
