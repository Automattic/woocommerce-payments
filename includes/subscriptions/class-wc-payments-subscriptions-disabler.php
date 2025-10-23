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
 *
 * This class hides UI elements and blocks access to subscription management
 * interfaces for both merchants and customers. It operates exclusively on
 * the presentation layer and does NOT affect backend subscription functionality.
 *
 * What this class disables:
 * - Admin menu items (WooCommerce > Subscriptions)
 * - Admin subscription management screens
 * - Subscription product types in product creation
 * - Subscription settings tab
 * - Customer account subscription pages
 * - Related subscriptions section on order details
 *
 * What this class does NOT affect:
 * - Stripe Billing webhook processing (invoice.paid, invoice.upcoming, etc.)
 * - Automatic renewal order creation via wcs_create_renewal_order()
 * - Subscription payment processing and completion
 * - Existing subscription data or meta
 * - Backend subscription status management
 * - Payment method updates
 *
 * This ensures merchants and customers cannot create or manage subscriptions
 * through the UI while Stripe Billing continues to process renewals automatically.
 */
class WC_Payments_Subscriptions_Disabler {

	/**
	 * Initiates hooks that hide bundled subscriptions management entry points.
	 *
	 * This method registers UI-layer hooks only. It does NOT hook into:
	 * - Payment processing (woocommerce_subscription_payment_complete, etc.)
	 * - Renewal order creation (woocommerce_renewal_order_payment_complete, etc.)
	 * - Webhook handling (invoice.paid, invoice.upcoming, etc.)
	 * - Subscription status changes (woocommerce_subscription_status_*, etc.)
	 *
	 * Admin hooks (menu/screen blocking):
	 * - Removes admin menu items
	 * - Blocks direct access to subscription screens
	 * - Removes subscription product types from product editor
	 * - Removes subscription settings tab
	 *
	 * Frontend hooks (customer-facing blocking):
	 * - Removes subscription navigation from My Account
	 * - Blocks direct access to subscription endpoints
	 * - Removes subscription details from order views
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'remove_admin_menu_items' ], 99 );
			add_action( 'current_screen', [ $this, 'maybe_block_admin_subscription_screen' ] );
			add_filter( 'product_type_selector', [ $this, 'filter_product_type_selector' ], 99 );
			add_filter( 'woocommerce_settings_tabs_array', [ $this, 'filter_settings_tabs' ], 99 );
			add_action( 'admin_init', [ $this, 'maybe_redirect_settings_tab' ], 99 );
			add_action( 'admin_notices', [ $this, 'display_subscription_disabled_notice' ] );
		}

		add_filter( 'woocommerce_account_menu_items', [ $this, 'remove_account_menu_item' ], 99 );
		add_action( 'template_redirect', [ $this, 'maybe_redirect_account_endpoints' ] );
		add_action( 'init', [ $this, 'remove_related_subscriptions_section' ], 99 );
	}

	/**
	 * Removes WooCommerce > Subscriptions menu entries.
	 *
	 * Hides the subscriptions admin menu for both CPT and HPOS implementations.
	 * Does not affect subscription data or the ability for renewals to process.
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
	 * Prevents direct URL access to subscription edit/list screens by redirecting
	 * to the WooCommerce overview. Does not run during AJAX or REST requests to
	 * avoid interfering with legitimate background operations.
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
	 * Prevents merchants from creating new subscription products by hiding
	 * the product types from the dropdown. Existing subscription products
	 * remain in the database and can still process renewals.
	 *
	 * @param array $product_types Registered product types.
	 * @return array Filtered product types without subscription options.
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
	 * Prevents customers from accessing subscription management pages including:
	 * - Subscriptions list (/my-account/subscriptions)
	 * - View subscription detail (/my-account/view-subscription/123)
	 * - Payment method management (/my-account/subscription-payment-method/123)
	 *
	 * Redirects all attempts to the My Account dashboard. Does not affect
	 * subscription data or automated renewal payments.
	 *
	 * @return void
	 */
	public function maybe_redirect_account_endpoints() {
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
	 * Removes the related subscriptions section from order details.
	 *
	 * Hides the "Related Subscriptions" section that normally appears on
	 * order detail pages (both admin and customer-facing). This prevents
	 * users from viewing subscription information through renewal orders.
	 *
	 * The underlying subscription and renewal order relationship remains intact;
	 * only the display is hidden.
	 *
	 * @return void
	 */
	public function remove_related_subscriptions_section() {
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			remove_action(
				'woocommerce_order_details_after_order_table',
				[ 'WC_Subscriptions_Order', 'add_subscriptions_to_view_order_templates' ],
				10
			);
		}
	}

