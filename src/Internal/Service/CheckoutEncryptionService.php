<?php
/**
 * Class CheckoutEncryptionService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Service for checkout data encryption.
 */
class CheckoutEncryptionService {

	/**
	 * Legacy Proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Service constructor.
	 *
	 * @param LegacyProxy $legacy_proxy Legacy proxy.
	 */
	public function __construct(
		LegacyProxy $legacy_proxy
	) {
		$this->legacy_proxy = $legacy_proxy;
	}

	/**
	 * Encrypt client secret for the client.
	 *
	 * @param string $customer_id Customer id.
	 * @param string $client_secret Client secret.
	 *
	 * @return string
	 */
	public function encrypt_client_secret( string $customer_id, string $client_secret ): string {
		if ( $this->legacy_proxy->call_static( \WC_Payments_Features::class, 'is_client_secret_encryption_enabled' ) ) {
			return $this->legacy_proxy->call_function(
				'openssl_encrypt',
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
