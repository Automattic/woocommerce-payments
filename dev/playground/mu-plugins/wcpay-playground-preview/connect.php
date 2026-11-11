<?php
/**
 * Connect mode: the visitor pastes the Jetpack connection of a sandbox site they already have,
 * and this preview talks to the real WooPayments server as that site.
 *
 * "Connect your store" cannot work in a browser Playground, because WordPress.com has no way
 * to reach a site that lives in a browser tab. Reusing an existing connection needs no callback.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

const WCPAY_PLAYGROUND_CONNECT_PAGE = 'wcpay-playground-connect';

/*
 * This preview shares a blog ID with the site the connection came from. If it synced, WordPress.com
 * would see a second URL for that blog and could put the original site into Jetpack safe mode.
 */
if ( ! defined( 'JETPACK_SYNC_READ_ONLY' ) ) {
	define( 'JETPACK_SYNC_READ_ONLY', true );
}
add_filter( 'jetpack_sync_modules', '__return_empty_array', 999 );

// Sandbox connections only: this keeps WooPayments in test mode whatever account is behind the connection.
add_filter( 'wcpay_dev_mode', '__return_true' );

/*
 * Two WooPayments request headers cannot be sent from a browser Playground. There, PHP's HTTP requests
 * are made by the browser, and WordPress.com only allows a fixed list of request headers across origins.
 * Neither is on it, so every request carrying one fails before it leaves the browser. The server works
 * without them.
 */
add_filter( 'wcpay_api_request_headers', 'wcpay_playground_drop_idempotency_key' );
add_filter( 'http_request_args', 'wcpay_playground_drop_request_initiated_header', 10, 2 );
add_action( 'admin_menu', 'wcpay_playground_add_connect_page' );
add_action( 'admin_bar_menu', 'wcpay_playground_add_connect_admin_bar_node', 100 );
add_action( 'admin_post_wcpay_playground_connect', 'wcpay_playground_handle_connect' );
add_action( 'admin_post_wcpay_playground_disconnect', 'wcpay_playground_handle_disconnect' );

/**
 * Removes the idempotency key before the API client reads its own headers.
 *
 * The client only retries a request it believes is idempotent, and it decides that from this header.
 * Removing it here, and not at the HTTP layer, also turns those retries off.
 *
 * @param array $headers Request headers.
 *
 * @return array
 */
function wcpay_playground_drop_idempotency_key( $headers ) {
	if ( is_array( $headers ) ) {
		unset( $headers['Idempotency-Key'] );
	}

	return $headers;
}

/**
 * Removes the request timing header, which the API client adds after its header filter has run.
 *
 * @param array  $args Request arguments.
 * @param string $url  Request URL.
 *
 * @return array
 */
function wcpay_playground_drop_request_initiated_header( $args, $url ) {
	if ( 'public-api.wordpress.com' === wp_parse_url( $url, PHP_URL_HOST ) && is_array( $args['headers'] ?? null ) ) {
		unset( $args['headers']['X-Request-Initiated'] );
	}

	return $args;
}

/**
 * Whether a pasted connection is stored.
 *
 * @return bool
 */
function wcpay_playground_has_connection(): bool {
	return class_exists( 'Jetpack_Options' ) && (bool) Jetpack_Options::get_option( 'blog_token' );
}

/**
 * URL of the connection page.
 *
 * @param array $args Query arguments to add.
 *
 * @return string
 */
function wcpay_playground_connect_page_url( array $args = [] ): string {
	return add_query_arg( array_merge( [ 'page' => WCPAY_PLAYGROUND_CONNECT_PAGE ], $args ), admin_url( 'admin.php' ) );
}

/**
 * Registers the connection page.
 */
function wcpay_playground_add_connect_page() {
	add_menu_page(
		'Sandbox connection',
		'Sandbox connection',
		'manage_options',
		WCPAY_PLAYGROUND_CONNECT_PAGE,
		'wcpay_playground_render_connect_page',
		'dashicons-admin-links',
		56
	);
}

/**
 * Shows the preview's connection state in the admin bar.
 *
 * @param WP_Admin_Bar $admin_bar Admin bar.
 */
function wcpay_playground_add_connect_admin_bar_node( $admin_bar ) {
	$admin_bar->add_node(
		[
			'id'    => 'wcpay-playground-preview',
			'title' => wcpay_playground_has_connection() ? 'WooPayments: sandbox connection' : 'WooPayments: connect a sandbox',
			'href'  => wcpay_playground_connect_page_url(),
		]
	);
}

