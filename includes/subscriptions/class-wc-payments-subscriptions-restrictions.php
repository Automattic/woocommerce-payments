<?php
/**
 * WC_Payments_Subscriptions_Restrictions class
 *
 * Restricts subscription management capabilities and hides UI when
 * bundled subscriptions are disabled in WooPayments 10.2+.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles restrictions for bundled subscriptions when UI is disabled.
 */
class WC_Payments_Subscriptions_Restrictions {

	/**
	 * Initialize restrictions.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Remove subscription capabilities to prevent editing/creating.
		add_filter( 'user_has_cap', [ $this, 'remove_subscription_capabilities' ], 10, 4 );

		// Hide subscription admin menu items.
		add_action( 'admin_menu', [ $this, 'remove_subscription_menus' ], 999 );

		// Redirect subscription edit/list pages to plugins page with notice.
		add_action( 'admin_init', [ $this, 'redirect_subscription_pages' ] );

		// Remove subscription product type from product editor.
		add_filter( 'product_type_selector', [ $this, 'remove_subscription_product_types' ] );

		// Prevent creating subscriptions via REST API.
		add_filter( 'woocommerce_rest_check_permissions', [ $this, 'block_subscription_rest_api' ], 10, 4 );
	}

	/**
	 * Remove subscription-related capabilities from all users.
	 *
	 * Since shop_subscription post type uses 'capability_type' => 'shop_order',
	 * we need to check for both shop_order capabilities and custom meta capabilities
	 * used by subscriptions-core.
	 *
	 * @param array $all_capabilities All capabilities.
	 * @param array $capabilities Required capabilities.
	 * @param array $arguments Capability context arguments.
	 * @return array Modified capabilities.
	 */
	public function remove_subscription_capabilities( $all_capabilities, $capabilities, $arguments ) {
		// Custom meta capabilities used by subscriptions-core.
		$custom_meta_capabilities = [
			'edit_shop_subscription_status',
			'edit_shop_subscription_line_items',
			'edit_shop_subscription_payment_method',
			'toggle_shop_subscription_auto_renewal',
		];

		// Check if the current capability check is for a subscription-related custom meta cap.
		if ( ! empty( $arguments[0] ) && in_array( $arguments[0], $custom_meta_capabilities, true ) ) {
			// Deny the custom meta capability.
			return array_fill_keys( $capabilities, false ) + $all_capabilities;
		}

		// Also block access if trying to edit a shop_subscription post type.
		// WordPress maps edit_post/delete_post to edit_shop_order/delete_shop_order
		// via map_meta_cap since capability_type => 'shop_order'.
		if ( ! empty( $arguments[0] ) && ! empty( $arguments[2] ) ) {
			$post_id = $arguments[2];
			if ( function_exists( 'get_post_type' ) && 'shop_subscription' === get_post_type( $post_id ) ) {
				// Deny all capabilities for this subscription post.
				return array_fill_keys( $capabilities, false ) + $all_capabilities;
			}
		}

		return $all_capabilities;
	}

	/**
	 * Remove subscription menu items from WordPress admin.
	 */
	public function remove_subscription_menus() {
		// Remove the main subscriptions menu.
		remove_menu_page( 'edit.php?post_type=shop_subscription' );

		// Remove subscriptions submenu items if they exist.
		remove_submenu_page( 'woocommerce', 'edit.php?post_type=shop_subscription' );
	}

	/**
	 * Redirect subscription admin pages to plugins page with notice.
	 */
	public function redirect_subscription_pages() {
		global $pagenow, $typenow;

		// Check if we're on a subscription edit or list page.
		if ( 'edit.php' === $pagenow || 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
			if ( 'shop_subscription' === $typenow || ( isset( $_GET['post_type'] ) && 'shop_subscription' === $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( admin_url( 'plugins.php?wcpay-subscriptions-disabled=1' ) );
				exit;
			}
		}

		// Check for redirect parameter and show notice.
		if ( isset( $_GET['wcpay-subscriptions-disabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'admin_notices', [ $this, 'display_redirect_notice' ] );
		}
	}

	/**
	 * Display notice when subscription pages are accessed.
	 */
	public function display_redirect_notice() {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Subscription Management Disabled', 'woocommerce-payments' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: Link to WooCommerce Subscriptions */
					esc_html__( 'WooPayments no longer supports creating or managing subscriptions. Please install %s to manage your subscriptions. Your existing subscriptions and renewal orders will continue to work.', 'woocommerce-payments' ),
					'<a href="' . esc_url( 'https://woocommerce.com/products/woocommerce-subscriptions/' ) . '" target="_blank">' . esc_html__( 'WooCommerce Subscriptions', 'woocommerce-payments' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Remove subscription product types from product editor.
	 *
	 * @param array $types Product types.
	 * @return array Modified product types.
	 */
	public function remove_subscription_product_types( $types ) {
		// Remove subscription and variable subscription types.
		unset( $types['subscription'] );
		unset( $types['variable-subscription'] );

		return $types;
	}

	/**
	 * Block subscription creation/editing via REST API.
	 *
	 * @param bool   $permission Permission result.
	 * @param string $context    Request context (read, create, edit, delete).
	 * @param int    $object_id  Object ID (unused but required by filter signature).
	 * @param string $post_type  Post type.
	 * @return bool|WP_Error Modified permission or error.
	 */
	public function block_subscription_rest_api( $permission, $context, $object_id, $post_type ) {
		unset( $object_id ); // Unused but required by filter signature.

		if ( 'shop_subscription' === $post_type && in_array( $context, [ 'create', 'edit', 'delete' ], true ) ) {
			return new WP_Error(
				'woocommerce_rest_cannot_manage_subscriptions',
				__( 'Subscription management is disabled. Please install WooCommerce Subscriptions.', 'woocommerce-payments' ),
				[ 'status' => 403 ]
			);
		}

		return $permission;
	}
}
