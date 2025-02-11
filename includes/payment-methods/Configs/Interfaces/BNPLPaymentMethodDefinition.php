<?php
/**
 * BNPL Payment Method Definition Interface
 *
 * @package WCPay\PaymentMethods\Configs\Interfaces
 */

namespace WCPay\PaymentMethods\Configs\Interfaces;

/**
 * Interface for BNPL payment method definitions.
 */
interface BNPLPaymentMethodDefinition extends PaymentMethodDefinitionInterface {
	/**
	 * Get the currency limits for the payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public function get_limits_per_currency(): array;
}