/**
 * Stores the pasted connection.
 */
function wcpay_playground_handle_connect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You are not allowed to do this.' );
	}
	check_admin_referer( 'wcpay_playground_connect' );

	if ( ! class_exists( 'Jetpack_Options' ) ) {
		wcpay_playground_redirect_to_connect_page( [ 'wcpay-playground-error' => 'woopayments_inactive' ] );
	}

	// Not sanitized as a whole: it is JSON, and each field is validated below.
	$connection = json_decode( trim( wp_unslash( $_POST['connection'] ?? '' ) ), true );
	$blog_id    = is_array( $connection ) ? (int) ( $connection['blog_id'] ?? 0 ) : 0;
	$blog_token = is_array( $connection ) && is_string( $connection['blog_token'] ?? null ) ? $connection['blog_token'] : '';
	$user_token = is_array( $connection ) && is_string( $connection['user_token'] ?? null ) ? $connection['user_token'] : '';

	// A blog token is `key.secret`; a user token is `key.secret.user_id`, the ID being the owner's on the original site.
	if (
		$blog_id <= 0
		|| ! preg_match( '/^[^.\s]+\.[^.\s]+$/', $blog_token )
		|| ! preg_match( '/^[^.\s]+\.[^.\s]+\.(\d+)$/', $user_token, $user_token_parts )
	) {
		wcpay_playground_redirect_to_connect_page( [ 'wcpay-playground-error' => 'invalid_connection' ] );
	}

	// Jetpack only accepts a user token for the user ID it was issued to, and needs that user to exist here.
	$owner_id = (int) $user_token_parts[1];
	if ( $owner_id <= 0 || ! wcpay_playground_ensure_user_exists( $owner_id ) ) {
		wcpay_playground_redirect_to_connect_page( [ 'wcpay-playground-error' => 'invalid_connection' ] );
	}

	Jetpack_Options::update_option( 'id', $blog_id );
	Jetpack_Options::update_option( 'master_user', $owner_id );
	Jetpack_Options::update_option( 'blog_token', $blog_token );
	Jetpack_Options::update_option( 'user_tokens', [ $owner_id => $user_token ] );

	// The check against the server happens on the next request, once Jetpack has loaded with the new tokens.
	wcpay_playground_redirect_to_connect_page( [ 'wcpay-playground-connected' => 1 ] );
}

/**
 * Removes the stored connection.
 */
function wcpay_playground_handle_disconnect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You are not allowed to do this.' );
	}
	check_admin_referer( 'wcpay_playground_disconnect' );

	if ( class_exists( 'Jetpack_Options' ) ) {
		Jetpack_Options::delete_option( [ 'id', 'master_user', 'blog_token', 'user_tokens' ] );
	}
	if ( class_exists( 'WC_Payments' ) ) {
		WC_Payments::get_account_service()->clear_cache();
	}

	wcpay_playground_redirect_to_connect_page();
}

/**
 * Makes sure a user with the given ID exists, creating a placeholder administrator when it does not.
 *
 * @param int $user_id User ID the pasted user token belongs to.
 *
 * @return bool
 */
function wcpay_playground_ensure_user_exists( int $user_id ): bool {
	if ( get_userdata( $user_id ) ) {
		return true;
	}

	global $wpdb;

	// wp_insert_user() cannot create a user with a chosen ID.
	$inserted = $wpdb->insert(
		$wpdb->users,
		[
			'ID'              => $user_id,
			'user_login'      => 'sandbox-connection-owner',
			'user_pass'       => wp_hash_password( wp_generate_password( 64 ) ),
			'user_nicename'   => 'sandbox-connection-owner',
			'user_email'      => 'sandbox-connection-owner@example.com',
			'user_registered' => gmdate( 'Y-m-d H:i:s' ),
			'display_name'    => 'Sandbox connection owner',
		]
	);
	if ( ! $inserted ) {
		return false;
	}

	clean_user_cache( $user_id );
	( new WP_User( $user_id ) )->set_role( 'administrator' );

	return true;
}

/**
 * Redirects to the connection page and stops.
 *
 * @param array $args Query arguments to add.
 */
function wcpay_playground_redirect_to_connect_page( array $args = [] ) {
	wp_safe_redirect( wcpay_playground_connect_page_url( $args ) );
	exit;
}

