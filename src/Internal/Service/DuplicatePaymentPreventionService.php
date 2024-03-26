<?php
/**
 * Class DuplicatePaymentPreventionService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception;
use WC_Payments_API_Payment_Intention;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Proxy\ProxyException;
use WCPay\Internal\Logger;

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
	 * Flag to indicate that a previous order with the same cart content has already paid.
	 *
	 * @type string
	 */
	const FLAG_PREVIOUS_ORDER_PAID = 'wcpay_paid_for_previous_order';

	/**
	 * Flag to indicate that a previous intention attached to the order was successful.
	 *
	 * @type string
	 */
	const FLAG_PREVIOUS_SUCCESSFUL_INTENT = 'wcpay_previous_successful_intent';

	/**
	 * OrderService instance.
	 *
	 * @var OrderService
	 */
	private $order_service;

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
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Initializes all dependencies.
	 *
	 * @param  OrderService   $order_service   Order service instance.
	 * @param  SessionService $session_service Session service instance.
	 * @param  Logger         $logger          Logger instance.
	 * @param  HooksProxy     $hooks_proxy     Hooks proxy instance.
	 * @param  LegacyProxy    $legacy_proxy    Legacy proxy instance.
	 */
	public function __construct( OrderService $order_service, SessionService $session_service, Logger $logger, HooksProxy $hooks_proxy, LegacyProxy $legacy_proxy ) {
		$this->order_service   = $order_service;
		$this->session_service = $session_service;
		$this->logger          = $logger;
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
	 * Checks if the currently attached payment intent was authorized for the current processing order.
	 *
	 * @param  int $order_id ID of the current processing order.
	 *
	 * @return WC_Payments_API_Payment_Intention|null The authorized attached payment intent, null otherwise.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_authorized_payment_intent_attached_to_order( int $order_id ): ?WC_Payments_API_Payment_Intention {
		$intent_id = $this->order_service->get_intent_id( $order_id );
		if ( is_null( $intent_id ) ) {
			return null;
		}

		// We only care about payment payment_intent.
		if ( 'pi_' !== substr( $intent_id, 0, 3 ) ) {
			return null;
		}

		try {
			$request = Get_Intention::create( $intent_id );
			$request->set_hook_args( $this->order_service->_deprecated_get_order( $order_id ) );
			/** @var WC_Payments_API_Payment_Intention $payment_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$payment_intent = $request->send();
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to fetch attached payment intent: ' . $e );
			return null;
		};

		if ( ! $payment_intent->is_authorized() ) {
			return null;
		}

		$intent_meta_order_id_raw = $payment_intent->get_metadata()['order_id'] ?? '';
		$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
		if ( $intent_meta_order_id !== $order_id ) {
			return null;
		}

		return $payment_intent;
	}

	/**
	 * Checks if the current order has the same content with the session processing order, which was already paid (ex. by a webhook).
	 *
	 * @param  int $current_order_id  ID of the current processing order.
	 *
	 * @return int|null Return the session processing order ID if it's already paid, null otherwise.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_previous_paid_duplicate_order_id( int $current_order_id ): ?int {
		$session_order_id = $this->get_session_processing_order();
		if ( null === $session_order_id ) {
			return null;
		}

		if ( $current_order_id === $session_order_id ) {
			return null;
		}

		if ( $this->order_service->get_cart_hash( $current_order_id ) !== $this->order_service->get_cart_hash( $session_order_id ) ) {
			return null;
		}

		if ( $this->order_service->get_customer_id( $current_order_id ) !== $this->order_service->get_customer_id( $session_order_id ) ) {
			return null;
		}

		if ( ! $this->order_service->is_pending( $current_order_id ) ) {
			return null;
		}

		if ( ! $this->order_service->is_paid( $session_order_id ) ) {
			return null;
		}

		return $session_order_id;
	}

	/**
	 * Does cleanup actions after detecting a duplicate order.
	 *
	 * @param  int $duplicate_order_id Detected duplicate order ID with the same cart content.
	 * @param  int $current_order_id   Current order ID in the processing.
	 *
	 * @return void
	 * @throws Order_Not_Found_Exception
	 */
	public function clean_up_when_detecting_duplicate_order( int $duplicate_order_id, int $current_order_id ) {
		$this->order_service->add_note(
			$duplicate_order_id,
			sprintf(
				/* translators: order ID integer number */
				__( 'WooCommerce Payments: detected and deleted order ID %d, which has duplicate cart content with this order.', 'woocommerce-payments' ),
				$current_order_id
			)
		);

		$this->order_service->delete( $current_order_id );

		$this->remove_session_processing_order( $duplicate_order_id );
	}

	/**
	 * Update the processing order ID for the current session.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return void
	 */
	public function update_session_processing_order( int $order_id ) {
		$this->session_service->set( self::SESSION_KEY_PROCESSING_ORDER, $order_id );
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
