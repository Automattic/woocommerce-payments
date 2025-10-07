<?php
/**
 * WooPayments QIT E2E Status Check
 *
 * This script loads QIT command class and runs status check in a single execution.
 *
 * @package WooCommerce\Payments\Tests
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'This script can only be run via WP-CLI' );
}

// Load the QIT command class.
$command_file = '/qit/tests/e2e/woocommerce-payments/local/bootstrap/class-wp-cli-qit-dev-command.php';

if ( ! file_exists( $command_file ) ) {
	WP_CLI::error( 'QIT command file not found: ' . $command_file );
}

require_once $command_file;

// Create instance and run status check directly.
$qit_command = new WP_CLI_QIT_Dev_Command();
$qit_command->qit_status();
