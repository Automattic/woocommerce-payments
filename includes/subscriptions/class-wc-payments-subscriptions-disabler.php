<?php
/**
 * Class WC_Payments_Subscriptions_Disabler
 *
 * Responsible for disabling merchant and customer facing management
 * interfaces for bundled subscriptions while keeping renewal logic active.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Disables bundled subscriptions management surfaces.
 */
class WC_Payments_Subscriptions_Disabler {

	/**
	 * Initiates hooks that hide bundled subscriptions management entry points.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'remove_admin_menu_items' ], PHP_INT_MAX );
			add_action( 'current_screen', [ $this, 'maybe_block_admin_subscription_screen' ] );
			add_filter( 'product_type_selector', [ $this, 'filter_product_type_selector' ], PHP_INT_MAX );
			add_filter( 'woocommerce_settings_tabs_array', [ $this, 'filter_settings_tabs' ], PHP_INT_MAX );
			add_action( 'admin_init', [ $this, 'maybe_redirect_settings_tab' ], PHP_INT_MAX );
		}

		add_filter( 'woocommerce_account_menu_items', [ $this, 'remove_account_menu_item' ], PHP_INT_MAX );
		add_action( 'template_redirect', [ $this, 'maybe_redirect_account_endpoints' ] );
	}

	/**
	 * Removes WooCommerce > Subscriptions menu entries.
	 *
	 * @return void
	 */
	public function remove_admin_menu_items() {
		remove_submenu_page( 'woocommerce', 'edit.php?post_type=shop_subscription' );
		remove_submenu_page( 'woocommerce', 'wc-orders--shop_subscription' );
		remove_menu_page( 'wc-orders--shop_subscription' );
	}

	/**
	 * Redirects attempts to access admin subscription management screens.
	 *
	 * @param WP_Screen $screen Current screen instance.
	 * @return void
	 */
	public function maybe_block_admin_subscription_screen( $screen ) {
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		$screen_id = (string) $screen->id;

		if ( $this->is_blocked_admin_screen( $screen_id ) || $this->is_subscription_post_type_request() ) {
			$this->redirect_to_admin_overview();
		}
	}

	/**
	 * Removes the subscriptions tab from the My Account navigation.
	 *
	 * @param array $items My Account menu items.
	 * @return array Filtered menu items.
	 */
	public function remove_account_menu_item( $items ) {
		$subscriptions_endpoint = $this->get_account_endpoint_slug( 'subscriptions' );

		if ( isset( $items[ $subscriptions_endpoint ] ) ) {
			unset( $items[ $subscriptions_endpoint ] );
		}

		return $items;
	}

	/**
	 * Removes subscription related product types from product selector.
	 *
	 * @param array $product_types Registered product types.
	 * @return array
	 */
	public function filter_product_type_selector( $product_types ) {
		unset( $product_types['subscription'], $product_types['variable-subscription'] );

		return $product_types;
	}

	/**
	 * Removes subscription tab from WooCommerce settings.
	 *
	 * @param array $tabs Registered WooCommerce settings tabs.
	 * @return array
	 */
	public function filter_settings_tabs( $tabs ) {
		unset( $tabs['subscriptions'] );

		return $tabs;
	}

	/**
	 * Redirects attempts to access the removed subscriptions settings tab.
	 *
	 * @return void
	 */
	public function maybe_redirect_settings_tab() {
		if ( empty( $_GET['page'] ) || empty( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'wc-settings' !== $page || 'subscriptions' !== $tab ) {
			return;
		}

		$this->redirect(
			add_query_arg(
				[
					'page' => 'wc-settings',
					'tab'  => 'general',
				],
				admin_url( 'admin.php' )
			)
		);
	}

	/**
	 * Redirects subscription related customer account endpoints.
	 *
	 * @return void
	 */
	public function maybe_redirect_account_endpoints() {
		if ( ! is_account_page() ) {
			return;
		}

		foreach ( $this->get_blocked_account_endpoints() as $endpoint ) {
			if ( empty( $endpoint ) ) {
				continue;
			}

			if ( $this->is_endpoint_url( $endpoint ) ) {
				$this->redirect( wc_get_page_permalink( 'myaccount' ) );
			}
		}
	}

	/**
	 * Determines if the given screen ID should be blocked.
	 *
	 * @param string $screen_id Screen ID.
	 * @return bool
	 */
	private function is_blocked_admin_screen( $screen_id ) {
		if ( '' === $screen_id ) {
			return false;
		}

		return false !== strpos( $screen_id, 'shop_subscription' )
			|| false !== strpos( $screen_id, 'wc-orders--shop_subscription' );
	}

	/**
	 * Determines if the current request is targeting the subscription post type.
	 *
	 * @return bool
	 */
	private function is_subscription_post_type_request() {
		if ( empty( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		return 'shop_subscription' === sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Redirects the current request to the WooCommerce dashboard.
	 *
	 * @return void
	 */
	protected function redirect_to_admin_overview() {
		$this->redirect( admin_url( 'admin.php?page=wc-admin' ) );
	}

	/**
	 * Gets the account endpoint slug for the supplied option key.
	 *
	 * @param string $key Subscriptions endpoint option key suffix.
	 * @return string
	 */
	private function get_account_endpoint_slug( $key ) {
		switch ( $key ) {
			case 'view-subscription':
				return get_option( 'woocommerce_myaccount_view_subscription_endpoint', 'view-subscription' );
			case 'subscription-payment-method':
				return get_option( 'woocommerce_myaccount_subscription_payment_method_endpoint', 'subscription-payment-method' );
			case 'subscriptions':
			default:
				return get_option( 'woocommerce_myaccount_subscriptions_endpoint', 'subscriptions' );
		}
	}

	/**
	 * Returns the list of account endpoints which should be blocked.
	 *
	 * @return array
	 */
	private function get_blocked_account_endpoints() {
		return [
			$this->get_account_endpoint_slug( 'subscriptions' ),
			$this->get_account_endpoint_slug( 'view-subscription' ),
			$this->get_account_endpoint_slug( 'subscription-payment-method' ),
		];
	}

	/**
	 * Redirects the current request to the provided URL and exits execution.
	 *
	 * @param string $target Target URL.
	 * @return void
	 */
	protected function redirect( $target ) {
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Checks whether the current request matches the provided endpoint.
	 *
	 * @param string $endpoint Endpoint slug.
	 * @return bool
	 */
	protected function is_endpoint_url( $endpoint ) {
		return is_wc_endpoint_url( $endpoint );
	}
}
