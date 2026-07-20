<?php
/**
 * Stub for the WooCommerce 10.9 Settings UI SDK page contract.
 *
 * `SettingsUIPageInterface` is implemented by the WOOPMNT-6211 settings UI
 * spike classes in `includes/admin/settings-ui-spike/`. It ships in
 * WooCommerce 10.9; environments still on an older WC version need this stub
 * so those files can be analyzed without the real interface present.
 *
 * Consumers:
 *  - PHPStan (loaded via `phpstan.neon` → `scanFiles`).
 *
 * Production runtime: `WC_Payments_Settings_UI_Spike::init()` short-circuits
 * with an `interface_exists()` check on WC < 10.9, so this stub is not
 * required outside static analysis.
 *
 * @package WooCommerce\Payments
 */

namespace Automattic\WooCommerce\Admin\Settings {
	if ( ! interface_exists( __NAMESPACE__ . '\\SettingsUIPageInterface' ) ) {
		interface SettingsUIPageInterface {
			public function get_page_id(): string;
			public function get_schema( string $section ): array;
			public function get_script_handles( string $section ): array;
			public function get_save_adapter( string $section ): string;
		}
	}
}
