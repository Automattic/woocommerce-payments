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
		require_once WCPAY_ABSPATH . '/includes/payment-methods/Configs/Scripts/class-generate-payment-method-configs-command.php';

		\WP_CLI::add_command(
			'wcpay generate-payment-method-configs',
			\WCPay\PaymentMethods\Configs\Scripts\Generate_Payment_Method_Configs_Command::class
		);
	}
}
