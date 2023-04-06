<?php
/**
 * Class Platform_Checkout_Store_Api_Token
 *
 * @package WCPay\Platform_Checkout
 */

namespace WCPay\Platform_Checkout;

use Automattic\WooCommerce\StoreApi\Routes\V1\AbstractCartRoute;

/**
 * This class is used to get the cart token from the cart route.
 */
class Platform_Checkout_Store_Api_Token extends AbstractCartRoute {

	/**
	 * Helper method to get the instance of the class.
	 *
	 * @return Platform_Checkout_Store_Api_Token The instance of the class.
	 */
	public static function init() {
		$formatters        = new \Automattic\WooCommerce\StoreApi\Formatters();
		$extend_schema     = new \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema( $formatters );
		$schema_controller = new \Automattic\WooCommerce\StoreApi\SchemaController( $extend_schema );
		return new self( $schema_controller, $schema_controller->get( 'cart' ) );
	}

	/**
	 * Get the path of this REST route.
	 *
	 * @throws \Exception Throws exception because this method is not meant to be implemented in this utility class.
	 */
	public function get_path() {
		throw new \Exception( 'Not implemented' );
	}

	/**
	 * Get arguments for this REST route.
	 *
	 * @throws \Exception Throws exception because this method is not meant to be implemented in this utility class.
	 */
	public function get_args() {
		throw new \Exception( 'Not implemented' );
	}

    //phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
	/**
	 * This function is used to get the cart token from the cart route.
	 *
	 * @return string The cart token.
	 */
	public function get_cart_token() {
		return parent::get_cart_token();
	}

	/**
	 * This function is used to get the cart token secret from the cart route.
	 *
	 * @return string The cart token secret.
	 */
	public function get_cart_token_secret() {
		return parent::get_cart_token_secret();
	}
}
