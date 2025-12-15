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
 * interfaces for both merchants and customers. It also prevents new subscription
 * products from being purchased or added to orders.
 *
 * What this class disables:
 * - Admin menu items (WooCommerce > Subscriptions)
 * - Admin subscription management screens
 * - Subscription product types in product creation
 * - Subscription settings tab
 * - Customer account subscription pages
 * - Related subscriptions section on order details
 * - Related orders meta box on admin order edit screen
 * - Purchasing of subscription products (makes them unpurchasable)
 * - Adding subscription products to admin orders (both search and validation)
 * - Order-pay endpoint when accessed with subscription IDs
 *
 * What this class does NOT affect:
 * - Stripe Billing webhook processing (invoice.paid, invoice.upcoming, etc.)
 * - Automatic renewal order creation via wcs_create_renewal_order()
 * - Subscription payment processing and completion
 * - Existing subscription data or meta
 * - Backend subscription status management
 * - Payment method updates
 * - Regular (non-subscription) products
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
	 * - Removes "Related Orders" meta box from order edit screen
	 *
	 * Frontend hooks (customer-facing blocking):
	 * - Removes subscription navigation from My Account
	 * - Blocks direct access to subscription endpoints
	 * - Removes subscription details from order views
	 * - Makes subscription products unpurchasable (prevents new subscriptions)
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
			add_filter( 'woocommerce_json_search_found_products', [ $this, 'filter_admin_product_search' ] );
			add_filter( 'woocommerce_ajax_add_order_item_validation', [ $this, 'validate_admin_order_item' ], 10, 4 );
			add_action( 'add_meta_boxes', [ $this, 'remove_related_orders_meta_box' ], 99, 2 );
		}

		add_filter( 'woocommerce_account_menu_items', [ $this, 'remove_account_menu_item' ], 99 );
		add_action( 'pre_get_posts', [ $this, 'maybe_redirect_subscription_endpoints' ], 1 );
		add_action( 'template_redirect', [ $this, 'maybe_redirect_account_endpoints' ], 5 );
		add_action( 'init', [ $this, 'remove_related_subscriptions_section' ], 99 );
		add_filter( 'woocommerce_is_purchasable', [ $this, 'make_subscription_products_unpurchasable' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_removed_message', [ $this, 'filter_subscription_removal_message' ], 10, 2 );
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
	 * Removes the "Related Orders" meta box from order edit screens.
	 *
	 * This meta box displays subscription-related orders (renewals, parent orders, etc.)
	 * on the admin order edit screen. We remove it to hide subscription relationships
	 * from merchants when viewing orders.
	 *
	 * The meta box is added by WooCommerce Subscriptions via:
	 * - WCS_Admin_Meta_Boxes::add_meta_boxes() at priority 10 on 'add_meta_boxes'
	 * - Meta box ID: 'subscription_renewal_orders'
	 * - Title: 'Related Orders'
	 *
	 * We run this at priority 99 to ensure it executes after WCS adds the meta box.
	 *
	 * @param string                $post_type The post type of the current post being edited.
	 * @param WP_Post|WC_Order|null $post_or_order_object The post or order currently being edited.
	 * @return void
	 */
	public function remove_related_orders_meta_box( $post_type, $post_or_order_object = null ) {
		// Only process when WCS functions are available.
		if ( ! function_exists( 'wcs_get_page_screen_id' ) ) {
			return;
		}

		// Get the order screen ID (handles both CPT and HPOS).
		$order_screen_id = wcs_get_page_screen_id( 'shop_order' );

		// Remove the Related Orders meta box from the order edit screen.
		// The 'normal' context matches where WCS registers the meta box.
		remove_meta_box( 'subscription_renewal_orders', $order_screen_id, 'normal' );
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
	 * Redirects subscription endpoints during query parsing.
	 *
	 * This runs on the pre_get_posts hook (priority 1) to intercept subscription
	 * endpoint requests BEFORE WooCommerce Subscriptions can redirect them to
	 * the order-pay page. This is critical because WCS_Query::maybe_redirect_payment_methods()
	 * runs at priority 10 on pre_get_posts and would redirect /my-account/subscription-payment-method/ID
	 * to /checkout/order-pay/ID/?change_payment_method=ID before we can block it.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function maybe_redirect_subscription_endpoints( $query ) {
		// Only process main queries.
		if ( ! $query->is_main_query() ) {
			return;
		}

		// Check each subscription endpoint.
		$endpoints = [
			'subscriptions',
			'view-subscription',
			'subscription-payment-method',
		];

		foreach ( $endpoints as $endpoint_key ) {
			$endpoint_slug = $this->get_account_endpoint_slug( $endpoint_key );

			// Check if this query is for a subscription endpoint.
			if ( ! empty( $query->get( $endpoint_slug ) ) ) {
				// Redirect to My Account before WCS can redirect elsewhere.
				$this->redirect( wc_get_page_permalink( 'myaccount' ) );
			}
		}
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

		// Also block order-pay endpoint if it contains a subscription ID.
		$this->maybe_redirect_order_pay_for_subscription();
	}

	/**
	 * Redirects order-pay requests that target subscription IDs.
	 *
	 * This prevents users from accessing the "pay for order" page using a
	 * subscription ID in two ways:
	 *
	 * 1. Direct access: /checkout/order-pay/{subscription_id}/
	 * 2. Via change_payment_method parameter: /checkout/order-pay/ID/?change_payment_method={subscription_id}
	 *
	 * The second case occurs when WooCommerce Subscriptions redirects from
	 * /my-account/subscription-payment-method/{subscription_id}/ to the order-pay
	 * endpoint during the pre_get_posts hook.
	 *
	 * @return void
	 */
	private function maybe_redirect_order_pay_for_subscription() {
		global $wp;

		$subscription_id = null;

		// Check if we're on the order-pay endpoint with a subscription ID.
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			$order_id  = absint( $wp->query_vars['order-pay'] );
			$post_type = get_post_type( $order_id );

			if ( 'shop_subscription' === $post_type ) {
				$subscription_id = $order_id;
			}
		}

		// Also check for change_payment_method parameter (when redirected from subscription-payment-method endpoint).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified by WooCommerce core.
		if ( ! $subscription_id && ! empty( $_GET['change_payment_method'] ) ) {
			$change_payment_id = absint( $_GET['change_payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type         = get_post_type( $change_payment_id );

			if ( 'shop_subscription' === $post_type ) {
				$subscription_id = $change_payment_id;
			}
		}

		// If we found a subscription ID in either place, redirect.
		if ( $subscription_id ) {
			$this->redirect( wc_get_page_permalink( 'myaccount' ) );
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
	 * Makes subscription products unpurchasable to prevent new subscriptions.
	 *
	 * This prevents customers from adding subscription products to their cart
	 * or purchasing them during checkout. Runs early in the purchase flow to
	 * provide the cleanest user experience.
	 *
	 * Does NOT affect:
	 * - Renewal order processing (renewals don't check is_purchasable)
	 * - Existing subscriptions in the database
	 * - Regular (non-subscription) products
	 *
	 * @param bool       $is_purchasable Whether the product can be purchased.
	 * @param WC_Product $product        Product object.
	 * @return bool False for subscription products, original value otherwise.
	 */
	public function make_subscription_products_unpurchasable( $is_purchasable, $product ) {
		if ( ! $product ) {
			return $is_purchasable;
		}

		// Check if product is a subscription type.
		if ( $product->is_type( [ 'subscription', 'variable-subscription', 'subscription_variation' ] ) ) {
			return false;
		}

		return $is_purchasable;
	}

	/**
	 * Filters the cart item removal message for subscription products.
	 *
	 * When WooCommerce removes unpurchasable products from the cart, this filter
	 * customizes the message for subscription products to be more customer-friendly.
	 *
	 * @param string     $message The default removal message from WooCommerce.
	 * @param WC_Product $product The product being removed.
	 * @return string The filtered message.
	 */
	public function filter_subscription_removal_message( $message, $product ) {
		// Only modify the message if this is a subscription product.
		if ( ! $product || ! $product->is_type( [ 'subscription', 'variable-subscription', 'subscription_variation' ] ) ) {
			return $message;
		}

		// Return a customer-friendly message that matches WooCommerce's standard format.
		return sprintf(
			/* translators: %s: product name */
			__( '%s has been removed from your cart because it can no longer be purchased. Please contact us if you need assistance.', 'woocommerce-payments' ),
			$product->get_name()
		);
	}

	/**
	 * Filters subscription products from admin product search results.
	 *
	 * Removes subscription products from the AJAX product search used in the
	 * admin order editor "Add item(s)" modal. This prevents admins from seeing
	 * subscription products as options when manually creating orders.
	 *
	 * @param array $products Array of products (product_id => product_name).
	 * @return array Filtered array without subscription products.
	 */
	public function filter_admin_product_search( $products ) {
		if ( empty( $products ) ) {
			return $products;
		}

		$filtered = [];
		foreach ( $products as $product_id => $product_name ) {
			$product = wc_get_product( $product_id );

			// Skip if not a valid product or is a subscription type.
			if ( ! $product || $product->is_type( [ 'subscription', 'variable-subscription', 'subscription_variation' ] ) ) {
				continue;
			}

			$filtered[ $product_id ] = $product_name;
		}

		return $filtered;
	}

	/**
	 * Validates that subscription products cannot be added to admin orders.
	 *
	 * This provides server-side validation as a backup to the search filtering.
	 * If an admin somehow attempts to add a subscription product to an order
	 * (e.g., by manipulating the AJAX request), this will block it with an error.
	 *
	 * @param WP_Error   $validation_error Error object to populate if validation fails.
	 * @param WC_Product $product          Product being added to order.
	 * @param WC_Order   $order            Order object.
	 * @param int        $qty              Quantity being added.
	 * @return WP_Error Error object (populated if validation fails).
	 */
	public function validate_admin_order_item( $validation_error, $product, $order, $qty ) {
		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required by filter signature.
		unset( $order, $qty );

		if ( ! $product ) {
			return $validation_error;
		}

		// Check if product is a subscription type.
		if ( $product->is_type( [ 'subscription', 'variable-subscription', 'subscription_variation' ] ) ) {
			return new WP_Error(
				'subscription_not_allowed_in_admin_order',
				__( 'Subscription products cannot be added to orders. Please install WooCommerce Subscriptions to manage subscriptions.', 'woocommerce-payments' )
			);
		}

		return $validation_error;
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
			__( 'To access your subscriptions data and keep managing recurring payments, please install <a target="_blank" href="%1$s">WooCommerce Subscriptions</a>. Built-in support for subscriptions is no longer available in WooPayments.', 'woocommerce-payments' ),
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
