<?php
/**
 * Class Test_Gateway
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Test gateway class to avoid boilerplate setup code.
 */
class Test_Gateway extends WC_Payment_Gateway {

	/**
	 * Yes or no based on whether the method is enabled.
	 *
	 * @var string
	 */
	public $enabled = 'no';

	/**
	 * Value returned by is_payment_request_enabled().
	 *
	 * @var bool
	 */
	public $is_payment_request_enabled_value = false;

	public function __construct() {
		$this->form_fields = [
			'payment_request' => [
				'default' => 'no',
			],
		];
	}

	/**
	 * Returns whether payment request is enabled.
	 *
	 * @return bool
	 */
	public function is_payment_request_enabled() {
		return $this->is_payment_request_enabled_value;
	}
}
