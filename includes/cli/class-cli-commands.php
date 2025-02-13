<?php
/**
 * CLI Commands Registration
 *
 * @package WCPay\CLI
 */

namespace WCPay\CLI;

/**
 * Class responsible for registering all WCPay CLI commands.
 */
class CLI_Commands {
	/**
	 * Initialize CLI commands.
	 */
	public static function init() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		// Load the command class.
		require_once WCPAY_ABSPATH . '/includes/payment-methods/Configs/Scripts/GeneratePaymentMethodConfigsCommand.php';

		\WP_CLI::add_command(
			'wcpay generate-payment-method-configs',
			\WCPay\PaymentMethods\Configs\Scripts\GeneratePaymentMethodConfigsCommand::class
		);
	}
}
