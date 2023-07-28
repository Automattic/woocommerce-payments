<?php
/**
 * Class ContainerTest
 *
 * @package WooPayments
 */

namespace WooPayments\Tests;

use WCPAY_UnitTestCase;
use Automattic\WooCommerce\Proxies\LegacyProxy;
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
	 * Ensures that the container is available through itself.
	 */
	public function test_container_contains_itself() {
		$this->assertInstanceOf( Container::class, wcpay_get_container()->get( Container::class ) );
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

	/**
	 * Ensures that a class can be replaced within the container during tests.
	 */
	public function test_container_allows_replacement() {
		$replacement_service = new class() extends PaymentProcessingService {

		};

		$container = wcpay_get_test_container();
		$container->replace( PaymentProcessingService::class, $replacement_service );

		$this->assertTrue( true );
	}
}
