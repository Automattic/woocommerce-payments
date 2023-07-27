<?php
/**
 * Class ContainerTest
 *
 * @package WooPayments
 */

namespace WooPayments\Tests;

use Automattic\WooCommerce\Proxies\LegacyProxy;
use WCPAY_UnitTestCase;
use WooPayments\Container;
use WooPayments\Internal\Service\PaymentProcessingService;

/**
 * Payment processing service unit tests.
 */
class ContainerTest extends WCPAY_UnitTestCase {
	/**
	 * Verifies that the global function returns a container.
	 */
	public function test_wcpay_get_container_returns_container() {
		$this->assertInstanceOf( Container::class, wcpay_get_container() );
	}

	/**
	 * Checks if a service can be loaded through the container.
	 */
	public function test_container_loads_service() {
		$service = wcpay_get_container()->get( PaymentProcessingService::class );
		$this->assertInstanceOf( PaymentProcessingService::class, $service );
	}

	/**
	 * Checks if the delegate container provides a WooCommerce instance.
	 */
	public function test_container_delegates() {
		$proxy = wcpay_get_container()->get( LegacyProxy::class );
		$this->assertInstanceOf( LegacyProxy::class, $proxy );
	}
}