/**
 * Renders the connection page.
 */
function wcpay_playground_render_connect_page() {
	// Read-only flags set by this page's own redirects, so no nonce.
	$error          = sanitize_key( $_GET['wcpay-playground-error'] ?? '' );
	$just_connected = isset( $_GET['wcpay-playground-connected'] );

	echo '<div class="wrap" style="max-width: 760px;"><h1>Sandbox connection</h1>';

	if ( ! class_exists( 'WC_Payments' ) || ! class_exists( 'Jetpack_Options' ) ) {
		echo '<div class="notice notice-error inline"><p>WooPayments is not active on this site, so there is nothing to connect.</p></div></div>';
		return;
	}

	if ( 'invalid_connection' === $error ) {
		echo '<div class="notice notice-error inline"><p>That does not look like a connection. Paste the whole line the command below prints, starting with <code>{</code>.</p></div>';
	}

	if ( wcpay_playground_has_connection() ) {
		wcpay_playground_render_connected_state( $just_connected );
	} else {
		wcpay_playground_render_connect_form();
	}

	echo '</div>';
}

/**
 * Renders what the server says about the stored connection, and the button to remove it.
 *
 * @param bool $refresh Whether to ask the server again instead of using cached account data.
 */
function wcpay_playground_render_connected_state( bool $refresh ) {
	$account_service = WC_Payments::get_account_service();
	if ( $refresh ) {
		$account_service->refresh_account_data();
	}

	if ( $account_service->is_stripe_connected() ) {
		$status = $account_service->get_account_status_data();
		printf(
			'<div class="notice notice-success inline"><p>Connected to the sandbox site with blog ID %1$d. Account status: <strong>%2$s</strong>. WooPayments is kept in test mode.</p></div><p><a class="button button-primary" href="%3$s">Open Payments</a></p>',
			(int) Jetpack_Options::get_option( 'id' ),
			esc_html( $status['status'] ?? 'unknown' ),
			esc_url( admin_url( 'admin.php?page=wc-admin&path=/payments/overview' ) )
		);
	} else {
		echo '<div class="notice notice-error inline"><p>The WooPayments server did not return an account for this connection. The tokens may be wrong or out of date, or the sandbox site may not have finished onboarding. Remove the connection and paste a fresh one.</p></div>';
	}

	echo '<p>Webhooks cannot reach this site, so anything that depends on them, such as dispute updates, will not arrive.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="wcpay_playground_disconnect" />';
	wp_nonce_field( 'wcpay_playground_disconnect' );
	echo '<p><button type="submit" class="button">Remove the connection from this preview</button></p></form>';
}

/**
 * Renders the form that takes a pasted connection.
 */
function wcpay_playground_render_connect_form() {
	$export_command = <<<'COMMAND'
wp eval 'echo wp_json_encode( [ "blog_id" => Jetpack_Options::get_option( "id" ), "blog_token" => Jetpack_Options::get_option( "blog_token" ), "user_token" => Jetpack_Options::get_option( "user_tokens" )[ Jetpack_Options::get_option( "master_user" ) ] ] );'
COMMAND;

	echo '<p>"Connect your store" cannot work here: this site runs in your browser, and WordPress.com cannot reach it to finish the connection. Instead, this preview can reuse the connection of a sandbox site you already have.</p>';
	echo '<div class="notice notice-warning inline"><p><strong>Only do this on a pull request you trust, and only with a sandbox site.</strong> The plugin code in this preview comes from the pull request and can read what you paste. The connection is stored in this browser until you remove it or delete this Playground site.</p></div>';
	echo '<h2>1. Print the connection on your sandbox site</h2>';
	echo '<p>Run this with WP-CLI on a sandbox site that is already connected to WooPayments:</p>';
	echo '<pre style="white-space: pre-wrap; background: #fff; border: 1px solid #c3c4c7; padding: 12px;">' . esc_html( $export_command ) . '</pre>';
	echo '<h2>2. Paste it here</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="wcpay_playground_connect" />';
	wp_nonce_field( 'wcpay_playground_connect' );
	echo '<p><textarea name="connection" rows="4" class="large-text code" autocomplete="off" spellcheck="false" placeholder=\'{"blog_id":123,"blog_token":"…","user_token":"…"}\' required></textarea></p>';
	echo '<p><button type="submit" class="button button-primary">Use this connection</button></p></form>';
}
