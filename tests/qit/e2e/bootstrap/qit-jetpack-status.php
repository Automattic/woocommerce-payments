<?php
/**
 * WooPayments QIT E2E Jetpack Connection Status
 *
 * This script loads QIT command class and checks Jetpack connection status.
 *
 * @package WooCommerce\Payments\Tests
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'This script can only be run via WP-CLI' );
}

// Load the QIT command class.
// Note: /qit/bootstrap is a volume mount defined in qit.yml pointing to tests/qit/e2e/bootstrap.
$command_file = '/qit/bootstrap/class-wp-cli-qit-dev-command.php';

if ( ! file_exists( $command_file ) ) {
	WP_CLI::error( 'QIT command file not found: ' . $command_file );
}

require_once $command_file;

// Create instance and check Jetpack connection status.
$qit_command = new WP_CLI_QIT_Dev_Command();
$qit_command->qit_jetpack_status();
