<?php
/**
 * Class ExampleService
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\Service;

use WCPay\Core\Mode;

/**
 * This is a service, which will be used for developing
 * DI-related functionality until there are better services
 * to test with.
 */
class ExampleService {
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
