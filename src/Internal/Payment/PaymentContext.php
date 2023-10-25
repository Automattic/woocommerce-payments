<?php
/**
 * Class PaymentContext
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use PHP_CodeSniffer\Tokenizers\PHP;
use WC_Payments_API_Abstract_Intention;
use WCPay\Internal\Payment\PaymentMethod\PaymentMethodInterface;
use WCPay\Internal\Payment\Change;

use function PHPUnit\Framework\isEmpty;

/**
 * A context object, which is shared between payment states.
 */
class PaymentContext {
	/**
	 * ID of the order, receiving a payment.
	 *
	 * @var int
	 */
	private $order_id;

	/**
	 * Contains all the context's data.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Stores the transitions of state.
	 *
	 * @var array
	 */
	private $transitions = [];

	/**
	 * Constructs the class, receiving an order ID.
	 *
	 * @param int $order_id ID of the order, receiving a payment.
	 */
	public function __construct( int $order_id ) {
		$this->order_id      = $order_id;
		$this->transitions[] = new Transition( $order_id, null, null, [], time() );
	}

	/**
	 * Returns the ID of the order requiring payment.
	 *
	 * @return int
	 */
	public function get_order_id(): ?int {
		return $this->order_id;
	}

	/**
	 * Stores the payment amount.
	 *
	 * @param int $amount Payment amount in cents.
	 */
	public function set_amount( int $amount ) {
		$this->set( 'amount', $amount );
	}

	/**
	 * Returns the payment amount.
	 *
	 * @return int|null Amount in cents.
	 */
	public function get_amount(): ?int {
		return $this->get( 'amount' );
	}

	/**
	 * Stores the payment currency.
	 *
	 * @param string $currency Lowercase payment currency.
	 */
	public function set_currency( string $currency ) {
		$this->set( 'currency', $currency );
	}

	/**
	 * Returns the payment currency in lowercase.
	 *
	 * @return string|null
	 */
	public function get_currency(): ?string {
		return $this->get( 'currency' );
	}

	/**
	 * Controls whether manual capture is enabled.
	 *
	 * @param bool $manual_capture Whether to enable it or not.
	 */
	public function toggle_manual_capture( bool $manual_capture ) {
		$this->set( 'manual_capture', $manual_capture );
	}

	/**
	 * Indicates whether the payment should be captured manually.
	 *
	 * @return bool
	 */
	public function should_capture_manually(): bool {
		return $this->get( 'manual_capture' ) ?? false;
	}

	/**
	 * Stores the order metadata.
	 *
	 * @param array $metadata Metadata to sent to the API.
	 */
	public function set_metadata( array $metadata ) {
		$this->set( 'metadata', $metadata );
	}

	/**
	 * Returns the order level 3 data if set.
	 *
	 * @return array|null
	 */
	public function get_level3_data(): ?array {
		return $this->get( 'level3_data' );
	}

	/**
	 * Stores the order level 3 data.
	 *
	 * @param array $level3_data level3_data to sent to the API.
	 */
	public function set_level3_data( array $level3_data ) {
		$this->set( 'level3_data', $level3_data );
	}

	/**
	 * Returns the order metadata if set.
	 *
	 * @return array|null
	 */
	public function get_metadata(): ?array {
		return $this->get( 'metadata' );
	}

	/**
	 * Stores the CVC confirmation.
	 *
	 * @param string $cvc_confirmation The confirmation.
	 */
	public function set_cvc_confirmation( string $cvc_confirmation = null ) {
		$this->set( 'cvc_confirmation', $cvc_confirmation );
	}

	/**
	 * Returns the CVC confirmation if set.
	 *
	 * @return string|null
	 */
	public function get_cvc_confirmation(): ?string {
		return $this->get( 'cvc_confirmation' );
	}

	/**
	 * Stores a payment's fingerprint.
	 *
	 * @param string $fingerprint The fingerprint.
	 */
	public function set_fingerprint( string $fingerprint ) {
		$this->set( 'fingerprint', $fingerprint );
	}

	/**
	 * Returns a payment's fingerprint if set.
	 *
	 * @return string|null
	 */
	public function get_fingerprint(): ?string {
		return $this->get( 'fingerprint' );
	}

	/**
	 * Stores a payment method within the context.
	 *
	 * @param PaymentMethodInterface $payment_method The payment method to use.
	 */
	public function set_payment_method( PaymentMethodInterface $payment_method ) {
		$this->set( 'payment_method', $payment_method );
	}

