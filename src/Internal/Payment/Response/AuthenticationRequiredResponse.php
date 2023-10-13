<?php
/**
 * Authentication Required Response class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\Response;

use WC_Payments_API_Abstract_Intention;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * AuthenticationRequiredResponse class.
 */
class AuthenticationRequiredResponse {

	/**
	 * Intent instance.
	 *
	 * @var WC_Payments_API_Abstract_Intention
	 */
	private $intent;

	/**
	 * Order id.
	 *
	 * @var int
	 */
	private $order_id;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_API_Abstract_Intention $intent Intent instance.
	 * @param int                                $order_id Order id.
	 */
	public function __construct( $intent, $order_id ) {
		$this->intent   = $intent;
		$this->order_id = $order_id;
	}

	/**
	 * Get redirect url.
	 *
	 * @return string
	 */
	public function get_url() {
		$legacy_proxy = wcpay_get_container()->get( LegacyProxy::class ); // Perhaps we can move it inside constructor or not use it at all.
		$next_action  = $this->intent->get_next_action();

		if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
			return $next_action['redirect_to_url']['url'];
		}

		$client_secret = $this->intent->get_client_secret();

		if ( $legacy_proxy->call_static( \WC_Payments_Features::class, 'is_client_secret_encryption_enabled' ) ) {
			$client_secret = $legacy_proxy->call_function(
				'openssl_encrypt',
				$client_secret,
				'aes-128-cbc',
				substr( $this->intent->get_customer_id(), 5 ),
				0,
				str_repeat( 'WC', 8 )
			);
		}

		return sprintf(
			'#wcpay-confirm-%s:%s:%s:%s',
			substr( $this->intent->get_id(), 0, 2 ), // intents starts with pi_ or si_ so we need only to first two letters.
			$this->order_id,
			$client_secret,
			$legacy_proxy->call_function( 'wp_create_nonce', 'wcpay_update_order_status_nonce' )
		);
	}


}
