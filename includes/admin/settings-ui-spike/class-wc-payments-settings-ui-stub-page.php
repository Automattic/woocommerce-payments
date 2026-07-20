<?php
/**
 * Class WC_Payments_Settings_UI_Stub_Page
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Settings\SettingsUIPageInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WOOPMNT-6211 spike: minimal `WC_Settings_Page` whose only job is to expose
 * the WooPayments settings UI adapter to Core's tab-level lookups.
 *
 * Core resolves a settings UI adapter by iterating
 * `WC_Admin_Settings::get_settings_pages()` and returning
 * `get_settings_ui_page()` from the first page whose id matches the current
 * tab. The real `checkout` tab page (`WC_Settings_Payment_Gateways`) belongs to
 * Core and offers no extension point for gateway sections, so this stub is
 * prepended with the same id.
 *
 * The constructor deliberately does NOT call `parent::__construct()`: this
 * object must not register a settings tab, output/save hooks, or body-class
 * filters — the real page instance keeps doing all of that.
 */
class WC_Payments_Settings_UI_Stub_Page extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		$this->id    = 'checkout';
		$this->label = '';
	}

	/**
	 * Provide the WooPayments settings UI adapter for the gateway section.
	 *
	 * @return SettingsUIPageInterface|null
	 */
	public function get_settings_ui_page(): ?SettingsUIPageInterface {
		if ( ! WC_Payments_Settings_UI_Spike::is_sdk_rendering() ) {
			return null;
		}

		require_once __DIR__ . '/class-wc-payments-settings-ui-adapter.php';

		return new WC_Payments_Settings_UI_Adapter();
	}
}
