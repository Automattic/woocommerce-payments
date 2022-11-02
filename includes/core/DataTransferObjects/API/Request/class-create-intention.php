<?php

namespace WCPay\Core\DataTransferObjects\API\Request;

use WCPay\Core\DataTransferObjects\Data_Transfer_Object;

final class Create_Intention extends Data_Transfer_Object
{
	/**
	 * @var int $amount Amount to charge.
	 */
	private $amount;

	/**
	 * @var string $currency_code Currency tp charge in.
	 */
	private $currency_code;

	/**
	 * @var string $payment_method_id ID of payment method to process charge with.
	 */
	private $payment_method_id;

	/**
	 * @var string $customer_id ID of the customer making the payment.
	 */
	private $customer_id;

	/**
	 * @var bool $manual_capture Whether to capture funds via manual action.
	 */
	private $manual_capture;

	/**
	 * @var bool$save_payment_method_to_store Whether to save payment method for future purchases.
	 */

	private $save_payment_method_to_store;

	/**
	 * @var bool $save_payment_method_to_platform Save payment method.
	 */
	private $save_payment_method_to_platform;

	/**
	 * @var array $metadata Metadata
	 */
	private $metadata;

	/**
	 * @var array $level3 Level 3 data
	 */
	private $level3;

	/**
	 * @var bool $off_session Off session
	 */
	private $off_session;

	/**
	 * @var array $additional_parameters Additional req. params
	 */
	private $additional_parameters;

	/**
	 * @var array|null $payment_methods Payment methods
	 */
	private $payment_methods;

	/**
	 * @var string|null $cvc_confirmation CVC confirmation
	 */
	private $cvc_confirmation;

	/**
	 * @var string|null $blog_id Blog id
	 */
	private $blog_id;

	/**
	 * @param int $amount Amount
	 * @param string $currency_code Currency code
	 * @param string $payment_method_id
	 * @param string $customer_id
	 * @param bool $manual_capture
	 * @param bool $save_payment_method_to_store
	 * @param bool $save_payment_method_to_platform
	 * @param array $metadata
	 * @param array $level3
	 * @param bool $off_session
	 * @param array $additional_parameters
	 * @param array|null $payment_methods
	 * @param string|null $cvc_confirmation
	 * @param string|null $blog_id
	 */
	public function __construct(
		$amount,
		$currency_code,
		$payment_method_id,
		$customer_id,
		$manual_capture = false,
		$save_payment_method_to_store = false,
		$save_payment_method_to_platform = false,
		$metadata = [],
		$level3 = [],
		$off_session = false,
		$additional_parameters = [],
		$payment_methods = null,
		$cvc_confirmation = null,
		$blog_id = null
	)
	{
		$this->amount = $amount;
		$this->currency_code = $currency_code;
		$this->payment_method_id = $payment_method_id;
		$this->customer_id = $customer_id;
		$this->manual_capture = $manual_capture;
		$this->save_payment_method_to_store = $save_payment_method_to_store;
		$this->save_payment_method_to_platform = $save_payment_method_to_platform;
		$this->metadata = $metadata;
		$this->level3 = $level3;
		$this->off_session = $off_session;
		$this->additional_parameters = $additional_parameters;
		$this->payment_methods = $payment_methods;
		$this->cvc_confirmation = $cvc_confirmation;
		$this->blog_id = $blog_id;
	}

	/**
	 * @return int
	 */
	public function get_amount()
	{
		return $this->amount;
	}

	/**
	 * @return string
	 */
	public function get_currency_code()
	{
		return $this->currency_code;
	}

	/**
	 * @return string
	 */
	public function get_payment_method_id()
	{
		return $this->payment_method_id;
	}

	/**
	 * @return string
	 */
	public function get_customer_id()
	{
		return $this->customer_id;
	}

	/**
	 * @return bool
	 */
	public function is_manual_capture()
	{
		return $this->manual_capture;
	}

	/**
	 * @return bool
	 */
	public function is_save_payment_method_to_store()
	{
		return $this->save_payment_method_to_store;
	}

	/**
	 * @return bool
	 */
	public function is_save_payment_method_to_platform()
	{
		return $this->save_payment_method_to_platform;
	}

	/**
	 * @return array
	 */
	public function get_metadata()
	{
		return $this->metadata;
	}

	/**
	 * @return array
	 */
	public function get_level_3()
	{
		return $this->level3;
	}

	/**
	 * @return bool
	 */
	public function is_off_session()
	{
		return $this->off_session;
	}

	/**
	 * @return array
	 */
	public function get_additional_parameters()
	{
		return $this->additional_parameters;
	}

	/**
	 * @return array|null
	 */
	public function get_payment_methods()
	{
		return $this->payment_methods;
	}

	/**
	 * @return string|null
	 */
	public function get_cvc_confirmation()
	{
		return $this->cvc_confirmation;
	}

	/**
	 * @return string|null
	 */
	public function get_blog_id()
	{
		return $this->blog_id;
	}

	/**
	 * Transform DTO to wcpay request.
	 *
	 * @return array
	 */
	public function to_wcpay_request() {

		$order_number = $this->metadata['order_number'] ?? 0;
		$request = [];
		$request['amount']         = $this->amount;
		$request['currency']       = $this->currency_code;
		//$request['confirm']        = 'true';
		$request['payment_method'] = $this->payment_method_id;
		$request['customer']       = $this->customer_id;
		$request['capture_method'] = $this->manual_capture ? 'manual' : 'automatic';
		$request['metadata']       = $this->metadata;
		$request['level3']         = $this->level3;
		$request['description']    =  sprintf(
			'Online Payment%s for %s%s',
			0 !== $order_number ? " for Order #$order_number" : '',
			str_replace( [ 'https://', 'http://' ], '', get_site_url() ),
			null !== $this->blog_id ? " blog_id $this->blog_id" : ''
		);
		$request = array_merge( $request, $this->additional_parameters );

		if ( $this->off_session ) {
			$request['off_session'] = 'true';
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




}