	/**
	 * Returns the stored payment method object, if any.
	 *
	 * @return PaymentMethodInterface|null
	 */
	public function get_payment_method(): ?PaymentMethodInterface {
		return $this->get( 'payment_method' );
	}

	/**
	 * Stores the WP user ID, associated with the payment.
	 *
	 * @param int $user_id ID of the user.
	 */
	public function set_user_id( int $user_id ) {
		$this->set( 'user_id', $user_id );
	}

	/**
	 * Returns the ID of the user if any.
	 *
	 * @return int|null
	 */
	public function get_user_id(): ?int {
		return $this->get( 'user_id' );
	}

	/**
	 * Stores the remote customer ID.
	 *
	 * @param string $customer_id ID of the customer.
	 */
	public function set_customer_id( string $customer_id ) {
		$this->set( 'customer_id', $customer_id );
	}

	/**
	 * Returns the remote customer ID.
	 *
	 * @return string|null
	 */
	public function get_customer_id(): ?string {
		return $this->get( 'customer_id' );
	}

	/**
	 * Stores the payment intent object.
	 *
	 * @param WC_Payments_API_Abstract_Intention $intent Instance of intent.
	 */
	public function set_intent( WC_Payments_API_Abstract_Intention $intent ) {
		$this->set( 'intent', $intent );
	}

	/**
	 * Returns the payment intent object.
	 *
	 * @return WC_Payments_API_Abstract_Intention|null
	 */
	public function get_intent(): ?WC_Payments_API_Abstract_Intention {
		return $this->get( 'intent' );
	}

	/**
	 * Updates state transition and associated changes
	 *
	 * @param string $state The state.
	 */
	public function log_state_transition( string $state ): void {
		$last_transition = end( $this->transitions );
		$last_transition->set_to_state( $state );
		$this->transitions[] = new Transition( $this->get_order_id(), $state, null, [], time() );
	}

	/**
	 * Returns the transitions and changes as a string that can be logged.
	 *
	 * @return string
	 */
	public function log_changes(): string {
		$log = '';
		foreach ( $this->transitions as $transition ) {
			$to_state       = $transition->get_to_state();
			$previous_state = $transition->get_from_state();
			if ( $to_state && ! $previous_state ) {
				$log .= 'Payment for order #' . $transition->get_order_id() . " initialized in '" . $to_state;
			} elseif ( $previous_state && ! $to_state ) {
				$log .= "Changes within '" . $previous_state;
			} else {
				$log .= "Transition from '" . $previous_state . "' to '" . $to_state;
			}
			$log .= "' [" . PHP_EOL;
			$log .= implode( PHP_EOL, $this->changes_to_str( $transition->get_changes() ) ) . PHP_EOL;
			$log .= ']' . PHP_EOL;
		}
		return $log;
	}

	/**
	 * Returns the changes array as a string that can be logged.
	 *
	 * @param array $changes Array of Change objects.
	 *
	 * @return array
	 */
	private function changes_to_str( $changes ) : array {
		$changes_string = array_map(
			function( Change $change ) {
				if ( $change->get_old_value() ) {
					$str = 'Changed ' . $change->get_key() . ' from ' . wp_json_encode( $change->get_old_value(), JSON_PRETTY_PRINT ) . ' to ' . wp_json_encode( $change->get_new_value(), JSON_PRETTY_PRINT );
				} else {
					$str = 'Set ' . $change->get_key() . ' to ' . wp_json_encode( $change->get_new_value(), JSON_PRETTY_PRINT );
				}
				return $str;
			},
			$changes
		);
		return $changes_string;
	}

	/**
	 * Stores an internal value.
	 *
	 * @param string $key   Property name.
	 * @param mixed  $value Value to store.
	 */
	private function set( string $key, $value ) : void {
		$this->log_change( $key, $value );
		$this->data[ $key ] = $value;
	}

	/**
	 * Log the change to a transition
	 *
	 * @param string $key   Property name.
	 * @param mixed  $value Value to store.
	 */
	private function log_change( string $key, $value ) {
		$last_transition = end( $this->transitions );
		$changes         = $last_transition->get_changes();
		$changes[]       = new Change( $key, $this->get( $key ), $value );
		$last_transition->set_changes( $changes );
	}

	/**
	 * Retrieves an internal value, if any.
	 *
	 * @param string $key Key of the property.
	 * @return mixed|null Either the stored value, or null if not set.
	 */
	private function get( string $key ) {
		return $this->data[ $key ] ?? null;
	}
}
