<?php
/**
 * Class PaymentProcessingService
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\Service;

use WCPay\Core\Mode;

/**
 *  Payment Processing Service.
 */
class PaymentProcessingService {
	/**
	 * Mode.
	 *
	 * @var Mode
	 */
	private $mode;

	/**
	 * Instantiates the class.
	 *
	 * @param Mode $mode The Mode object.
	 */
	public function __construct( Mode $mode ) {
		$this->mode = $mode;
	}

	/**
	 * Temporary method to test the Mode.
	 *
	 * @return bool
	 */
	public function is_in_test_mode() {
		return $this->mode->is_test();
	}
}
