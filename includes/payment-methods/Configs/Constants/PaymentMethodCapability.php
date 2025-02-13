<?php
/**
 * Payment Method Capability Constants
 *
 * @package WCPay\PaymentMethods\Configs\Constants
 */

namespace WCPay\PaymentMethods\Configs\Constants;

/**
 * Class defining payment method capability constants.
 */
class PaymentMethodCapability {
	/**
	 * Payment method can be saved and reused
	 *
	 * @var string
	 */
	public const TOKENIZATION = 'tokenization';

	/**
	 * Payment method supports refunds
	 *
	 * @var string
	 */
	public const REFUNDS = 'refunds';

	/**
	 * Payment method supports capturing payment later
	 *
	 * @var string
	 */
	public const CAPTURE_LATER = 'capture_later';

	/**
	 * Payment method supports multiple currencies
	 *
	 * @var string
	 */
	public const MULTI_CURRENCY = 'multi_currency';

	/**
	 * Payment method is a Buy Now Pay Later method
	 *
	 * @var string
	 */
	public const BUY_NOW_PAY_LATER = 'buy_now_pay_later';

	/**
	 * Payment method only accepts domestic transactions
	 *
	 * @var string
	 */
	public const DOMESTIC_TRANSACTIONS_ONLY = 'domestic_transactions_only';
}
