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
	 * @var class-string<PaymentMethodDefinitionInterface>[]
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
	 * @param string $definition_class The payment method definition class to register.
	 * @throws \InvalidArgumentException If the class does not exist or does not implement PaymentMethodDefinitionInterface.
	 */
	public function register_payment_method( string $definition_class ): void {
		if ( ! class_exists( $definition_class ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Payment method definition class "%s" does not exist.',
					$definition_class
				)
			);
		}

		$interfaces = class_implements( $definition_class );
		if ( ! isset( $interfaces[ PaymentMethodDefinitionInterface::class ] ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Payment method definition class "%s" must implement %s.',
					$definition_class,
					PaymentMethodDefinitionInterface::class
				)
			);
		}

		$this->payment_methods[ $definition_class::get_id() ] = $definition_class;
	}

	/**
	 * Get all registered payment method definitions.
	 *
	 * @return class-string<PaymentMethodDefinitionInterface>[] All registered payment method definition classes.
	 */
	public function get_all_payment_method_definitions(): array {
		return $this->payment_methods;
	}

	/**
	 * Get all available payment method definitions for a given account and currency.
	 *
	 * @param string $account_country The account country.
	 * @param string $currency The currency.
	 * @return string[] All available payment method definition classes.
	 */
	public function get_available_payment_method_definitions( string $account_country, string $currency ): array {
		return array_filter(
			$this->payment_methods,
			function ( $definition_class ) use ( $account_country, $currency ) {
				return $definition_class::is_available_for( $currency, $account_country );
			}
		);
	}
}
