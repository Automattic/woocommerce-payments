<?php
/**
 * Plugin Name: WCPay Worktree Indicator
 * Description: Displays the current worktree ID and port in the WordPress admin bar.
 * Version: 1.0.0
 * Author: Automattic
 *
 * This mu-plugin reads from the per-worktree .env file to display identifying
 * information, helping developers distinguish between multiple running worktrees.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse the .env file from the WooPayments plugin directory.
 *
 * @return array Associative array of env variables.
 */
function wcpay_worktree_parse_env() {
	static $env_cache = null;

	if ( null !== $env_cache ) {
		return $env_cache;
	}

	$env_cache = [];
	$env_file  = ABSPATH . 'wp-content/plugins/woocommerce-payments/.env';

	if ( ! file_exists( $env_file ) ) {
		return $env_cache;
	}

	$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	if ( ! $lines ) {
		return $env_cache;
	}

	foreach ( $lines as $line ) {
		// Skip comments.
		if ( strpos( trim( $line ), '#' ) === 0 ) {
			continue;
		}

		// Parse KEY=value.
		if ( strpos( $line, '=' ) !== false ) {
			list( $key, $value ) = explode( '=', $line, 2 );
			$env_cache[ trim( $key ) ] = trim( $value, '"\'  ' );
		}
	}

	return $env_cache;
}

/**
 * Add worktree indicator to the admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
 */
function wcpay_worktree_admin_bar_indicator( $wp_admin_bar ) {
	$env = wcpay_worktree_parse_env();

	$worktree_id = $env['WORKTREE_ID'] ?? 'default';
	$port        = $env['WORDPRESS_PORT'] ?? '8082';

	// Generate a consistent color from the worktree ID for visual distinction.
	$hash  = crc32( $worktree_id );
	$hue   = $hash % 360;
	$color = "hsl({$hue}, 70%, 45%)";

	$wp_admin_bar->add_node(
		[
			'id'    => 'wcpay-worktree-indicator',
			'title' => sprintf(
				'<span style="
					background: %s;
					color: white;
					padding: 0 8px;
					border-radius: 3px;
					font-size: 12px;
					font-weight: 600;
					margin-right: 5px;
				">%s</span><span style="opacity: 0.7;">:%s</span>',
				esc_attr( $color ),
				esc_html( $worktree_id ),
				esc_html( $port )
			),
			'meta'  => [
				'title' => sprintf( 'Worktree: %s | Port: %s', $worktree_id, $port ),
			],
		]
	);
}
add_action( 'admin_bar_menu', 'wcpay_worktree_admin_bar_indicator', 100 );

/**
 * Add styles to ensure the indicator displays properly.
 */
function wcpay_worktree_admin_bar_styles() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}
	?>
	<style>
		#wp-admin-bar-wcpay-worktree-indicator > .ab-item {
			height: auto !important;
			padding: 0 8px !important;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'wcpay_worktree_admin_bar_styles' );
add_action( 'admin_head', 'wcpay_worktree_admin_bar_styles' );
