<?php
/**
 * PHPStan stub for the WooCommerce 10.9 Abilities API surface.
 *
 * AbilityDefinition is the interface every per-ability Domain class in
 * src/Internal/Abilities/Domain/ implements. It ships in WooCommerce
 * 10.9; until the test environment ramps to that version, PHPStan can't
 * see the real interface. The stub declares the same public shape so
 * static analysis can resolve `implements AbilityDefinition` references
 * without needing the actual WC dependency at analysis time.
 *
 * Runtime: the AbilitiesRegistrar short-circuits before referencing any
 * Domain class on WC < 10.9, so the unresolved interface FQN is never
 * actually parsed at runtime there.
 *
 * @package WooCommerce\Payments
 */

namespace Automattic\WooCommerce\Abilities;

if ( ! interface_exists( __NAMESPACE__ . '\\AbilityDefinition' ) ) {
	interface AbilityDefinition {
		public static function get_name(): string;
		public static function get_registration_args(): array;
	}
}
