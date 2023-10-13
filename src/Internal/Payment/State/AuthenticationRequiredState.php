<?php
/**
 * Class AuthenticationRequiredState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Proxy\LegacyProxy;
/**
 * The state, which indicates that the payment processing has been completed.
 */
class AuthenticationRequiredState extends AbstractPaymentState {

	/**
	 * Legacy Proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory $state_factory Factory for payment states.
	 * @param LegacyProxy  $legacy_proxy Legacy proxy.
	 */
	public function __construct(
		StateFactory $state_factory,
		LegacyProxy $legacy_proxy
	) {
		parent::__construct( $state_factory );

		$this->legacy_proxy = $legacy_proxy;
	}

	/**
	 * Get authentication redirect url.
	 *
	 * @return string
	 */
	public function get_authentication_url() {
		$context     = $this->get_context();
		$intent      = $context->get_intent();
		$next_action = $intent->get_next_action();

		if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
			return $next_action['redirect_to_url']['url'];
		}

		return sprintf(
			'#wcpay-confirm-%s:%s:%s:%s',
			$context->get_amount() > 0 ? 'pi' : 'si',
			$context->get_order_id(),
			$this->encrypt_client_secret( $intent->get_customer_id(), $intent->get_client_secret() ),
			$this->legacy_proxy->call_function( 'wp_create_nonce', 'wcpay_update_order_status_nonce' )
		);
	}

	/**
	 * Encrypt client secret for the client.
	 *
	 * @param string $customer_id Customer id.
	 * @param string $client_secret Client secret.
	 *
	 * @return string
	 */
	private function encrypt_client_secret( string $customer_id, string $client_secret ): string {
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
