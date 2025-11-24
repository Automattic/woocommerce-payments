<?php
/**
 * Class WCPay\Tests\PaymentMethods\Configs\Utils\PaymentMethodUtilsTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\PaymentMethods\Configs\Utils;

use WCPay\PaymentMethods\Configs\Utils\PaymentMethodUtils;
use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;
use WCPay\PaymentMethods\Configs\Registry\PaymentMethodDefinitionRegistry;
use WCPay\Tests\PaymentMethods\Configs\MockPaymentMethodDefinition;
use WCPay\Tests\PaymentMethods\Configs\SecondMockPaymentMethodDefinition;
use WCPAY_UnitTestCase;
use ReflectionClass;

/**
 * PaymentMethodUtils unit tests.
 */
class PaymentMethodUtilsTest extends WCPAY_UnitTestCase {

	/**
	 * PaymentMethodDefinitionRegistry is a singleton that maintains state.
	 * But we need to ensure each test starts with a clean slate by resetting the registry.
	 */
	public function set_up() {
		parent::set_up();

		// on each test, reset the global definition registry class.
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
	 * Helper method to create a mock payment method definition with given capabilities.
	 *
	 * @param array $capabilities Array of capabilities.
	 * @return string The class name of the created definition.
	 */
	private function create_mock_definition( array $capabilities ): string {
		$definition = new class( $capabilities ) {
			/**
			 * @var array The capabilities (injected) for the mocked payment definition.
			 */
			private static $static_capabilities;

			public function __construct( array $capabilities ) {
				self::$static_capabilities = $capabilities;
			}

			public static function get_capabilities(): array {
				return self::$static_capabilities ?? [];
			}
		};

		return get_class( $definition );
	}

	/**
	 * Test that get_stripe_id() correctly appends '_payments'.
	 */
	public function test_get_stripe_id() {
		$this->assertEquals( 'test_payments', PaymentMethodUtils::get_stripe_id( 'test' ) );
	}

	/**
	 * Test that is_available_for() works correctly with supported currency and country.
	 */
	public function test_is_available_for_with_supported_currency_and_country() {
		$supported_currencies = [ 'USD', 'CAD' ];
		$supported_countries  = [ 'US', 'CA' ];

		// Test with supported currency and country.
		$this->assertTrue(
			PaymentMethodUtils::is_available_for( $supported_currencies, $supported_countries, 'USD', 'US' ),
			'Should be available for USD in US'
		);

		$this->assertTrue(
			PaymentMethodUtils::is_available_for( $supported_currencies, $supported_countries, 'CAD', 'CA' ),
			'Should be available for CAD in CA'
		);
	}

	/**
	 * Test that is_available_for() works correctly with unsupported currency.
	 */
	public function test_is_available_for_with_unsupported_currency() {
		$supported_currencies = [ 'USD', 'CAD' ];
		$supported_countries  = [ 'US', 'CA' ];

		$this->assertFalse(
			PaymentMethodUtils::is_available_for( $supported_currencies, $supported_countries, 'EUR', 'US' ),
			'Should not be available for EUR in US'
		);
	}

	/**
	 * Test that is_available_for() works correctly with unsupported country.
	 */
	public function test_is_available_for_with_unsupported_country() {
		$supported_currencies = [ 'USD', 'CAD' ];
		$supported_countries  = [ 'US', 'CA' ];

		$this->assertFalse(
			PaymentMethodUtils::is_available_for( $supported_currencies, $supported_countries, 'USD', 'GB' ),
			'Should not be available for USD in GB'
		);
	}

	/**
	 * Test that is_available_for() works correctly with empty support arrays.
	 */
	public function test_is_available_for_with_empty_support_arrays() {
		// Empty arrays should allow all currencies and countries.
		$this->assertTrue(
			PaymentMethodUtils::is_available_for( [], [], 'USD', 'US' ),
			'Empty arrays should allow all currencies and countries'
		);

		$this->assertTrue(
			PaymentMethodUtils::is_available_for( [], [], 'EUR', 'GB' ),
			'Empty arrays should allow all currencies and countries'
		);
	}

	/**
	 * Data provider for test_is_bnpl.
	 *
	 * @return array
	 */
	public function is_bnpl_provider(): array {
		return [
			'with BNPL capability'    => [
				[ PaymentMethodCapability::BUY_NOW_PAY_LATER ],
				true,
				'Should identify BNPL capability',
			],
			'without BNPL capability' => [
				[ PaymentMethodCapability::TOKENIZATION ],
				false,
				'Should not identify non-BNPL capability as BNPL',
			],
			'with empty capabilities' => [
				[],
				false,
				'Empty capabilities should not be identified as BNPL',
			],
		];
	}

	/**
	 * Test that is_bnpl() correctly identifies BNPL methods.
	 *
	 * @dataProvider is_bnpl_provider
	 *
	 * @param array  $capabilities Array of capabilities.
	 * @param bool   $expected Expected result.
	 * @param string $message Assertion message.
	 */
	public function test_is_bnpl( array $capabilities, bool $expected, string $message ) {
		$definition_class = $this->create_mock_definition( $capabilities );
		$this->assertEquals( $expected, PaymentMethodUtils::is_bnpl( $definition_class ), $message );
	}

	/**
	 * Data provider for test_is_reusable.
	 *
	 * @return array
	 */
	public function is_reusable_provider(): array {
		return [
			'with tokenization capability'    => [
				[ PaymentMethodCapability::TOKENIZATION ],
				true,
				'Should identify tokenizable capability',
			],
			'without tokenization capability' => [
				[ PaymentMethodCapability::BUY_NOW_PAY_LATER ],
				false,
				'Should not identify non-tokenizable capability as reusable',
			],
			'with empty capabilities'         => [
				[],
				false,
				'Empty capabilities should not be identified as reusable',
			],
		];
	}

	/**
	 * Test that is_reusable() identifies tokenizable methods.
	 *
	 * @dataProvider is_reusable_provider
	 *
	 * @param array  $capabilities Array of capabilities.
	 * @param bool   $expected Expected result.
	 * @param string $message Assertion message.
	 */
	public function test_is_reusable( array $capabilities, bool $expected, string $message ) {
		$definition_class = $this->create_mock_definition( $capabilities );
		$this->assertEquals( $expected, PaymentMethodUtils::is_reusable( $definition_class ), $message );
	}

	/**
	 * Data provider for test_accepts_only_domestic_payments.
	 *
	 * @return array
	 */
	public function accepts_only_domestic_payments_provider(): array {
		return [
			'with domestic-only capability'    => [
				[ PaymentMethodCapability::DOMESTIC_TRANSACTIONS_ONLY ],
				true,
				'Should identify domestic-only capability',
			],
			'without domestic-only capability' => [
				[ PaymentMethodCapability::TOKENIZATION ],
				false,
				'Should not identify non-domestic-only capability as domestic-only',
			],
			'with empty capabilities'          => [
				[],
				false,
				'Empty capabilities should not be identified as domestic-only',
			],
		];
	}

	/**
	 * Test that accepts_only_domestic_payments() identifies domestic-only methods.
	 *
	 * @dataProvider accepts_only_domestic_payments_provider
	 *
	 * @param array  $capabilities Array of capabilities.
	 * @param bool   $expected Expected result.
	 * @param string $message Assertion message.
	 */
	public function test_accepts_only_domestic_payments( array $capabilities, bool $expected, string $message ) {
		$definition_class = $this->create_mock_definition( $capabilities );
		$this->assertEquals( $expected, PaymentMethodUtils::accepts_only_domestic_payments( $definition_class ), $message );
	}

	/**
	 * Data provider for test_allows_manual_capture.
	 *
	 * @return array
	 */
	public function allows_manual_capture_provider(): array {
		return [
			'with manual capture capability'    => [
				[ PaymentMethodCapability::CAPTURE_LATER ],
				true,
				'Should identify manual capture capability',
			],
			'without manual capture capability' => [
				[ PaymentMethodCapability::TOKENIZATION ],
				false,
				'Should not identify non-manual-capture capability as supporting manual capture',
			],
			'with empty capabilities'           => [
				[],
				false,
				'Empty capabilities should not be identified as supporting manual capture',
			],
		];
	}

	/**
	 * Test that allows_manual_capture() identifies manual capture support.
	 *
	 * @dataProvider allows_manual_capture_provider
	 *
	 * @param array  $capabilities Array of capabilities.
	 * @param bool   $expected Expected result.
	 * @param string $message Assertion message.
	 */
	public function test_allows_manual_capture( array $capabilities, bool $expected, string $message ) {
		$definition_class = $this->create_mock_definition( $capabilities );
		$this->assertEquals( $expected, PaymentMethodUtils::allows_manual_capture( $definition_class ), $message );
	}

	/**
	 * Test that is_domestic_currency_for_country() works with valid combinations.
	 */
	public function test_is_domestic_currency_for_country_with_valid_combinations() {
		// Test some known valid combinations.
		$this->assertTrue(
			PaymentMethodUtils::is_domestic_currency_for_country( 'USD', 'US' ),
			'USD should be domestic for US'
		);

		$this->assertTrue(
			PaymentMethodUtils::is_domestic_currency_for_country( 'EUR', 'DE' ),
			'EUR should be domestic for DE'
		);

		$this->assertTrue(
			PaymentMethodUtils::is_domestic_currency_for_country( 'GBP', 'GB' ),
			'GBP should be domestic for GB'
		);
	}

	/**
	 * Test that is_domestic_currency_for_country() works with invalid combinations.
	 */
	public function test_is_domestic_currency_for_country_with_invalid_combinations() {
		$this->assertFalse(
			PaymentMethodUtils::is_domestic_currency_for_country( 'EUR', 'US' ),
			'EUR should not be domestic for US'
		);

		$this->assertFalse(
			PaymentMethodUtils::is_domestic_currency_for_country( 'USD', 'GB' ),
			'USD should not be domestic for GB'
		);

		// Test with invalid country code.
		$this->assertFalse(
			PaymentMethodUtils::is_domestic_currency_for_country( 'USD', 'XX' ),
			'Should return false for invalid country code'
		);
	}

	/**
	 * Test that get_payment_method_definitions_json() returns valid JSON.
	 */
	public function test_get_payment_method_definitions_json_returns_valid_json() {
		$registry = PaymentMethodDefinitionRegistry::instance();
		$registry->register_payment_method( MockPaymentMethodDefinition::class );
		$registry->register_payment_method( SecondMockPaymentMethodDefinition::class );

		$json = PaymentMethodUtils::get_payment_method_definitions_json();

		// Verify it's valid JSON.
		$decoded = json_decode( $json, true );
		$this->assertNotNull( $decoded, 'Should return valid JSON' );
		$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );

		// Verify required fields are present.
		foreach ( $decoded as $method ) {
			$this->assertArrayHasKey( 'id', $method );
			$this->assertArrayHasKey( 'title', $method );
			$this->assertArrayHasKey( 'description', $method );
			$this->assertArrayHasKey( 'icon', $method );
			$this->assertArrayHasKey( 'currencies', $method );
			$this->assertArrayHasKey( 'allows_manual_capture', $method );
			$this->assertArrayHasKey( 'allows_pay_later', $method );
			$this->assertArrayHasKey( 'accepts_only_domestic_payment', $method );
		}
	}

	/**
	 * Test that get_payment_method_definitions_json() returns empty string for empty registry.
	 */
	public function test_get_payment_method_definitions_json_empty_registry() {
		$json = PaymentMethodUtils::get_payment_method_definitions_json();

		$this->assertJson( $json );
		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
		$this->assertEmpty( $decoded );
	}
}
