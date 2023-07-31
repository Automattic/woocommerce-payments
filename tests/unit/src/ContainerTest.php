<?php
/**
 * Class ContainerTest
 *
 * @package WooPayments
 */

namespace WooPayments\Tests;

use WCPAY_UnitTestCase;
use Automattic\WooCommerce\Proxies\LegacyProxy;
use stdClass;
use WCPay\Core\Mode;
use WooPayments\Container;
use WooPayments\Internal\DependencyManagement\ContainerException;
use WooPayments\Internal\DependencyManagement\ExtendedContainer;
use WooPayments\Internal\Service\ExampleService;

/**
 * Dependency injection container unit tests.
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
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();

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
		$service = $this->sut->get( ExampleService::class );
		$this->assertInstanceOf( ExampleService::class, $service );
	}

	/**
	 * Checks if the delegate container provides a WooCommerce instance.
	 */
	public function test_container_delegates() {
		$proxy = $this->sut->get( LegacyProxy::class );
		$this->assertInstanceOf( LegacyProxy::class, $proxy );
	}

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
		$result = $this->sut->get( ExampleService::class );
		$this->assertSame( $replacement_service, $result );
	}

	/**
	 * Checks that resetting an individual replacement works.
	 */
	public function test_container_resets_single_replacement() {
		// Set up: Load original and replace.
		$original = $this->sut->get( ExampleService::class );
		$this->replace_payment_processing_service();

		// Act: Reset the replacement.
		$this->test_sut->reset_replacement( ExampleService::class );
		$result = $this->sut->get( ExampleService::class );

		// Assert: The original resolution is available.
		$this->assertSame( $original, $result );
	}

	/**
	 * Checks that resetting all replacements works.
	 */
	public function test_container_resets_all_replacements() {
		// Set up: Load original and replace.
		$original = $this->sut->get( ExampleService::class );
		$this->replace_payment_processing_service();

		// Act: Reset all replacements.
		$this->test_sut->reset_all_replacements();
		$result = $this->sut->get( ExampleService::class );

		// Assert: The original resolution is available.
		$this->assertSame( $original, $result );
	}

	/**
	 * Checks whether the container delegates to the legacy container.
	 */
	public function test_container_delegates_to_legacy_container() {
		$result = $this->sut->get( Mode::class );
		$this->assertInstanceOf( Mode::class, $result );
	}

	/**
	 * Ensure that using a replacement will also work with delegate containers.
	 */
	public function test_container_handles_delegate_replacement() {
		$mode = new stdClass(); // Just a mock.
		$this->test_sut->replace( Mode::class, $mode );
		$result = $this->sut->get( Mode::class );

		$this->assertSame( $result, $mode );
	}

	/**
	 * Replaces the payment processing service within the container.
	 *
	 * @return object The replacement.
	 */
	private function replace_payment_processing_service() {
		$mock_mode           = $this->createMock( Mode::class );
		$replacement_service = new class( $mock_mode ) extends ExampleService {};

		$this->test_sut->replace( ExampleService::class, $replacement_service );

		return $replacement_service;
	}
}