	/**
	 * Determines if the given screen ID should be blocked.
	 *
	 * Checks if a screen ID contains subscription-related identifiers for
	 * both CPT (shop_subscription) and HPOS (wc-orders--shop_subscription).
	 *
	 * @param string $screen_id Screen ID.
	 * @return bool True if the screen should be blocked, false otherwise.
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
	 * This handles:
	 * - Listing: ?post_type=shop_subscription
	 * - Adding new: ?post_type=shop_subscription
	 * - Editing by post ID: ?post=123&action=edit (checked via post type lookup)
	 *
	 * @return bool
	 */
	private function is_subscription_post_type_request() {
		// Check for explicit post_type parameter.
		if ( ! empty( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'shop_subscription' === sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// Check if editing a specific post that might be a subscription.
		if ( ! empty( $_GET['post'] ) && ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $post_id > 0 ) {
				$post_type = get_post_type( $post_id );
				// Block subscription orders.
				if ( 'shop_subscription' === $post_type ) {
					return true;
				}
				// Block subscription products.
				if ( 'product' === $post_type && $this->is_subscription_product( $post_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if a product ID is a subscription product.
	 *
	 * @param int $product_id Product ID to check.
	 * @return bool True if the product is a subscription product, false otherwise.
	 */
	private function is_subscription_product( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		// Check product type directly - more reliable than using WC_Subscriptions_Product
		// which may not be available in all contexts.
		return $product->is_type( [ 'subscription', 'variable-subscription', 'subscription_variation' ] );
	}

	/**
	 * Displays an admin notice when users are redirected from disabled subscription features.
	 *
	 * @return void
	 */
	public function display_subscription_disabled_notice() {
		if ( empty( $_GET['wcpay_subscription_disabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( empty( $_GET['page'] ) || 'wc-settings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( empty( $_GET['section'] ) || 'woocommerce_payments' !== $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$message = sprintf(
			/* translators: %1$s: WooCommerce Subscriptions link */
			__( 'Subscription management is currently unavailable. To create and manage subscriptions, please install <a target="_blank" href="%1$s">WooCommerce Subscriptions</a>.', 'woocommerce-payments' ),
			'https://woocommerce.com/products/woocommerce-subscriptions/'
		);
		?>
		<div class="notice notice-info wcpay-notice">
			<p><strong><?php esc_html_e( 'WooPayments', 'woocommerce-payments' ); ?></strong></p>
			<p>
			<?php
			echo wp_kses(
				$message,
				[
					'a' => [
						'href'   => [],
						'target' => [],
					],
				]
			);
			?>
			</p>
		</div>
		<?php
	}

	/**
	 * Redirects the current request to the WooCommerce Payments settings page.
	 *
	 * Adds a query parameter to trigger an informational notice after redirect.
	 *
	 * @return void
	 */
	protected function redirect_to_admin_overview() {
		$redirect_url = add_query_arg(
			[
				'page'                        => 'wc-settings',
				'tab'                         => 'checkout',
				'section'                     => 'woocommerce_payments',
				'wcpay_subscription_disabled' => '1',
			],
			admin_url( 'admin.php' )
		);

		$this->redirect( $redirect_url );
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
	 * Retrieves all subscription-related My Account endpoints that customers
	 * should not be able to access. Endpoint slugs are configurable via
	 * WooCommerce settings, so we fetch them dynamically.
	 *
	 * @return array Array of endpoint slugs to block.
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
