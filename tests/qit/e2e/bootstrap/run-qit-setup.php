<?php
/**
 * WooPayments QIT E2E Setup - Load Commands and Run Setup
 *
 * This script loads QIT WP-CLI commands and runs the setup in a single execution.
 * This is necessary because WP-CLI commands registered via eval-file are not
 * persisted across separate wp command invocations.
 *
 * @package WooCommerce\Payments\Tests
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'This script can only be run via WP-CLI' );
}

// Get arguments from environment variables (set by setup.sh).
$site_id    = getenv( 'E2E_JP_SITE_ID' );
$blog_token = getenv( 'E2E_JP_BLOG_TOKEN' );
$user_token = getenv( 'E2E_JP_USER_TOKEN' );

if ( empty( $site_id ) ) {
	WP_CLI::error( 'E2E_JP_SITE_ID environment variable not set' );
}

// Load the QIT command class.
$command_file = '/qit/tests/e2e/woocommerce-payments/local/bootstrap/class-wp-cli-qit-dev-command.php';

if ( ! file_exists( $command_file ) ) {
	WP_CLI::error( 'QIT command file not found: ' . $command_file );
}

require_once $command_file;

// Create instance and run setup directly.
$qit_command = new WP_CLI_QIT_Dev_Command();
$qit_command->qit_setup(
	[ $site_id ],
	[
		'blog_token' => $blog_token,
		'user_token' => $user_token,
	]
);
