<?php
/**
 * Payment method definition registry.
 *
 * @package WCPay\PaymentMethods
 */

namespace WCPay\PaymentMethods;

use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;

/**
 * Registry for payment method definitions.
 */
class Payment_Method_Definition_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Payment_Method_Definition_Registry|null
	 */
	private static $instance = null;

	/**
	 * Payment method definitions.
	 *
	 * @var PaymentMethodDefinitionInterface[]
	 */
	private $payment_methods = [];

	/**
	 * Get the singleton instance.
	 *
	 * @return Payment_Method_Definition_Registry
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a payment method definition.
	 *
	 * @param PaymentMethodDefinitionInterface $payment_method The payment method definition to register.
	 */
	public function register_payment_method( PaymentMethodDefinitionInterface $payment_method ): void {
		$this->payment_methods[ $payment_method->get_id() ] = $payment_method;
	}

	/**
	 * Get a payment method definition by its ID.
	 *
	 * @param string $id The ID of the payment method definition to get.
	 * @return PaymentMethodDefinitionInterface|null The payment method definition, or null if it doesn't exist.
	 */
	public function get_payment_method_definition( string $id ): ?PaymentMethodDefinitionInterface {
		return $this->payment_methods[ $id ] ?? null;
	}

	/**
	 * Get all registered payment method definitions.
	 *
	 * @return PaymentMethodDefinitionInterface[] All registered payment method definitions.
	 */
	public function get_all_payment_method_definitions(): array {
		return $this->payment_methods;
	}

	/**
	 * Get all available payment method definitions for a given account and currency.
	 *
	 * @param string $account_country The account country.
	 * @param string $currency The currency.
	 * @return PaymentMethodDefinitionInterface[] All available payment method definitions.
	 */
	public function get_available_payment_method_definitions( string $account_country, string $currency ): array {
		return array_filter(
			$this->payment_methods,
			function ( $method ) use ( $account_country, $currency ) {
				$supported_currencies = $method->get_supported_currencies();
				$supported_countries  = $method->get_supported_countries();

				return (
					( empty( $supported_currencies ) || in_array( $currency, $supported_currencies, true ) ) &&
					( empty( $supported_countries ) || in_array( $account_country, $supported_countries, true ) )
				);
			}
		);
	}
}
