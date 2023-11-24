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
 * @psalm-immutable
 */
class Track_Events extends Base_Constant {
	// UPE toggle events.
	public const UPE_ENABLED                  = 'wcpay_upe_enabled';
	public const UPE_DISABLED                 = 'wcpay_upe_disabled';
	public const SPLIT_UPE_ENABLED            = 'wcpay_split_upe_enabled';
	public const SPLIT_UPE_DISABLED           = 'wcpay_split_upe_disabled';
	public const DEFERRED_INTENT_UPE_ENABLED  = 'wcpay_deferred_intent_upe_enabled';
	public const DEFERRED_INTENT_UPE_DISABLED = 'wcpay_deferred_intent_upe_disabled';

	// Payment method events.
	public const PAYMENT_METHOD_ENABLED  = 'wcpay_payment_method_enabled';
	public const PAYMENT_METHOD_DISABLED = 'wcpay_payment_method_disabled';
}
