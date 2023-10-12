<?php
/**
 * Class AuthenticationRequiredState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\CheckoutEncryptionService;

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
	 * Checkout Encryption service
	 *
	 * @var CheckoutEncryptionService
	 */
	private $checkout_encryption_service;


	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory              $state_factory Factory for payment states.
	 * @param LegacyProxy               $legacy_proxy Legacy proxy.
	 * @param CheckoutEncryptionService $checkout_encryption_service Service for encrypting checkout data.
	 */
	public function __construct(
		StateFactory $state_factory,
		LegacyProxy $legacy_proxy,
		CheckoutEncryptionService $checkout_encryption_service
	) {
		parent::__construct( $state_factory );

		$this->legacy_proxy                = $legacy_proxy;
		$this->checkout_encryption_service = $checkout_encryption_service;
	}

	/**
	 * Get redirect url.
	 *
	 * @return string
	 */
	public function get_redirect_url() {
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
			$this->checkout_encryption_service->encrypt_client_secret( $intent->get_customer_id(), $intent->get_client_secret() ),
			$this->legacy_proxy->call_function( 'wp_create_nonce', 'wcpay_update_order_status_nonce' )
		);
	}
}
