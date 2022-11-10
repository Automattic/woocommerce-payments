<?php
/**
 * Create charge request.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Enums\Endpoints;

/**
 * Create intention request.
 */
final class Create_Intention extends Base_Request {

	/**
	 * Amount to charge.
	 *
	 * @var int $amount
	 */
	private $amount;

	/**
	 * Currency to charge in.
	 *
	 * @var string $currency_code
	 */
	private $currency_code;

	/**
	 * ID of payment method to process charge with.
	 *
	 * @var string $payment_method_id
	 */
	private $payment_method_id;

	/**
	 * ID of the customer making the payment.
	 *
	 * @var string $customer_id
	 */
	private $customer_id;

	/**
	 * Whether to capture funds via manual action.
	 *
	 * @var bool $manual_capture
	 */
	private $manual_capture;

	/**
	 * Whether to save payment method for future purchases.
	 *
	 * @var bool$save_payment_method_to_store
	 */

	private $save_payment_method_to_store;

	/**
	 * Save payment method.
	 *
	 * @var bool $save_payment_method_to_platform
	 */
	private $save_payment_method_to_platform;

	/**
	 *  Metadata
	 *
	 * @var array $metadata
	 */
	private $metadata;

	/**
	 * Level 3 data
	 *
	 * @var array $level3
	 */
	private $level3;

	/**
	 * Off session
	 *
	 * @var bool $off_session
	 */
	private $off_session;

	/**
	 * Additional req. params
	 *
	 * @var array $additional_parameters
	 */
	private $additional_parameters;

	/**
	 * Payment methods
	 *
	 * @var array|null $payment_methods
	 */
	private $payment_methods;

	/**
	 * CVC confirmation
	 *
	 * @var string|null $cvc_confirmation
	 */
	private $cvc_confirmation;

	/**
	 * Blog id
	 *
	 * @var string|null $blog_id
	 */
	private $blog_id;

	/**
	 * Order number
	 *
	 * @var string|null $order_number
	 */
	private $order_number;


	/**
	 * Set amount.
	 *
	 * @param int $amount Amount to set.
	 * @return self
	 */
	public function set_amount( $amount ) {
		$this->amount = $amount;
		return $this;
	}

	/**
	 * Set currency code.
	 *
	 * @param string $currency_code Currency code.
	 * @return self
	 */
	public function set_currency_code( $currency_code ) {
		$this->currency_code = $currency_code;
		return $this;
	}

	/**
	 * Set payment method id
	 *
	 * @param string $payment_method_id Payment method id.
	 * @return Create_Intention
	 */
	public function set_payment_method_id( $payment_method_id ) {
		$this->payment_method_id = $payment_method_id;
		return $this;
	}

	/**
	 * Set customer id.
	 *
	 * @param string $customer_id Customer id.
	 * @return self
	 */
	public function set_customer_id( $customer_id ) {
		$this->customer_id = $customer_id;
		return $this;
	}

	/**
	 * Set manual capture.
	 *
	 * @param bool $manual_capture Manual capture.
	 * @return Create_Intention
	 */
	public function set_manual_capture( $manual_capture ) {
		$this->manual_capture = $manual_capture;
		return $this;
	}

	/**
	 * Set save payment method to store.
	 *
	 * @param bool $save_payment_method_to_store Save payment method to store.
	 * @return self
	 */
	public function set_save_payment_method_to_store( $save_payment_method_to_store ) {
		$this->save_payment_method_to_store = (bool) $save_payment_method_to_store;
		return $this;
	}

	/**
	 * Save payment method to platform.
	 *
	 * @param bool $save_payment_method_to_platform Save payment method to platform.
	 * @return self
	 */
	public function set_save_payment_method_to_platform( $save_payment_method_to_platform ) {
		$this->save_payment_method_to_platform = $save_payment_method_to_platform;
		return $this;
	}

	/**
	 * Set metadata.
	 *
	 * @param array $metadata Metadata to set.
	 * @return self
	 */
	public function set_metadata( $metadata ) {
		$this->metadata = $metadata;
		return $this;
	}

	/**
	 * Set level 3 data.
	 *
	 * @param array $level3 Level 3 data.
	 * @return self
	 */
	public function set_level3( $level3 ) {
		$this->level3 = $level3;
		return $this;
	}

