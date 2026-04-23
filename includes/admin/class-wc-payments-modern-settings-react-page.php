<?php
/**
 * Class WC_Payments_Modern_Settings_React_Page
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\Admin\Settings\ReactSettingsPageInterface;

/**
 * ReactSettingsPageInterface contribution for the WooPayments gateway settings.
 *
 * Returned from WC_Payments_Modern_Settings_Bridge::get_react_settings_page() so
 * ReactSettingsSchema can resolve our custom field types when computing the
 * render plan for tab=checkout, section=woocommerce_payments.
 *
 * Maps WCPay's `account_statement_descriptor` raw type onto the SDK's `text`
 * primitive — the underlying field is a constrained text input, so no JS Edit
 * component is required for this PoC. Other raw types in the gateway form
 * (`checkbox`, `select`, `multiselect`, `text`, `title`) are already in the
 * SDK's native supported set.
 */
class WC_Payments_Modern_Settings_React_Page implements ReactSettingsPageInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param string $section Section id. Empty string means the default section.
	 * @return array<string, string>
	 */
	public function get_extra_type_map( string $section ): array {
		return [
			'account_statement_descriptor' => 'text',
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $section Section id. Empty string means the default section.
	 * @return array<int, string>
	 */
	public function get_extra_supported_types( string $section ): array {
		return [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $field_id Field id.
	 * @param array  $field    Raw settings definition.
	 * @param string $section  Section id. Empty string means the default section.
	 * @return array<int, array{label: string, value: string}>|null
	 */
	public function get_field_options( string $field_id, array $field, string $section ): ?array {
		return null;
	}
}
