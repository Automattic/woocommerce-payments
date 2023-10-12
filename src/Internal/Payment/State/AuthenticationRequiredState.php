<?php
/**
 * Class AuthenticationRequiredState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Service\CheckoutEncryptionService;

/**
 * The state, which indicates that the payment processing has been completed.
 */
class AuthenticationRequiredState extends AbstractPaymentState {
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
	 * @param CheckoutEncryptionService $checkout_encryption_service Service for encrypting checkout data.
	 */
	public function __construct(
		StateFactory $state_factory,
		CheckoutEncryptionService $checkout_encryption_service
	) {
		parent::__construct( $state_factory );

		$this->checkout_encryption_service = $checkout_encryption_service;
	}

	/**
	 * Get response that will be sent to the client.
	 */
	public function get_response(): array {
		$context     = $this->get_context();
		$intent      = $context->get_intent();
		$next_action = $intent->get_next_action();
		if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
			$response = [
				'result'   => 'success',
				'redirect' => $next_action['redirect_to_url']['url'],
			];
		} else {
			if ( ! $context->get_payment_method() ) {
				$payment_method_id = $context->get_payment_method()->get_id();
			} else {
				$payment_method_id = $intent->get_payment_method_id();
			}
			$response = [
				'result'         => 'success',
				// Include a new nonce for update_order_status to ensure the update order
				// status call works when a guest user creates an account during checkout.
				'redirect'       => sprintf(
					'#wcpay-confirm-%s:%s:%s:%s',
					$context->get_amount() > 0 ? 'pi' : 'si',
					$context->get_order_id(),
					$this->checkout_encryption_service->encrypt_client_secret( $intent->get_customer_id(), $intent->get_client_secret() ),
					wp_create_nonce( 'wcpay_update_order_status_nonce' )
				),
				// Include the payment method ID so the Blocks integration can save cards.
				'payment_method' => $payment_method_id,
			];
		}

		return $response;
	}

}
