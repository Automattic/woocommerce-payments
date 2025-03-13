<?php
/**
 * Class WCPay\Tests\PaymentMethods\Configs\PaymentMethodDefinitionRegistryTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\PaymentMethods\Configs;

use WCPay\PaymentMethods\Configs\Registry\PaymentMethodDefinitionRegistry;
use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;
use WCPAY_UnitTestCase;
use ReflectionClass;

/**
 * PaymentMethodDefinitionRegistry unit tests.
 */
class PaymentMethodDefinitionRegistryTest extends WCPAY_UnitTestCase {

	/**
	 * Set up the test case.
	 *
	 * Since PaymentMethodDefinitionRegistry is a singleton that maintains state,
	 * we need to ensure each test starts with a clean slate by:
	 * 1. Resetting the singleton instance
	 * 2. Resetting the available definitions
	 * 3. Resetting the registered payment methods
	 *
	 * This prevents tests from being affected by state changes in other tests.
	 */
	public function set_up() {
		parent::set_up();

		// Reset the singleton instance before each test.
		$reflection = new ReflectionClass( PaymentMethodDefinitionRegistry::class );

		// Reset the static instance property to null.
		$instance_property = $reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );
		$instance_property->setAccessible( false );

		// Create a new instance.
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Reset available definitions.
		$available_definitions = $reflection->getProperty( 'available_definitions' );
		$available_definitions->setAccessible( true );
		$available_definitions->setValue( $registry, [] );
		$available_definitions->setAccessible( false );

