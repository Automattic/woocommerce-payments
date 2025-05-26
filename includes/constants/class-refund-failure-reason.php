<?php
/**
 * Class Refund_Failure_Reason
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class gives a list of all the possible WCPay refund failure reason constants and their user-friendly messages.
 * Based on Stripe's refund failure reasons: https://docs.stripe.com/api/refunds/object#refund_object-failure_reason
 *
 * @psalm-immutable
 */
class Refund_Failure_Reason extends Base_Constant {
	const LOST_OR_STOLEN_CARD                = 'lost_or_stolen_card';
	const EXPIRED_OR_CANCELED_CARD           = 'expired_or_canceled_card';
	const CHARGE_FOR_PENDING_REFUND_DISPUTED = 'charge_for_pending_refund_disputed';
	const INSUFFICIENT_FUNDS                 = 'insufficient_funds';
	const DECLINED                           = 'declined';
	const MERCHANT_REQUEST                   = 'merchant_request';
	const UNKNOWN                            = 'unknown';

	/**
	 * Get user-friendly message for a failure reason.
	 *
	 * @param string $failure_reason The failure reason code.
	 * @return string The user-friendly message.
	 */
	public static function get_failure_message( string $failure_reason ): string {
		$messages = [
			self::LOST_OR_STOLEN_CARD                => __( 'The card used for the original payment has been reported lost or stolen.', 'woocommerce-payments' ),
			self::EXPIRED_OR_CANCELED_CARD           => __( 'The card used for the original payment has expired or been canceled.', 'woocommerce-payments' ),
			self::CHARGE_FOR_PENDING_REFUND_DISPUTED => __( 'The charge for this refund is being disputed by the customer.', 'woocommerce-payments' ),
			self::INSUFFICIENT_FUNDS                 => __( 'Insufficient funds in your WooPayments balance.', 'woocommerce-payments' ),
			self::DECLINED                           => __( 'The refund was declined by the card issuer.', 'woocommerce-payments' ),
			self::MERCHANT_REQUEST                   => __( 'The refund was canceled at your request.', 'woocommerce-payments' ),
			self::UNKNOWN                            => __( 'An unknown error occurred while processing the refund.', 'woocommerce-payments' ),
		];

		return $messages[ $failure_reason ] ?? $messages[ self::UNKNOWN ];
	}
}
