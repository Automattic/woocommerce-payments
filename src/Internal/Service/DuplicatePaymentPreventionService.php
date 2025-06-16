<?php
/**
 * Class DuplicatePaymentPreventionService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Proxy\ProxyException;

/**
 * Used for methods, which detect existing payments or payment intents,
 * and prevent creating duplicate payments.
 */
class DuplicatePaymentPreventionService {
	/**
	 * Key name for saving the current processing order_id to WC Session with the purpose
	 * of preventing duplicate payments in a single order.
	 *
	 * @type string
	 */
	const SESSION_KEY_PROCESSING_ORDER = 'wcpay_processing_order';

	/**
	 * HooksProxy instance.
	 *
	 * @var HooksProxy
	 */
	private $hooks_proxy;

	/**
	 * LegacyProxy instance.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Session service instance.
	 *
	 * @var SessionService
	 */
	private $session_service;

	/**
	 * Initializes all dependencies.
	 *
	 * @param  SessionService $session_service Session service instance.
	 * @param  HooksProxy     $hooks_proxy     Hooks proxy instance.
	 * @param  LegacyProxy    $legacy_proxy    Legacy proxy instance.
	 */
	public function __construct( SessionService $session_service, HooksProxy $hooks_proxy, LegacyProxy $legacy_proxy ) {
		$this->session_service = $session_service;
		$this->hooks_proxy     = $hooks_proxy;
		$this->legacy_proxy    = $legacy_proxy;
	}

	/**
	 * Initializes this class's hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		// Priority 21 to run right after wc_clear_cart_after_payment.
		$this->hooks_proxy->add_action( 'template_redirect', [ $this, 'clear_session_processing_order_after_landing_order_received_page' ], 21 );
	}

	/**
	 * Remove the provided order ID from the current session if it matches with the ID in the session.
	 *
	 * @param  int $order_id Order ID to remove from the session.
	 *
	 * @return void
	 */
	public function remove_session_processing_order( int $order_id ) {
		if ( $order_id === $this->get_session_processing_order() ) {
			$this->session_service->set( self::SESSION_KEY_PROCESSING_ORDER, null );
		}
	}

	/**
	 * Get the processing order ID for the current session.
	 *
	 * @return integer|null Order ID. Null if the value is not set.
	 */
	public function get_session_processing_order(): ?int {
		$val = $this->session_service->get( self::SESSION_KEY_PROCESSING_ORDER );
		return null === $val ? null : absint( $val );
	}

	/**
	 * Action to remove the order ID when customers reach its order-received page.
	 *
	 * @return void
	 * @throws ProxyException
	 */
	public function clear_session_processing_order_after_landing_order_received_page() {
		$global_wp = $this->legacy_proxy->get_global( 'wp' );

		if ( $this->legacy_proxy->call_function( 'is_order_received_page' ) && isset( $global_wp->query_vars['order-received'] ) ) {
			$order_id = absint( $global_wp->query_vars['order-received'] );
			$this->remove_session_processing_order( $order_id );
		}
	}
}
