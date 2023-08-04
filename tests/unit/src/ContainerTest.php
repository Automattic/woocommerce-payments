<?php
/**
 * Class ContainerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests;

use WCPAY_UnitTestCase;
use Automattic\WooCommerce\Proxies\LegacyProxy;
use stdClass;
use WCPay\Container;
use WCPay\Internal\DependencyManagement\ContainerException;
use WCPay\Internal\DependencyManagement\DelegateContainer\WooContainer;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Internal\DependencyManagement\ExtendedContainer;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Payment processing service unit tests.
 *
 * This one has a couple of Service under Test (sut) props, because
 * we want to check the result of the main container, while manipulating
 * the internal instance.
 */
class ContainerTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the container, which will be tested.
	 *
	 * @var Container
	 */
	private $sut;

	/**
	 * Holds the extended internal container, which will be tested.
	 *
	 * @var ExtendedContainer
	 */
	private $test_sut;

	/**
	 * A mock of the WooContainer.
	 *
	 * @var WooContainer|MockObject
	 */
	private $mock_woo_container;

	/**
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Setup the mock, and make sure the globals are fresh.
		$this->mock_woo_container        = $this->createMock( WooContainer::class );
		$GLOBALS['wcpay_container']      = new Container( $this->mock_woo_container );
		$GLOBALS['wcpay_test_container'] = null;

		$this->sut      = wcpay_get_container();
		$this->test_sut = wcpay_get_test_container();
	}

	/**
	 * Verifies that the global function returns a container.
	 */
	public function test_wcpay_get_container_returns_container() {
		$this->assertInstanceOf( Container::class, $this->sut );
	}

	/**
	 * Ensures that the container is available through itself.
	 */
	public function test_container_contains_itself() {
		$this->assertInstanceOf( Container::class, $this->sut->get( Container::class ) );
	}

	/**
	 * Checks if a service can be loaded through the container.
	 */
	public function test_container_loads_service() {
		$service = $this->sut->get( PaymentProcessingService::class );
		$this->assertInstanceOf( PaymentProcessingService::class, $service );
	}

	/**
	 * Checks if the delegate container provides a WooCommerce instance.
	 */
	public function test_container_delegates() {
		$proxy = new LegacyProxy();

		$this->mock_woo_container->expects( $this->once() )
			->method( 'has' )
			->with( LegacyProxy::class )
			->willReturn( true );

		$this->mock_woo_container->expects( $this->once() )
			->method( 'get' )
			->with( LegacyProxy::class )
			->willReturn( $proxy );

		$result = $this->sut->get( LegacyProxy::class );
		$this->assertSame( $proxy, $result );
	}

	/**
	 * Makes sure that the container does not allow replacements of
	 * classes that it is not aware of.
	 */
	public function test_container_doesnt_allow_replacements_of_unknowns() {
		$this->expectException( ContainerException::class );
		$this->test_sut->replace( 'UnknownClassThatDoesNotExist', new stdClass() );
	}

	/**
	 * Ensures that a class can be replaced within the container during tests.
	 */
	public function test_container_allows_replacement() {
		// Set up: Store the replacement in the extended (test) container.
		$replacement_service = $this->replace_payment_processing_service();

		// Assert: The mock is returned.
		$result = $this->sut->get( PaymentProcessingService::class );
		$this->assertSame( $replacement_service, $result );
	}

	/**
	 * Checks that resetting an individual replacement works.
	 */
	public function test_container_resets_single_replacement() {
		// Set up: Load original and replace.
		$original = $this->sut->get( PaymentProcessingService::class );
		$this->replace_payment_processing_service();

		// Act: Reset the replacement.
		$this->test_sut->reset_replacement( PaymentProcessingService::class );
		$result = $this->sut->get( PaymentProcessingService::class );

		// Assert: The original resolution is available.
		$this->assertSame( $original, $result );
	}

	/**
	 * Checks that resetting all replacements works.
	 */
	public function test_container_resets_all_replacements() {
		// Set up: Load original and replace.
		$original = $this->sut->get( PaymentProcessingService::class );
		$this->replace_payment_processing_service();

		// Act: Reset all replacements.
		$this->test_sut->reset_all_replacements();
		$result = $this->sut->get( PaymentProcessingService::class );

		// Assert: The original resolution is available.
		$this->assertSame( $original, $result );
	}

	/**
	 * Replaces the payment processing service within the container.
	 *
	 * @return object The replacement.
	 */
	private function replace_payment_processing_service() {
		$replacement_service = new class() extends PaymentProcessingService {};

		$this->test_sut->replace( PaymentProcessingService::class, $replacement_service );

		return $replacement_service;
	}
}
