<?php
/**
 * Class CheckoutEncryptionService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use stdClass;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;
use WC_Payments_Account;
use WC_Payments_Utils;
use WCPay\Internal\Service\OrderService;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Service for generating Level 3 data from orders.
 */
class CheckoutEncryptionService {
		/**
		 * Encrypt client secret for the client.
		 *
		 * @param string $customer_id Customer id.
		 * @param string $client_secret Client secret.
		 *
		 * @return string
		 */
	public function encrypt_client_secret( string $customer_id, string $client_secret ): string {
		if ( \WC_Payments_Features::is_client_secret_encryption_enabled() ) {
			return openssl_encrypt(
				$client_secret,
				'aes-128-cbc',
				substr( $customer_id, 5 ),
				0,
				str_repeat( 'WC', 8 )
			);
		}
		return $client_secret;
	}
}
