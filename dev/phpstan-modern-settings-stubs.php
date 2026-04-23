<?php
/**
 * PHPStan stubs for the WooCommerce 10.8 modernised settings SDK.
 *
 * The SDK ships in WC 10.8+ but WCPay's PHPStan stub set tracks an earlier
 * version, so the real classes are not visible to static analysis. These
 * stubs let us reference the SDK from forward-compat code in WCPay without
 * tripping `class.notFound` / `interface.notFound` errors.
 *
 * Update or remove when WCPay's WC stubs are bumped to 10.8.
 *
 * @package WooCommerce\Payments
 */

namespace Automattic\WooCommerce\Internal\Admin\Settings;

interface ReactSettingsPageInterface {
	public function get_extra_type_map( string $section ): array;

	public function get_extra_supported_types( string $section ): array;

	public function get_field_options( string $field_id, array $field, string $section ): ?array;
}

class ReactSettingsSchema {
	public static function is_feature_enabled(): bool {
		return false;
	}

	public static function get_screen_render_context( string $tab, string $section, array $settings_definitions, $settings_page ): array {
		return [];
	}
}
