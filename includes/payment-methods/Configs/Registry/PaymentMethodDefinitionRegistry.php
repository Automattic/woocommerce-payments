<?php
/**
 * Payment method definition registry.
 *
 * @package WCPay\PaymentMethods\Configs\Registry
 */

namespace WCPay\PaymentMethods\Configs\Registry;

use WCPay\PaymentMethods\Configs\Definitions\AffirmDefinition;
use WCPay\PaymentMethods\Configs\Definitions\AfterpayDefinition;
use WCPay\PaymentMethods\Configs\Definitions\AlipayDefinition;
use WCPay\PaymentMethods\Configs\Definitions\AmazonPayDefinition;
use WCPay\PaymentMethods\Configs\Definitions\ApplePayDefinition;
use WCPay\PaymentMethods\Configs\Definitions\BancontactDefinition;
use WCPay\PaymentMethods\Configs\Definitions\BecsDefinition;
use WCPay\PaymentMethods\Configs\Definitions\EpsDefinition;
use WCPay\PaymentMethods\Configs\Definitions\GiropayDefinition;
use WCPay\PaymentMethods\Configs\Definitions\GooglePayDefinition;
use WCPay\PaymentMethods\Configs\Definitions\GrabPayDefinition;
use WCPay\PaymentMethods\Configs\Definitions\IdealDefinition;
use WCPay\PaymentMethods\Configs\Definitions\KlarnaDefinition;
use WCPay\PaymentMethods\Configs\Definitions\LinkDefinition;
use WCPay\PaymentMethods\Configs\Definitions\MultibancoDefinition;
use WCPay\PaymentMethods\Configs\Definitions\P24Definition;
use WCPay\PaymentMethods\Configs\Definitions\SepaDefinition;
use WCPay\PaymentMethods\Configs\Definitions\SofortDefinition;
use WCPay\PaymentMethods\Configs\Definitions\WechatPayDefinition;
use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;

/**
 * Registry for payment method definitions.
 */
class PaymentMethodDefinitionRegistry {

	/**
	 * Singleton instance.
	 *
	 * @var PaymentMethodDefinitionRegistry|null
	 */
	private static $instance = null;

	/**
	 * List of all available payment method definitions.
	 *
	 * @var array<class-string<PaymentMethodDefinitionInterface>>
	 */
	private $available_definitions = [
		// Add new payment method definitions here.
		AffirmDefinition::class,
		AfterpayDefinition::class,
		AlipayDefinition::class,
		BancontactDefinition::class,
		BecsDefinition::class,
		EpsDefinition::class,
		GiropayDefinition::class,
		GrabPayDefinition::class,
		IdealDefinition::class,
		LinkDefinition::class,
		MultibancoDefinition::class,
		KlarnaDefinition::class,
		P24Definition::class,
		SepaDefinition::class,
		SofortDefinition::class,
		WechatPayDefinition::class,
		ApplePayDefinition::class,
		GooglePayDefinition::class,
	];

	/**
	 * Payment method definitions that have been registered for use.
	 *
	 * @var array<string,class-string<PaymentMethodDefinitionInterface>>
	 */
	private $payment_methods = [];

	/**
	 * Constructor is private to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return PaymentMethodDefinitionRegistry
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the registry by registering all available payment method definitions.
	 *
	 * @return void
	 */
	public function init(): void {
		foreach ( $this->available_definitions as $definition ) {
			$this->register_payment_method( $definition );
		}

		// When Amazon Pay is promoted from feature flag, we can register it directly in the `available_definitions` array.
		if ( \WC_Payments_Features::is_amazon_pay_enabled() ) {
			$this->register_payment_method( AmazonPayDefinition::class );
		}
	}

	/**
	 * Get all available payment method definitions.
	 *
	 * @return array<class-string<PaymentMethodDefinitionInterface>> Array of payment method definition class names.
	 */
	public function get_available_definitions(): array {
		return $this->available_definitions;
	}

	/**
	 * Register a payment method definition.
	 *
	 * @param class-string<PaymentMethodDefinitionInterface> $definition_class The payment method definition class to register.
	 * @throws \InvalidArgumentException If the class does not exist or does not implement PaymentMethodDefinitionInterface.
	 */
	public function register_payment_method( $definition_class ): void {
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

		/**
		 * Ensure type safety for the payment method definition class.
		 *
		 * @var class-string<PaymentMethodDefinitionInterface> $definition_class
		 */
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
