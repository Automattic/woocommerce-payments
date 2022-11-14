<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for creating intents.
 */
class Create_And_Confirm_Intention extends Request {
	use Intention;

	const IMMUTABLE_PARAMS = [ 'amount' ];
	const REQUIRED_PARAMS  = [ 'amount', 'currency' ];
	const DEFAULT_PARAMS   = [
		'confirm' => 'true',
	];

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Amount setter.
	 *
	 * @param  int $amount Amount to charge.
	 * @return static      Instance of the class for method chaining.
	 */
	public function set_amount( $amount ) {
		$this->set_param( 'amount', $amount );
		return $this;
	}

	/**
	 * Currency code setter.
	 *
	 * @param  string $currency_code Currency to charge in.
	 * @return static                Instance of the class for method chaining.
	 */
	public function set_currency_code( $currency_code ) {
		$this->set_param( 'currency', $currency_code );
		return $this;
	}

	/**
	 * Payment method setter.
	 *
	 * @param  string $payment_method_id ID of payment method to process charge with.
	 * @return static                    Instance of the class for method chaining.
	 */
	public function set_payment_method( $payment_method_id ) {
		$this->set_param( 'payment_method', $payment_method_id );
		return $this;
	}

	/**
	 * Customer setter.
	 *
	 * @param  string $customer_id ID of the customer making the payment.
	 * @return static              Instance of the class for method chaining.
	 */
	public function set_customer( $customer_id ) {
		$this->set_param( 'customer', $customer_id );
		return $this;
	}

	/**
	 * Capture method setter.
	 *
	 * @param  bool $manual_capture Whether to capture funds via manual action.
	 * @return static               Instance of the class for method chaining.
	 */
	public function set_capture_method( $manual_capture = false ) {
		$this->set_param( 'capture_method', $manual_capture ? 'manual' : 'automatic' );
		return $this;
	}

	/**
	 * If the payment method should be saved to the store, this enables future usage.
	 *
	 * @return static Instance of the class for method chaining.
	 */
	public function setup_future_usage() {
		$this->set_param( 'setup_future_usage', 'off_session' );
		return $this;
	}

	/**
	 * Save payment method to platform setter.
	 *
	 * @param  bool $save_payment_method_to_platform Whether to save payment method to platform.
	 * @return static                                Instance of the class for method chaining.
	 */
	public function save_payment_method_to_platform( $save_payment_method_to_platform ) {
		$flag = $save_payment_method_to_platform ? 'true' : '';
		$this->set_param( 'save_payment_method_to_platform', $flag );
		return $this;
	}

	/**
	 * Metadata setter.
	 *
	 * @param  array $metadata Meta data values to be sent along with payment intent creation.
	 * @return static          Instance of the class for method chaining.
	 * @throws \Exception      In case there is no order number provided.
	 */
	public function set_metadata( $metadata ) {
		if ( ! isset( $metadata['order_number'] ) ) {
			throw new \Exception( 'An order number is required!' );
		}

		// The description is based on the order number here.
		$description = $this->get_intent_description( $metadata['order_number'] ?? 0 );
		$this->set_param( 'description', $description );

		// Combine the metadata with the fingerprint.
		$metadata = array_merge( $metadata, $this->get_fingerprint_metadata() );
		$this->set_param( 'metadata', $metadata );

		return $this;
	}

	/**
	 * Level 3 data setter.
	 *
	 * @param  array $level3 Level 3 data.
	 * @return static        Instance of the class for method chaining.
	 */
	public function set_level3( $level3 ) {
		$this->set_param( 'level3', $level3 );
		return $this;
	}

	/**
	 * AAAA setter.
	 *
	 * @param  bool $off_session Whether the payment is off-session (merchant-initiated), or on-session (customer-initiated).
	 * @return static            Instance of the class for method chaining.
	 */
	public function set_off_session( $off_session = true ) {
		// @todo: Convert boolean values to `true` automatically?
		$this->set_param( 'off_session', $off_session ? 'true' : '' );
		return $this;
	}

	/**
	 * Payment methods setter.
	 *
	 * @param  array $payment_methods An array of payment methods that might be used for the payment.
	 * @return static                 Instance of the class for method chaining.
	 */
	public function set_payment_methods( $payment_methods ) {
		$this->set_param( 'payment_methods_types', $payment_methods );
		return $this;
	}

	/**
	 * CVC confirmation setter.
	 *
	 * @param  string $cvc_confirmation The CVC confirmation for this payment method.
	 * @return static                   Instance of the class for method chaining.
	 */
	public function set_cvc_confirmation( $cvc_confirmation ) {
		$this->set_param( 'cvc_confirmation', $cvc_confirmation );
		return $this;
	}
}
