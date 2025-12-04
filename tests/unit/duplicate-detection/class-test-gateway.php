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
	 * For mocking is_payment_request_enabled() in tests.
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
	 * Mock implementation of is_payment_request_enabled() for testing.
	 *
	 * @return bool
	 */
	public function is_payment_request_enabled() {
		return $this->is_payment_request_enabled_value;
	}
}
