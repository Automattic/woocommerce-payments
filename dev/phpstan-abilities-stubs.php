<?php
/**
 * Stub for the WooCommerce 10.9 Abilities API interface.
 *
 * `AbilityDefinition` is the interface every per-ability Domain class in
 * `src/Internal/Abilities/Domain/` implements. It ships in WooCommerce
 * 10.9; environments still on an older WC version need this stub so the
 * Domain class files can parse without the real interface present.
 *
 * Consumers:
 *  - PHPStan (loaded via `phpstan.neon` → `bootstrapFiles`).
 *  - PHPUnit (loaded from `tests/unit/bootstrap.php` after WC loads).
 *
 * Production runtime: `AbilitiesRegistrar::init()` short-circuits before
 * touching any Domain class on WC < 10.9, so this stub is not required
 * outside static analysis and the test suite.
 *
 * @package WooCommerce\Payments
 */

namespace Automattic\WooCommerce\Abilities {
	if ( ! interface_exists( __NAMESPACE__ . '\\AbilityDefinition' ) ) {
		interface AbilityDefinition {
			public static function get_name(): string;
			public static function get_registration_args(): array;
		}
	}
}

namespace Automattic\WooCommerce\Internal\Abilities {
	if ( ! class_exists( __NAMESPACE__ . '\\AbilitiesLoader' ) ) {
		/**
		 * Marker stub for the WC 10.9 loader. AbilitiesRegistrar only checks
		 * presence via `class_exists()` — no methods or properties needed.
		 */
		class AbilitiesLoader {} // phpcs:ignore Squiz.Classes.ClassDeclaration.MissingBrace
	}
}
