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

	public function __construct() {
		$this->form_fields = [
			'payment_request' => [
				'default' => 'no',
			],
		];
	}
}
