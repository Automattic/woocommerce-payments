<?php
/**
 * Class WC_Payments_Subscriptions_Onboarding_Handler
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A class to handle the onboarding of subscriptions products. Created subscription products will be set to draft until
 *   onboarding is completed.
 */
class WC_Payments_Subscriptions_Onboarding_Handler {

	/**
	 * Transient to store subscription publish blocked error.
	 *
	 * @const string
	 */
	const WCPAY_SUBSCRIPTION_PUBLISH_BLOCKED_NOTICE = 'wcpay_subscription_onboarding_blocked_notice';

	/**
	 * Option for holding an array of product id's to publish post onboarding.
	 *
	 * @const string
	 */
	const WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS = 'wcpay_subscription_onboarding_products';

	/**
	 * Transient to store notice that subscriptions were auto published.
	 *
	 * @const string
	 */
	const WCPAY_SUBSCRIPTION_AUTO_PUBLISH_NOTICE = 'wcpay_subscription_onboarding_published_notice';

	/**
	 * The account service instance.
	 *
	 * @var WC_Payments_Account|null
	 */
	private static $account = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'publish_product', [ $this, 'product_published' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'add_notices' ] );
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'account_data_refreshed' ] );
	}

	/**
	 * Sets the account service instance reference on the class.
	 *
	 * @param WC_Payments_Account $account account service instance.
	 */
	public static function set_account( WC_Payments_Account $account ) {
		self::$account = $account;
	}

	/**
	 * Convert subscriptions to drafts when using WCPay (without subscriptions) and onboarding is not complete.
	 *
	 * @param int $product_id Subscriptions Product id.
	 */
	public function product_published( int $product_id ) {
		// We can skip if the post is not yet marked as published.

		if ( $this->is_onboarding_complete() ) {
			return;
		}

		// If Subscriptions plugin is installed we don't need to do this check.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		// Skip products which have already been scheduled or aren't subscriptions.
		$product = wc_get_product( $product_id );
		if ( ! $product || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return;
		}

		$this->convert_subscription_to_draft( $product );
	}

	/**
	 * Convert a product to a draft and save the id in an array, so we can auto-publish once onboarding is complete.
	 *
	 * @param WC_Product $product Product to convert to draft.
	 */
	private function convert_subscription_to_draft( WC_Product $product ) {
		// Force into draft status.
		wp_update_post(
			[
				'ID'          => $product->get_id(),
				'post_status' => 'draft',
			]
		);
		set_transient( self::WCPAY_SUBSCRIPTION_PUBLISH_BLOCKED_NOTICE, true, 30 );

		$auto_publish_ids   = get_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, [] );
		$auto_publish_ids[] = $product->get_id();

		// Save and prevent duplicates from multiple updates.
		update_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, array_unique( $auto_publish_ids ) );
	}

	/**
	 * Add any notices required to the UI,
	 */
	public function add_notices() {
		$publish_blocked_warning = get_transient( self::WCPAY_SUBSCRIPTION_PUBLISH_BLOCKED_NOTICE );

		if ( $publish_blocked_warning ) {
			echo '<div class="notice notice-warning wcpay-settings-notice"><p>' .
				esc_html( __( 'WooCommerce Payments must be setup to publish a subscription product. Any subscription products will be automatically published when setup is complete.', 'woocommerce-payments' ) ) .
			'</p></div>';
			delete_transient( self::WCPAY_SUBSCRIPTION_PUBLISH_BLOCKED_NOTICE );
		}

		$products_published_info = get_transient( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_NOTICE );

		if ( $products_published_info ) {
			echo '<div class="notice notice-info wcpay-settings-notice"><p>';
			echo WC_Payments_Utils::esc_interpolated_html(
			/* translators: link to Stripe testing page */
				__( '<strong>Subscriptions Published:</strong> Your draft subscription products have now been published.', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
				]
			);
			echo '</p></div>';
			delete_transient( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_NOTICE );
		}

	}

	/**
	 * Method to handle when account data is refreshed and onboarding may have been completed
	 */
	public function account_data_refreshed() {

		if ( ! $this->is_onboarding_complete() ) {
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

			wp_update_post(
				[
					'ID'          => $product->get_id(),
					'post_status' => 'publish',
				]
			);
		}

		// Set transient to show a notice to inform user of auto-publish.
		set_transient(
			self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_NOTICE,
			true,
			180
		);

		// clear auto-published products from option.
		delete_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS );
	}

	/**
	 * Check whether onboarding is complete
	 *
	 * @return bool
	 */
	private function is_onboarding_complete(): bool {
		$account = self::$account ?? WC_Payments::get_account_service();

		return ( $account instanceof WC_Payments_Account ) && true === $account->is_stripe_connected();
	}
}
