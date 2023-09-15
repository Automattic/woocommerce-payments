<?php
/**
 * Class ContainerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests;

use WCPAY_UnitTestCase;
use Automattic\WooCommerce\Proxies\LegacyProxy;
use Automattic\WooCommerce\Utilities\PluginUtil;
use stdClass;
use WCPay\Container;
use WCPay\Core\Mode;
use WCPay\Internal\DependencyManagement\ContainerException;
use WCPay\Internal\DependencyManagement\ExtendedContainer;
use WCPay\Internal\DependencyManagement\DelegateContainer\WooContainer;
use WCPay\Internal\DependencyManagement\DelegateContainer\LegacyContainer;
use WCPay\Internal\Service\ExampleService;
use WCPay\Internal\Service\ExampleServiceWithDependencies;
use PHPUnit\Framework\MockObject\MockObject;

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
	 * A mock of the WooContainer.
	 *
	 * @var WooContainer|MockObject
	 */
	private $mock_woo_container;

	/**
	 * A mock of the legacy container.
	 *
	 * @var LegacyContainer|MockObject
	 */
	private $mock_legacy_container;

	/**
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Setup the mock, and make sure the globals are fresh.
		$this->mock_woo_container        = $this->createMock( WooContainer::class );
		$this->mock_legacy_container     = $this->createMock( LegacyContainer::class );
		$GLOBALS['wcpay_container']      = new Container( $this->mock_legacy_container, $this->mock_woo_container );
		$GLOBALS['wcpay_test_container'] = null;

		$this->sut      = wcpay_get_container();
		$this->test_sut = wcpay_get_test_container();
	}

	/**
	 * Cleans up global replacements after the class.
	 *
	 * Without this, other `src` tests will fail.
	 */
	public static function tearDownAfterClass(): void {
		parent::tearDownAfterClass();

		$GLOBALS['wcpay_container']      = null;
		$GLOBALS['wcpay_test_container'] = null;
	}

	/**
	 * Tests the `wcpay_get_container` function.
	 */
	public function test_function() {
		unset( $GLOBALS['wcpay_container'] );
		$this->assertInstanceOf( Container::class, wcpay_get_container() );
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
	 * Checks if a service is available through the container.
	 */
	public function test_container_has_service() {
		$this->assertTrue( $this->sut->has( ExampleService::class ) );
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
		$replacement_service = $this->replace_example_service();

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
		$this->replace_example_service();

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
		$this->replace_example_service();

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
		$mock_mode = $this->createMock( Mode::class );

		$this->mock_legacy_container
			->expects( $this->once() )
			->method( 'has' )
			->with( Mode::class )
			->willReturn( true );

		$this->mock_legacy_container
			->expects( $this->once() )
			->method( 'get' )
			->with( Mode::class )
			->willReturn( $mock_mode );

		$result = $this->sut->get( Mode::class );
		$this->assertSame( $mock_mode, $result );
	}

	/**
	 * Ensure that using a replacement will also work with delegate containers.
	 */
	public function test_container_handles_delegate_replacement() {
		$mock_mode = new stdClass(); // Just a mock.

		// The ExtendedContainer will check whether a delegate provides the class.
		$this->mock_legacy_container
			->expects( $this->once() )
			->method( 'has' )
			->with( Mode::class )
			->willReturn( true );

		// But should never try to instantiate it.
		$this->mock_legacy_container
			->expects( $this->never() )
			->method( 'get' );

		$this->test_sut->replace( Mode::class, $mock_mode );
		$result = $this->sut->get( Mode::class );

		$this->assertSame( $result, $mock_mode );
	}

	/**
	 * Ensures that delegate replacements can be reset.
	 */
	public function test_container_handles_delegate_replacement_reset() {
		$mock_mode = $this->createMock( Mode::class );

		/**
		 * The ExtendedContainer will check whether a delegate provides the class:
		 * - Before replacing it.
		 * - When trying to retrieve it after reset.
		 */
		$this->mock_legacy_container
			->expects( $this->exactly( 2 ) )
			->method( 'has' )
			->with( Mode::class )
			->willReturn( true );

		$this->test_sut->replace( Mode::class, new stdClass() ); // Just a mock.

		$this->test_sut->reset_replacement( Mode::class );

		$this->mock_legacy_container
			->expects( $this->once() )
			->method( 'get' )
			->with( Mode::class )
			->willReturn( $mock_mode );

		$result = $this->sut->get( Mode::class );
		$this->assertSame( $result, $mock_mode );
	}

	/**
	 * Test that all containers are linked with a mix of dependencies.
	 */
	public function test_all_containers() {
		$this->mock_legacy_container
			->expects( $this->exactly( 4 ) )
			->method( 'has' )
			->withConsecutive( [ Mode::class ], [ Mode::class ], [ PluginUtil::class ], [ PluginUtil::class ] )
			->willReturnOnConsecutiveCalls( true, true, false, false );

		$this->mock_legacy_container
			->expects( $this->once() )
			->method( 'get' )
			->with( Mode::class )
			->willReturn( $this->createMock( Mode::class ) );

		$this->mock_woo_container
			->expects( $this->exactly( 2 ) )
			->method( 'has' )
			->with( PluginUtil::class )
			->willReturn( true );

		$this->mock_woo_container
			->expects( $this->once() )
			->method( 'get' )
			->with( PluginUtil::class )
			->willReturn( new PluginUtil() ); // final class, cannot be mocked.

		$result = $this->sut->get( ExampleServiceWithDependencies::class );
		$this->assertInstanceOf( ExampleServiceWithDependencies::class, $result );
	}

	/**
	 * Replaces the example service within the container.
	 *
	 * @return object The replacement.
	 */
	private function replace_example_service() {
		$mock_mode           = $this->createMock( Mode::class );
		$replacement_service = new class( $mock_mode ) extends ExampleService {};

		$this->test_sut->replace( ExampleService::class, $replacement_service );

		return $replacement_service;
	}
}
