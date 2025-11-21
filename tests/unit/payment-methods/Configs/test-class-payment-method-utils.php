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

/**
 * PaymentMethodUtils unit tests.
 */
class PaymentMethodUtilsTest extends WCPAY_UnitTestCase {

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
	 * Test that is_bnpl() correctly identifies BNPL methods.
	 */
	public function test_is_bnpl() {
		// Create anonymous class with BNPL capability.
		$bnpl_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::BUY_NOW_PAY_LATER ];
			}
		};
		$this->assertTrue(
			PaymentMethodUtils::is_bnpl( get_class( $bnpl_definition ) ),
			'Should identify BNPL capability'
		);

		// Create anonymous class without BNPL capability.
		$non_bnpl_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::TOKENIZATION ];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::is_bnpl( get_class( $non_bnpl_definition ) ),
			'Should not identify non-BNPL capability as BNPL'
		);

		// Create anonymous class with empty capabilities.
		$empty_definition = new class() {
			public static function get_capabilities(): array {
				return [];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::is_bnpl( get_class( $empty_definition ) ),
			'Empty capabilities should not be identified as BNPL'
		);
	}

	/**
	 * Test that is_reusable() identifies tokenizable methods.
	 */
	public function test_is_reusable() {
		// Create anonymous class with tokenization capability.
		$reusable_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::TOKENIZATION ];
			}
		};
		$this->assertTrue(
			PaymentMethodUtils::is_reusable( get_class( $reusable_definition ) ),
			'Should identify tokenizable capability'
		);

		// Create anonymous class without tokenization capability.
		$non_reusable_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::BUY_NOW_PAY_LATER ];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::is_reusable( get_class( $non_reusable_definition ) ),
			'Should not identify non-tokenizable capability as reusable'
		);

		// Create anonymous class with empty capabilities.
		$empty_definition = new class() {
			public static function get_capabilities(): array {
				return [];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::is_reusable( get_class( $empty_definition ) ),
			'Empty capabilities should not be identified as reusable'
		);
	}

	/**
	 * Test that accepts_only_domestic_payments() identifies domestic-only methods.
	 */
	public function test_accepts_only_domestic_payments() {
		// Create anonymous class with domestic-only capability.
		$domestic_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::DOMESTIC_TRANSACTIONS_ONLY ];
			}
		};
		$this->assertTrue(
			PaymentMethodUtils::accepts_only_domestic_payments( get_class( $domestic_definition ) ),
			'Should identify domestic-only capability'
		);

		// Create anonymous class without domestic-only capability.
		$non_domestic_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::TOKENIZATION ];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::accepts_only_domestic_payments( get_class( $non_domestic_definition ) ),
			'Should not identify non-domestic-only capability as domestic-only'
		);

		// Create anonymous class with empty capabilities.
		$empty_definition = new class() {
			public static function get_capabilities(): array {
				return [];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::accepts_only_domestic_payments( get_class( $empty_definition ) ),
			'Empty capabilities should not be identified as domestic-only'
		);
	}

	/**
	 * Test that allows_manual_capture() identifies manual capture support.
	 */
	public function test_allows_manual_capture() {
		// Create anonymous class with manual capture capability.
		$manual_capture_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::CAPTURE_LATER ];
			}
		};
		$this->assertTrue(
			PaymentMethodUtils::allows_manual_capture( get_class( $manual_capture_definition ) ),
			'Should identify manual capture capability'
		);

		// Create anonymous class without manual capture capability.
		$non_manual_capture_definition = new class() {
			public static function get_capabilities(): array {
				return [ PaymentMethodCapability::TOKENIZATION ];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::allows_manual_capture( get_class( $non_manual_capture_definition ) ),
			'Should not identify non-manual-capture capability as supporting manual capture'
		);

		// Create anonymous class with empty capabilities.
		$empty_definition = new class() {
			public static function get_capabilities(): array {
				return [];
			}
		};
		$this->assertFalse(
			PaymentMethodUtils::allows_manual_capture( get_class( $empty_definition ) ),
			'Empty capabilities should not be identified as supporting manual capture'
		);
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
			$this->assertArrayHasKey( 'settings_icon_url', $method );
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
		$registry = PaymentMethodDefinitionRegistry::instance();
		$json     = PaymentMethodUtils::get_payment_method_definitions_json();

		$this->assertJson( $json );
		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
		$this->assertEmpty( $decoded );
	}
}
