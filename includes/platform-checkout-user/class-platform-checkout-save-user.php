<?php
/**
 * Class PlatformCheckoutSaveUser
 *
 * @package WooCommerce\Payments\PlatformCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class that adds a section to save new user for platform checkout on the frontend.
 */
class Platform_Checkout_Save_User {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_review_order_after_payment', [ $this, 'add_checkout_page_save_user_container' ] );
	}

	/** Adds a section in checkout page to save new user for platform checkout. */
	public function add_checkout_page_save_user_container() {
		echo '<div id="checkout-page-save-user-container"></div>';
	}
}
