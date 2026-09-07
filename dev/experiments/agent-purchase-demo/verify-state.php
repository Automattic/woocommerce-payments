<?php
/**
 * Charge-free CLI checks for demo completion state and lock handling.
 *
 * Run in the local demo store with: wp eval-file verify-state.php
 * Calls the request handler directly; agent-key authentication is not tested.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WCPay_Agent_Demo' ) ) {
	throw new RuntimeException( 'Run this check through WP-CLI with the demo plugin loaded.' );
}
WCPay_Agent_Demo::guard();

$checks = array();
$fixture_keys = array();
$network_attempts = 0;
$block_http = static function () use ( &$network_attempts ) {
	++$network_attempts;
	throw new RuntimeException( 'Network access is forbidden during state verification.' );
};
$enable_demo = static fn() => 'yes';
$check = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$checks[] = $message;
};
$new_record = static function ( string $state, int $expiry ) use ( &$fixture_keys ): array {
	$id = bin2hex( random_bytes( 16 ) );
	$key = 'wcpay_agent_demo_quote_' . $id;
	$record = array(
		'id' => $id, 'owner' => 0, 'state' => $state,
		// No product/order exists: none of these rejection cases should construct one.
		'quote' => array( 'fingerprint' => hash( 'sha256', $id ) ),
		'expires_at' => $expiry, 'order_id' => null,
	);
	if ( ! add_option( $key, $record, '', false ) ) { throw new RuntimeException( 'Could not create isolated state fixture.' ); }
	$fixture_keys[] = $key;
	return array( $key, $record );
};
$complete = static function ( string $id ) {
	$request = new WP_REST_Request( 'POST', '/wcpay-agent-demo/v1/quotes/' . $id . '/complete' );
	$request->set_param( 'id', $id );
	return WCPay_Agent_Demo::request( $request );
};
$reject = static function ( $result, string $message ) use ( $check ): void {
	$check( is_wp_error( $result ) && 'demo_rejected' === $result->get_error_code() && $message === $result->get_error_message(), $message );
};

add_filter( 'pre_http_request', $block_http, PHP_INT_MAX );
add_filter( 'pre_option_wcpay_agent_demo_enabled', $enable_demo );
try {
	list( $key, $record ) = $new_record( 'awaiting_approval', time() + 600 );
	$reject( $complete( $record['id'] ), 'Explicit shopper approval is required; unresolved payments cannot be automatically retried.' );
	$check( $record === get_option( $key ), 'Payment-before-approval preserves the quote.' );
	$check( false === get_option( $key . '_lock' ), 'Rejected completion releases its own lock.' );

	list( $key, $record ) = $new_record( 'approved', time() + 600 );
	$lock_value = 'approval-owned-test-lock';
	if ( ! add_option( $key . '_lock', $lock_value, '', false ) ) { throw new RuntimeException( 'Could not establish approval lock fixture.' ); }
	$reject( $complete( $record['id'] ), 'This purchase is already being processed.' );
	$check( $record === get_option( $key ), 'Completion cannot overwrite a quote while approval holds its lock.' );
	$check( $lock_value === get_option( $key . '_lock' ), 'Rejected completion does not release another operation\'s lock.' );

	list( $key, $record ) = $new_record( 'approved', time() - 1 );
	$reject( $complete( $record['id'] ), 'Quote expired. Request a new quote and approval.' );
	$check( $record === get_option( $key ), 'Expired approved quote remains associated with no order.' );
	$check( false === get_option( $key . '_lock' ), 'Expiry rejection releases its own lock.' );
	$check( 0 === $network_attempts, 'State checks made no network requests.' );
} finally {
	foreach ( $fixture_keys as $key ) { delete_option( $key . '_lock' ); delete_option( $key ); }
	remove_filter( 'pre_http_request', $block_http, PHP_INT_MAX );
	remove_filter( 'pre_option_wcpay_agent_demo_enabled', $enable_demo );
}
WP_CLI::line( wp_json_encode( array( 'checks' => $checks, 'limits' => 'Does not execute approval page redirects, a concurrent HTTP race, authentication, or payments.' ), JSON_PRETTY_PRINT ) );
