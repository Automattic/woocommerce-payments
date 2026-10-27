<?php
/**
 * Stubs for the WooCommerce core payments extension suggestion incentives.
 *
 * These classes are internal to WooCommerce core, so the WooCommerce stubs
 * package doesn't include them. `WC_Payments_Incentives_Service` delegates
 * to them behind a `class_exists()` guard.
 *
 * Consumers:
 *  - PHPStan (discovered via `phpstan.neon` → `scanFiles`).
 *
 * @package WooCommerce\Payments
 */

namespace Automattic\WooCommerce\Internal\Admin\Suggestions\Incentives {
	if ( ! class_exists( __NAMESPACE__ . '\\Incentive' ) ) {
		abstract class Incentive {
			public function get_all( string $country_code, string $incentive_type = '' ): array {
				return [];
			}
		}
	}
}

namespace Automattic\WooCommerce\Internal\Admin\Suggestions {
	if ( ! class_exists( __NAMESPACE__ . '\\PaymentsExtensionSuggestionIncentives' ) ) {
		class PaymentsExtensionSuggestionIncentives {
			public function get_incentive_instance( string $suggestion_id ): ?Incentives\Incentive {
				return null;
			}
		}
	}
}
