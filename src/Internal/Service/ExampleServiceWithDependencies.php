<?php
/**
 * Class ExampleServiceWithDependencies
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use Automattic\WooCommerce\Utilities\PluginUtil;
use WCPay\Core\Mode;

/**
 * This is a service, which will be used for developing
 * DI-related functionality until there are better services
 * to test with.
 */
class ExampleServiceWithDependencies {
	/**
	 * Example service.
	 *
	 * @var ExampleService
	 */
	private $example_service;

	/**
	 * Mode.
	 *
	 * @var Mode
	 */
	private $mode;

	/**
	 * Database utils.
	 *
	 * @var PluginUtil
	 */
	private $plugin_util;

	/**
	 * Instantiates the class.
	 *
	 * @param ExampleService $example_service A class from `src`.
	 * @param Mode           $mode            Legacy class from `includes`.
	 * @param PluginUtil     $plugin_util     WooCommerce Core class.
	 */
	public function __construct(
		ExampleService $example_service,
		Mode $mode,
		PluginUtil $plugin_util
	) {
		$this->example_service = $example_service;
		$this->mode            = $mode;
		$this->plugin_util     = $plugin_util;
	}

	/**
	 * Temporary method to test the Mode.
	 *
	 * @return bool
	 */
	public function is_in_test_mode() {
		$this->example_service->do_something_with_exception();
		return $this->mode->is_test();
	}
}