		// Reset payment methods.
		$payment_methods = $reflection->getProperty( 'payment_methods' );
		$payment_methods->setAccessible( true );
		$payment_methods->setValue( $registry, [] );
		$payment_methods->setAccessible( false );
	}

	/**
	 * Test that the singleton pattern works correctly.
	 */
	public function test_singleton_pattern() {
		// Get two instances of the registry.
		$instance1 = PaymentMethodDefinitionRegistry::instance();
		$instance2 = PaymentMethodDefinitionRegistry::instance();

		// Test that both instances are the same object.
		$this->assertSame( $instance1, $instance2 );

		// Test that we cannot instantiate the class directly.
		$reflection  = new ReflectionClass( PaymentMethodDefinitionRegistry::class );
		$constructor = $reflection->getConstructor();
		$this->assertTrue( $constructor->isPrivate(), 'Constructor should be private' );
	}

	/**
	 * Test that valid payment method definitions can be registered.
	 */
	public function test_valid_payment_method_registration() {
		$registry = PaymentMethodDefinitionRegistry::instance();
		$registry->register_payment_method( MockPaymentMethodDefinition::class );

		$methods = $registry->get_all_payment_method_definitions();
		$this->assertArrayHasKey( 'mock_method', $methods );
		$this->assertEquals( MockPaymentMethodDefinition::class, $methods['mock_method'] );
	}

	/**
	 * Test that invalid payment method classes throw appropriate exceptions.
	 */
	public function test_invalid_payment_method_registration() {
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Test non-existent class.
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Payment method definition class "NonExistentClass" does not exist.' );
		$registry->register_payment_method( 'NonExistentClass' );

		// Test class that doesn't implement interface.
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Payment method definition class "' . InvalidMockPaymentMethod::class . '" must implement ' . PaymentMethodDefinitionInterface::class );
		$registry->register_payment_method( InvalidMockPaymentMethod::class );
	}

	/**
	 * Test that the same payment method cannot be registered twice.
	 */
	public function test_duplicate_payment_method_registration() {
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Register the payment method first time.
		$registry->register_payment_method( MockPaymentMethodDefinition::class );

		// Attempt to register the same payment method again.
		$registry->register_payment_method( MockPaymentMethodDefinition::class );

		// Verify that the payment method is only registered once.
		$methods = $registry->get_all_payment_method_definitions();
		$count   = array_filter(
			$methods,
			function ( $method ) {
				return MockPaymentMethodDefinition::class === $method;
			}
		);
		$this->assertCount( 1, $count );
	}

	/**
	 * Test that get_all_payment_method_definitions() returns the correct list.
	 */
	public function test_get_all_payment_method_definitions() {
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Register multiple payment methods.
		$registry->register_payment_method( MockPaymentMethodDefinition::class );
		$registry->register_payment_method( SecondMockPaymentMethodDefinition::class );

		// Get all registered methods.
		$methods = $registry->get_all_payment_method_definitions();

		// Verify that both methods are in the list.
		$this->assertCount( 2, $methods );
		$this->assertArrayHasKey( 'mock_method', $methods );
		$this->assertArrayHasKey( 'second_mock_method', $methods );
		$this->assertEquals( MockPaymentMethodDefinition::class, $methods['mock_method'] );
		$this->assertEquals( SecondMockPaymentMethodDefinition::class, $methods['second_mock_method'] );
	}

	/**
	 * Test that an empty registry returns an empty array.
	 */
	public function test_empty_registry_returns_empty_array() {
		$registry = PaymentMethodDefinitionRegistry::instance();
		$methods  = $registry->get_all_payment_method_definitions();

		$this->assertIsArray( $methods );
		$this->assertEmpty( $methods );
	}

	/**
	 * Test that init() registers all available definitions.
	 */
	public function test_init_registers_all_available_definitions() {
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Register multiple payment methods.
		$registry->register_payment_method( MockPaymentMethodDefinition::class );
		$registry->register_payment_method( SecondMockPaymentMethodDefinition::class );

		// Initialize the registry.
		$registry->init();

		// Verify that all registered methods are available.
		$methods = $registry->get_all_payment_method_definitions();
		$this->assertCount( 2, $methods );
		$this->assertArrayHasKey( 'mock_method', $methods );
		$this->assertArrayHasKey( 'second_mock_method', $methods );
		$this->assertEquals( MockPaymentMethodDefinition::class, $methods['mock_method'] );
		$this->assertEquals( SecondMockPaymentMethodDefinition::class, $methods['second_mock_method'] );
	}

	/**
	 * Test that get_available_definitions() returns the expected list of payment method definitions.
	 */
	public function test_available_definitions_match_expected_list() {
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Set up some available definitions.
		$reflection                 = new ReflectionClass( PaymentMethodDefinitionRegistry::class );
		$available_definitions_prop = $reflection->getProperty( 'available_definitions' );
		$available_definitions_prop->setAccessible( true );
		$available_definitions_prop->setValue(
			$registry,
			[
				MockPaymentMethodDefinition::class,
				SecondMockPaymentMethodDefinition::class,
			]
		);
		$available_definitions_prop->setAccessible( false );

		// Get the available definitions.
		$available_definitions = $registry->get_available_definitions();

		// Verify we get back exactly what we set up.
		$this->assertCount( 2, $available_definitions );
		$this->assertContains( MockPaymentMethodDefinition::class, $available_definitions );
		$this->assertContains( SecondMockPaymentMethodDefinition::class, $available_definitions );

		// Verify each definition is a valid payment method class.
		foreach ( $available_definitions as $definition ) {
			$this->assertTrue( class_exists( $definition ), "Definition class $definition should exist." );
			$this->assertTrue(
				is_subclass_of( $definition, PaymentMethodDefinitionInterface::class ),
				"Definition class $definition should implement PaymentMethodDefinitionInterface."
			);
		}
	}

	/**
	 * Test that get_available_payment_method_definitions() filters by currency and country.
	 */
	public function test_get_available_payment_method_definitions_filters_correctly() {
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Register our test payment methods.
		$registry->register_payment_method( MockPaymentMethodDefinition::class );
		$registry->register_payment_method( SecondMockPaymentMethodDefinition::class );

		// Test filtering by currency.
		$methods = $registry->get_available_payment_method_definitions( 'US', 'USD' );
		$this->assertCount( 2, $methods, 'Both methods should be available for USD in US.' );
		$this->assertArrayHasKey( 'mock_method', $methods );
		$this->assertArrayHasKey( 'second_mock_method', $methods );

		// Test filtering by unsupported currency.
		$methods = $registry->get_available_payment_method_definitions( 'US', 'EUR' );
		$this->assertCount( 1, $methods, 'Only one method should support EUR.' );
		$this->assertArrayHasKey( 'second_mock_method', $methods );
		$this->assertArrayNotHasKey( 'mock_method', $methods );

		// Test filtering by country.
		$methods = $registry->get_available_payment_method_definitions( 'CA', 'USD' );
		$this->assertCount( 1, $methods, 'Only one method should be available in Canada.' );
		$this->assertArrayHasKey( 'mock_method', $methods );
		$this->assertArrayNotHasKey( 'second_mock_method', $methods );
	}

	/**
	 * Test that get_available_payment_method_definitions() returns empty array when no methods match.
	 */
	public function test_get_available_payment_method_definitions_empty_when_no_matches() {
		$registry = PaymentMethodDefinitionRegistry::instance();

		// Register our test payment methods.
		$registry->register_payment_method( MockPaymentMethodDefinition::class );
		$registry->register_payment_method( SecondMockPaymentMethodDefinition::class );

		// Test with unsupported currency and country combination.
		$methods = $registry->get_available_payment_method_definitions( 'GB', 'EUR' );
		$this->assertIsArray( $methods );
		$this->assertEmpty( $methods );
	}
}
