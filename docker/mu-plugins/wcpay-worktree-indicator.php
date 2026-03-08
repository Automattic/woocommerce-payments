<?php
/**
 * Plugin Name: WCPay Worktree Indicator
 * Description: Displays the current worktree ID and port in the WordPress admin bar so developers can distinguish between multiple running checkouts.
 * Version: 1.0.0
 * Author: Automattic
 *
 * This mu-plugin reads from the per-checkout .env file to display identifying
 * information in the admin toolbar, helping developers working with multiple
 * simultaneous checkouts or git worktrees on the same machine.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse the .env file from the WooPayments plugin directory.
 *
 * @return array<string, string> Associative array of env variables.
 */
function wcpay_worktree_parse_env(): array {
	static $env_cache = null;

	if ( null !== $env_cache ) {
		return $env_cache;
	}

	$env_cache = array();
	$env_file  = ABSPATH . 'wp-content/plugins/woocommerce-payments/.env';

	if ( ! file_exists( $env_file ) ) {
		return $env_cache;
	}

	$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	if ( ! $lines ) {
		return $env_cache;
	}

	foreach ( $lines as $line ) {
		$line = trim( $line );
		// Skip comments and lines without an = sign.
		if ( strpos( $line, '#' ) === 0 || strpos( $line, '=' ) === false ) {
			continue;
		}
		[ $key, $value ] = explode( '=', $line, 2 );
		$env_cache[ trim( $key ) ] = trim( $value );
	}

	return $env_cache;
}

/**
 * Add a node to the admin bar showing the current worktree / checkout identity.
 *
 * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
 */
function wcpay_worktree_admin_bar_item( \WP_Admin_Bar $wp_admin_bar ): void {
	$env         = wcpay_worktree_parse_env();
	$worktree_id = $env['WORKTREE_ID'] ?? '';
	$port        = $env['WORDPRESS_PORT'] ?? '';

	if ( empty( $worktree_id ) && empty( $port ) ) {
		return;
	}

	$label  = '🌿 ';
	$label .= ! empty( $worktree_id ) ? esc_html( $worktree_id ) : 'wcpay';
	if ( ! empty( $port ) ) {
		$label .= ' :' . esc_html( $port );
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'wcpay-worktree-indicator',
			'title' => $label,
			'href'  => false,
			'meta'  => array(
				'title' => 'WooPayments checkout: ' . esc_attr( $worktree_id ) . ( ! empty( $port ) ? ' (port ' . esc_attr( $port ) . ')' : '' ),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'wcpay_worktree_admin_bar_item', 100 );
