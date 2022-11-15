<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use Exception;
use WC_Payments;
use WCPay\Core\Server\Request;
use WCPay\Core\Exceptions\Invalid_Request_Param;
use WC_Payments_API_Client;

/**
 * Request class for creating intents.
 */
class Create_And_Confirm_Intention extends Request {
	use Intention;
	use Level3;

	const IMMUTABLE_PARAMS = [
		// Those are up to us, we have to decide.
		'amount',
		'currency',
		'payment_method',
	];

	const REQUIRED_PARAMS = [
		'amount',
		'currency',
		'payment_method',
		'customer',
		'metadata',
	];

	const DEFAULT_PARAMS = [
		'confirm'        => true, // By the definition of the request.
		'capture_method' => 'automatic',
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
	public function set_amount( int $amount ) {
		return $this->set_param( 'amount', $amount );
	}

	/**
	 * Currency code setter.
	 *
	 * @param  string $currency_code Currency to charge in.
	 * @return static                Instance of the class for method chaining.
	 * @throws Invalid_Request_Param When the currency code is invalid.
	 */
	public function set_currency_code( string $currency_code ) {
		// No checks needed, account data should not be empty when creating intents.
		$account_data = WC_Payments::get_account_service()->get_cached_account_data();
		if ( ! in_array( $currency_code, $account_data['customer_currencies']['supported'], true ) ) {
			throw new Invalid_Request_Param(
				sprintf(
					// Translators: %s is a currency code.
					__( '%s is not a supported currency for payments.', 'woocommerce-payments' ),
					$currency_code
				),
				'currency_not_available'
			);
		}

		return $this->set_param( 'currency', $currency_code );
	}

	/**
	 * Payment method setter.
	 *
	 * @param  string $payment_method_id ID of payment method to process charge with.
	 * @return static                    Instance of the class for method chaining.
	 */
	public function set_payment_method( $payment_method_id ) {
		$this->validate_stripe_id( $payment_method_id, [ 'pm', 'src' ] );
		return $this->set_param( 'payment_method', $payment_method_id );
	}

	/**
	 * Customer setter.
	 *
	 * @param  string $customer_id ID of the customer making the payment.
	 * @return static              Instance of the class for method chaining.
	 */
	public function set_customer( string $customer_id ) {
		$this->validate_stripe_id( $customer_id, 'cus' );
		return $this->set_param( 'customer', $customer_id );
	}

	/**
	 * Capture method setter.
	 *
	 * @param  bool $manual_capture Whether to capture funds via manual action.
	 * @return static               Instance of the class for method chaining.
	 */
	public function set_capture_method( bool $manual_capture = false ) {
		return $this->set_param( 'capture_method', $manual_capture ? 'manual' : 'automatic' );
	}

	/**
	 * If the payment method should be saved to the store, this enables future usage.
	 *
	 * @return static Instance of the class for method chaining.
	 */
	public function setup_future_usage() {
		return $this->set_param( 'setup_future_usage', 'off_session' );
	}

	/**
	 * Metadata setter.
	 *
	 * @param  array $metadata       Meta data values to be sent along with payment intent creation.
	 * @return static                Instance of the class for method chaining.
	 * @throws Invalid_Request_Param In case there is no order number provided.
	 */
	public function set_metadata( $metadata ) {
		if ( ! isset( $metadata['order_number'] ) ) {
			throw new Invalid_Request_Param(
				__( 'An order number is required!', 'woocommerce-payments' ),
				'missing_metadata_order_number'
			);
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
		if ( empty( $level3 ) || ! is_array( $level3 ) ) {
			return $this;
		}

		return $this->set_param( 'level3', $this->fix_level3_data( $level3 ) );
	}

	/**
	 * Off-session setter.
	 *
	 * @param  bool $off_session Whether the payment is off-session (merchant-initiated), or on-session (customer-initiated).
	 * @return static            Instance of the class for method chaining.
	 */
	public function set_off_session( bool $off_session = true ) {
		// This one is tricky. We can have `true`, but otherwise we need to get rid of the parameter.
		if ( $off_session ) {
			return $this->set_param( 'off_session', true );
		} else {
			return $this->unset_param( 'off_session' );
		}
	}

	/**
	 * Payment methods setter.
	 *
	 * @param  array $payment_methods An array of payment methods that might be used for the payment.
	 * @return static                 Instance of the class for method chaining.
	 * @throws Invalid_Request_Param  When there are no payment methods provided.
	 */
	public function set_payment_methods( array $payment_methods ) {
		// Hard to validate without hardcoding a list here.
		if ( empty( $payment_methods ) ) {
			throw new Invalid_Request_Param(
				__( 'Intentions require at least one payment method', 'woocommerce-payments' ),
				'missing_payment_method_types'
			);
		}

		return $this->set_param( 'payment_methods_types', $payment_methods );
	}

	/**
	 * CVC confirmation setter.
	 *
	 * @param  string $cvc_confirmation The CVC confirmation for this payment method (Optional).
	 * @return static                   Instance of the class for method chaining.
	 */
	public function set_cvc_confirmation( $cvc_confirmation = null ) {
		return $this->set_param( 'cvc_confirmation', $cvc_confirmation );
	}

	/**
	 * Formats the response from the server.
	 *
	 * @param  mixed $intention_array The response from `WC_Payments_API_Client::request`.
	 * @return mixed                  Either the same response, or the correct object.
	 */
	public function format_response( $intention_array ) {
		return WC_Payments::get_payments_api_client()->deserialize_intention_object_from_array( $intention_array );
	}
}
