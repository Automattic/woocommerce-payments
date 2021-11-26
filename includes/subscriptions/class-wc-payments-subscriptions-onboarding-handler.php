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
		add_action( 'publish_product', [ $this, 'product_published' ] );
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'account_data_refreshed' ] );

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
	 *
	 * @param int $product_id Subscriptions Product id.
	 */
	public function product_published( int $product_id ) {
		// We can skip if the post is not yet marked as published.

		if ( $this->account->is_stripe_connected() ) {
			return;
		}

		// If Subscriptions plugin is installed we don't need to do this check.
		if ( $this->is_subscriptions_plugin_active() ) {
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
		$product->set_status( 'draft' );
		$product->save();

		$auto_publish_ids   = get_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, [] );
		$auto_publish_ids[] = $product->get_id();

		// Save and prevent duplicates from multiple updates.
		update_option( self::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, array_unique( $auto_publish_ids ) );
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
}
