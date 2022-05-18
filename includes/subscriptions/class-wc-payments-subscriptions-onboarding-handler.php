<?php
/**
 * Class WC_Payments_Subscriptions_Onboarding_Handler
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Tracker;

/**
 * A class to handle the onboarding of subscriptions products. Created subscription products will be set to draft until
 *   onboarding is completed.
 */
class WC_Payments_Subscriptions_Onboarding_Handler {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Option for holding an array of product id's to publish post onboarding.
	 *
	 * @const string
	 */
	const WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS = 'wcpay_subscription_onboarding_products';

	/**
	 * The account service instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Constructor
	 *
	 * @param WC_Payments_Account $account account service instance.
	 */
	public function __construct( WC_Payments_Account $account ) {
		// This action is triggered on product save but after other required subscriptions logic is triggered.
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'product_save' ] );
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'account_data_refreshed' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_modal_scripts_and_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_toast_script' ] );
		add_filter( 'woocommerce_subscriptions_admin_pointer_script_parameters', [ $this, 'filter_admin_pointer_script_parameters' ] );

		$this->account = $account;
	}

	/**
	 * Sets the account service instance reference on the class.
	 *
	 * @param WC_Payments_Account $account account service instance.
	 */
	public function set_account( WC_Payments_Account $account ) {
		$this->account = $account;
	}

	/**
	 * Convert subscriptions to drafts when using WCPay (without subscriptions) and onboarding is not complete.
	 * This should be triggered just prior to a $product->save() call so no need to call product->save()
	 *
	 * @param WC_Product $product Subscriptions Product.
	 */
	public function product_save( WC_Product $product ) {
		if ( $this->account->is_stripe_connected() ) {
			return;
		}

		// If Subscriptions plugin is installed we don't need to do this check.
		if ( $this->is_subscriptions_plugin_active() ) {
			return;
		}

		// Skip products which have already been scheduled or aren't subscriptions.
		if ( ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return;
		}

		// We can skip if the post is not yet marked as published.
		if ( 'publish' !== $product->get_status() ) {
			return;
		}

		// Change the default WP saved post URL to correctly reflect the draft status and to add our saved-as-draft flag.
		add_filter(
			'redirect_post_location',
			function() use ( $product ) {
				return add_query_arg(
					[
						'message' => 10, // Post saved as draft message.
						'wcpay-subscription-saved-as-draft' => 1,
					],
					get_edit_post_link( $product->get_id(), 'url' )
				);
			}
		);

		$this->convert_subscription_to_draft( $product );
	}

	/**
	 * Convert a product to a draft and save the id in an array, so we can auto-publish once onboarding is complete.
	 *
	 * @param WC_Product $product Product to convert to draft.
	 */
	private function convert_subscription_to_draft( WC_Product $product ) {
		// Force into draft status.
		$product->set_status( 'draft' );
		$product->save();

		$auto_publish_ids   = get_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, [] );
		$auto_publish_ids[] = $product->get_id();

		// Save and prevent duplicates from multiple updates.
		update_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, array_unique( $auto_publish_ids ) );

		Tracker::track_admin( 'wcpay_subscriptions_account_not_connected_save_product' );
	}

	/**
	 * Method to handle when account data is refreshed and onboarding may have been completed
	 */
	public function account_data_refreshed() {

		if ( ! $this->account->is_stripe_connected() ) {
			return;
		}

		$products = get_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, [] );

		if ( [] === $products ) {
			return;
		}

		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
				continue;
			}
			if ( 'draft' !== $product->get_status() ) {
				continue;
			}

			$product->set_status( 'publish' );
			$product->save();
		}

		// clear auto-published products from option.
		delete_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS );
	}

	/**
	 * Enqueues the admin scripts needed on the add/edit product screen when the
	 * merchant attempts to publish a subscription product prior to completing
	 * WCPay onboarding.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_modal_scripts_and_styles( $hook_suffix ) {
		global $post;

		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		if ( empty( $_GET['wcpay-subscription-saved-as-draft'] ) || 1 !== (int) $_GET['wcpay-subscription-saved-as-draft'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( $this->is_subscriptions_plugin_active() ) {
			return;
		}

		if ( $this->account->is_stripe_connected() ) {
			return;
		}

		$script_src_url    = plugins_url( 'dist/subscription-product-onboarding-modal.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/subscription-product-onboarding-modal.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			'wcpay-subscription-product-onboarding-modal',
			$script_src_url,
			$script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/subscription-product-onboarding-modal.js' ),
			true
		);

		wp_localize_script(
			'wcpay-subscription-product-onboarding-modal',
			'wcpaySubscriptionProductOnboardingModal',
			[
				'connectUrl'  => WC_Payments_Account::get_connect_url( 'WC_SUBSCRIPTIONS_PUBLISH_PRODUCT_' . $post->ID ),
				'pluginScope' => ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.5', '>=' ) ) ? 'woocommerce-admin' : 'woocommerce',
			]
		);

		wp_register_style(
			'wcpay-subscription-product-onboarding-modal',
			plugins_url( 'dist/subscription-product-onboarding-modal.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/subscription-product-onboarding-modal.css' )
		);

		wp_enqueue_script( 'wcpay-subscription-product-onboarding-modal' );
		wp_enqueue_style( 'wcpay-subscription-product-onboarding-modal' );
	}

	/**
	 * Enqueues the admin scripts needed on the add/edit product screen when the
	 * merchant has completed WCPay onboarding and is redirected back to product
	 * edit page.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_toast_script( $hook_suffix ) {
		global $post;

		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		if ( empty( $_GET['wcpay-subscriptions-onboarded'] ) || 1 !== (int) $_GET['wcpay-subscriptions-onboarded'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( $this->is_subscriptions_plugin_active() ) {
			return;
		}

		$script_src_url    = plugins_url( 'dist/subscription-product-onboarding-toast.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/subscription-product-onboarding-toast.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			'wcpay-subscription-product-onboarding-toast',
			$script_src_url,
			$script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/subscription-product-onboarding-toast.js' ),
			true
		);

		wp_localize_script(
			'wcpay-subscription-product-onboarding-toast',
			'wcpaySubscriptionProductOnboardingToast',
			[
				'pluginScope' => ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.5', '>=' ) ) ? 'woocommerce-admin' : 'woocommerce',
			]
		);

		wp_enqueue_script( 'wcpay-subscription-product-onboarding-toast' );
	}

	/**
	 * Modifies the pointer content found on the "Add new product" page
	 * when WooCommerce Subscriptions is not active.
	 *
	 * @param array $pointer_params Array of strings used on the "Add new product" page.
	 * @return array Potentially modified array of strings used on the "Add new product" page.
	 */
	public function filter_admin_pointer_script_parameters( $pointer_params ) {
		if ( $this->is_subscriptions_plugin_active() ) {
			return $pointer_params;
		}

		// translators: %1$s: <h3> tag, %2$s: </h3> tag, %3$s: <p> tag, %4$s: <em> tag, %5$s: </em> tag, %6$s: <em> tag, %7$s: </em> tag, %8$s: </p> tag.
		$pointer_params['typePointerContent'] = sprintf( _x( '%1$sChoose Subscription%2$s%3$sWooCommerce Payments adds two new subscription product types - %4$sSimple subscription%5$s and %6$sVariable subscription%7$s.%8$s', 'used in admin pointer script params in javascript as type pointer content', 'woocommerce-payments' ), '<h3>', '</h3>', '<p>', '<em>', '</em>', '<em>', '</em>', '</p>' );

		return $pointer_params;
	}
}
