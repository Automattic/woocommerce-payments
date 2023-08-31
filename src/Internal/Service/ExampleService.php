<?php
/**
 * Class ExampleService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Exception;
use WCPay\Core\Mode;

/**
 * This is a service, which will be used for developing
 * DI-related functionality until there are better services
 * to test with.
 */
class ExampleService {
	/**
	 * Does something.
	 *
	 * @throws Exception Whenever Huell is not happy.
	 */
	public function do_something_with_exception() {
		throw new Exception( 'Huell is not happy.' );
	}
}