	/**
	 * Set off sesison
	 *
	 * @param bool $off_session Off session.
	 * @return self
	 */
	public function set_off_session( $off_session ) {
		$this->off_session = $off_session;
		return $this;
	}

	/**
	 * Set additional parameters.
	 *
	 * @param array $additional_parameters Additional params.
	 * @return self
	 */
	public function set_additional_parameters( $additional_parameters ) {
		$this->additional_parameters = $additional_parameters;
		return $this;
	}

	/**
	 * Set payment methods.
	 *
	 * @param array|null $payment_methods Set payment methods.
	 * @return self
	 */
	public function set_payment_methods( $payment_methods ) {
		$this->payment_methods = $payment_methods;
		return $this;
	}

	/**
	 * Set CVC confirmation.
	 *
	 * @param string|null $cvc_confirmation CVC confirmation.
	 * @return self
	 */
	public function set_cvc_confirmation( $cvc_confirmation ) {
		$this->cvc_confirmation = $cvc_confirmation;
		return $this;
	}

	/**
	 * Set blog id.
	 *
	 * @param string|null $blog_id Blog id.
	 * @return self
	 */
	public function set_blog_id( $blog_id ) {
		$this->blog_id = $blog_id;
		return $this;
	}

	/** Set order number.
	 *
	 * @param string $order_number Order number.
	 * @return $this
	 */
	public function set_order_number( $order_number ) {
		$this->order_number = $order_number;
		return $this;
	}

	/**
	 * Confirm intention.
	 *
	 * @return $this
	 */
	public function confirm_intention() {
		if ( ! is_array( $this->additional_parameters ) ) {
			$this->additional_parameters = [];
		}
		$this->additional_parameters['confirm'] = 'true';
		return $this;
	}

	/**
	 * Get method.
	 *
	 * @return string
	 */
	public function get_method() {
		return \Requests::POST;
	}

	/**
	 * Get route.
	 *
	 * @return string
	 */
	public function get_route() {
		return Endpoints::INTENTIONS_API;
	}
	/**
	 * Get parameters.
	 *
	 * @return array
	 */
	public function get_parameters() {

		$order_number              = $this->order_number ?? $this->metadata['order_number'] ?? 0;
		$request                   = [];
		$request['amount']         = $this->amount;
		$request['currency']       = $this->currency_code;
		$request['capture_method'] = $this->manual_capture ? 'manual' : 'automatic';
		$request['metadata']       = $this->metadata ?? [];
		$request['level3']         = $this->level3 ?? [];
		$request['description']    = sprintf(
			'Online Payment%s for %s%s',
			0 !== $order_number ? " for Order #$order_number" : '',
			str_replace( [ 'https://', 'http://' ], '', get_site_url() ),
			null !== $this->blog_id ? " blog_id $this->blog_id" : ''
		);
		if ( is_array( $this->additional_parameters ) ) {
			$request = array_merge( $request, $this->additional_parameters );
		}

		if ( $this->off_session ) {
			$request['off_session'] = 'true';
		}

		if ( $this->payment_method_id ) {
			$request['payment_method'] = $this->payment_method_id;
		}

		if ( $this->payment_methods ) {
			$request['payment_method_types'] = $this->payment_methods;
		}

		if ( $this->save_payment_method_to_store ) {
			$request['setup_future_usage'] = 'off_session';
		}

		if ( $this->save_payment_method_to_platform ) {
			$request['save_payment_method_to_platform'] = 'true';
		}

		if ( ! empty( $this->cvc_confirmation ) ) {
			$request['cvc_confirmation'] = $this->cvc_confirmation;
		}
		if ( $this->customer_id ) {
			$request['customer'] = $this->customer_id;
		}

		return $request;
	}

	/**
	 * Make sure that properties are filled.
	 *
	 * @return bool
	 */
	public function is_request_data_valid() {

		// Make sure that one of the payment method properties exist.

		if ( ! property_exists( $this, 'payment_methods' ) && ! property_exists( $this, 'payment_method_id' ) ) {
			return false;
		}

		// Make sure that other minimal required properties exist.
		if ( ! property_exists( $this, 'amount' ) || ! property_exists( $this, 'currency' ) ) {
			return false;
		}
		return true;
	}



}
