<?php
/**
 * WP-CLI command to generate payment method configurations.
 *
 * @package WCPay\PaymentMethods\Configs\Scripts
 */

namespace WCPay\PaymentMethods\Configs\Scripts;

use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;
use WCPay\PaymentMethods\Configs\Registry\PaymentMethodDefinitionRegistry;
use WP_CLI;

/**
 * Generates payment method configurations for the frontend.
 */
class GeneratePaymentMethodConfigsCommand {

	/**
	 * Generates the payment method configurations JSON file.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate the payment method configurations
	 *     $ wp wcpay generate-payment-method-configs
	 *
	 * @when after_wp_load
	 */
	public function __invoke() {
		WP_CLI::log( 'Generating payment method configurations...' );

		try {
			// Register all payment method definitions from the registry.
			PaymentMethodDefinitionRegistry::instance()->init();

			$output = [
				'paymentMethods' => $this->get_payment_method_definitions(),
				'capabilities'   => $this->get_capability_constants(),
			];
		} catch ( \Throwable $e ) {
			WP_CLI::error( sprintf( 'Error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
			return;
		}

		// Create the build directory if it doesn't exist.
		$build_dir = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/build/payment-methods';
		if ( ! file_exists( $build_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			mkdir( $build_dir, 0755, true );
		}

		// Write the JSON file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents(
			$build_dir . '/definitions.json',
			wp_json_encode( $output, JSON_PRETTY_PRINT )
		);

		WP_CLI::success( 'Payment method configurations generated successfully.' );
	}

	/**
	 * Get all payment method definitions without translations
	 *
	 * @return array Array of payment method definitions
	 */
	private function get_payment_method_definitions(): array {
		$definitions = [];
		$registry    = PaymentMethodDefinitionRegistry::instance();

		/**
		 * The registry returns an array of class names.
		 *
		 * @var class-string<PaymentMethodDefinitionInterface>[] $payment_method_definitions
		 */
		$payment_method_definitions = $registry->get_all_payment_method_definitions();

		foreach ( $payment_method_definitions as $definition_class ) {
			$definitions[ $definition_class::get_id() ] = [
				'id'                         => $definition_class::get_id(),
				'stripeId'                   => $definition_class::get_stripe_id(),
				'title'                      => $definition_class::get_title(),
				'description'                => $definition_class::get_description(),
				'capabilities'               => $definition_class::get_capabilities(),
				'currencies'                 => $definition_class::get_supported_currencies(),
				'countries'                  => $definition_class::get_supported_countries(),
				'allowsManualCapture'        => $definition_class::allows_manual_capture(),
				'allowsPayLater'             => $definition_class::is_bnpl(),
				'acceptsOnlyDomesticPayment' => $definition_class::accepts_only_domestic_payments(),
				'settingsIcon'               => str_replace( plugin_dir_url( WCPAY_PLUGIN_FILE ), '', $definition_class::get_settings_icon_url() ),
				'icons'                      => [
					'default' => [
						'path' => str_replace( plugin_dir_url( WCPAY_PLUGIN_FILE ), '', $definition_class::get_icon_url() ),
					],
					'dark'    => [
						'path' => str_replace( plugin_dir_url( WCPAY_PLUGIN_FILE ), '', $definition_class::get_dark_icon_url() ),
					],
				],
			];
		}

		return $definitions;
	}

	/**
	 * Get payment method capabilities as constants
	 *
	 * @return array Array of capability constants
	 */
	private function get_capability_constants(): array {
		$reflection = new \ReflectionClass( PaymentMethodCapability::class );
		return $reflection->getConstants();
	}
}
