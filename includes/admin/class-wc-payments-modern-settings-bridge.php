<?php
/**
 * Class WC_Payments_Modern_Settings_Bridge
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\Admin\Settings\ReactSettingsPageInterface;
use Automattic\WooCommerce\Internal\Admin\Settings\ReactSettingsSchema;

/**
 * Bridges the WooPayments gateway into WooCommerce's modernised settings SDK.
 *
 * Why this exists:
 *   The SDK auto-renders settings pages from `WC_Settings_Page::output()` and
 *   auto-publishes the JSON payload from `Settings::add_react_settings_data()`,
 *   both of which look up the page by `tab` via `WC_Admin_Settings::get_settings_pages()`.
 *   For `tab=checkout` that returns `WC_Settings_Payment_Gateways` — not the
 *   gateway. The gateway settings render via
 *   `WC_Settings_Payment_Gateways::output() → render_classic_gateway_settings_page() → $gateway->admin_options()`,
 *   which never reaches the SDK.
 *
 *   This class fills the gap by acting as a "page-shaped" wrapper around the
 *   gateway: it satisfies the loose-typed `$settings_page` argument
 *   `ReactSettingsSchema` expects (`get_react_settings_page()` + `get_label()`)
 *   and drives both the mount-markup emit (from the gateway's `admin_options()`)
 *   and the payload publish (via `woocommerce_admin_shared_settings`).
 *
 * Scope: a proof-of-concept render path for tab=checkout, section=woocommerce_payments,
 * gated by the `modern-settings` feature flag. The legacy save POST handler is
 * still authoritative — the modern path replaces only the render in WC 10.8.
 */
class WC_Payments_Modern_Settings_Bridge {

	const SETTINGS_TAB     = 'checkout';
	const SETTINGS_SECTION = WC_Payment_Gateway_WCPay::GATEWAY_ID;

	/**
	 * The gateway being bridged.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Memoized ReactSettingsPageInterface implementation.
	 *
	 * @var ReactSettingsPageInterface|null
	 */
	private $react_page;

	/**
	 * Memoized settings definitions translated from the gateway's form_fields.
	 *
	 * @var array|null
	 */
	private $settings_definitions;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway The gateway to bridge.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Wire the WordPress hooks needed to publish the SDK payload.
	 *
	 * The mount-markup emit is driven from the gateway's admin_options() and
	 * does not need a hook.
	 */
	public function init_hooks(): void {
		add_filter( 'woocommerce_admin_shared_settings', [ $this, 'maybe_publish_payload' ] );
	}

	/**
	 * The label rendered as the form's title in the React DataForm.
	 *
	 * Mirrors `WC_Settings_Page::get_label()` so `ReactSettingsSchema::build_response()`
	 * can pick it up via duck typing.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->gateway->get_method_title();
	}

	/**
	 * The interface the SDK consults for type-map / supported-types / option overrides.
	 *
	 * Mirrors `WC_Settings_Page::get_react_settings_page()` so
	 * `ReactSettingsSchema::resolve_react_settings_page_interface()` can pick it up.
	 *
	 * @return ReactSettingsPageInterface
	 */
	public function get_react_settings_page(): ReactSettingsPageInterface {
		if ( null === $this->react_page ) {
			require_once __DIR__ . '/class-wc-payments-modern-settings-react-page.php';
			$this->react_page = new WC_Payments_Modern_Settings_React_Page();
		}
		return $this->react_page;
	}

	/**
	 * Called from WC_Payment_Gateway_WCPay::admin_options() when the flag is on.
	 *
	 * If the SDK render plan says we should render, emits the mount markup that
	 * the React bootstrapper scans for and returns true (the gateway then
	 * suppresses its existing custom React render). Otherwise returns false to
	 * fall through to the existing render.
	 *
	 * @return bool Whether the modern mount was emitted.
	 */
	public function maybe_render_mount(): bool {
		$render_plan = $this->get_render_plan();
		if ( null === $render_plan || ! $render_plan['should_render'] ) {
			return false;
		}

		// The custom React app on this page also sets this; keep it set so
		// WooCommerce's submit row stays hidden.
		$GLOBALS['hide_save_button'] = true;

		printf(
			'<div id="%s" data-wc-modern-settings="1" data-wc-settings-tab="%s" data-wc-settings-section="%s"></div>',
			esc_attr( $render_plan['mount_id'] ),
			esc_attr( self::SETTINGS_TAB ),
			esc_attr( self::SETTINGS_SECTION )
		);
		return true;
	}

	/**
	 * Filter callback for `woocommerce_admin_shared_settings`.
	 *
	 * Publishes the React payload at `wcSettings.admin.settings.{tab}.{section}`
	 * — the same path the SDK's own `Settings::add_react_settings_data()` uses
	 * — so `useReactSettings()` finds it on first render.
	 *
	 * Only fires on the WooPayments gateway settings screen with the flag on.
	 *
	 * @param mixed $settings Existing shared settings.
	 * @return mixed
	 */
	public function maybe_publish_payload( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		$render_plan = $this->get_render_plan();
		if ( null === $render_plan || ! $render_plan['should_render'] || null === $render_plan['response'] ) {
			return $settings;
		}

		$current = &$settings;
		foreach ( $render_plan['payload_path'] as $segment ) {
			if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
				$current[ $segment ] = [];
			}
			$current = &$current[ $segment ];
		}
		$current = $render_plan['response'];

		return $settings;
	}

	/**
	 * Compute the render plan, or null if we shouldn't render the modern path.
	 *
	 * Gates: feature flag on, on the right tab/section, and the SDK's render
	 * plan returns should_render=true. Memoization is intentionally skipped —
	 * the render plan is queried at most twice per request (once from
	 * admin_options(), once from the shared-settings filter).
	 *
	 * @return array|null
	 */
	private function get_render_plan(): ?array {
		if ( ! ReactSettingsSchema::is_feature_enabled() ) {
			return null;
		}

		if ( ! $this->is_on_gateway_settings_screen() ) {
			return null;
		}

		return ReactSettingsSchema::get_screen_render_context(
			self::SETTINGS_TAB,
			self::SETTINGS_SECTION,
			$this->get_settings_definitions(),
			$this
		);
	}

	/**
	 * Whether the current request targets the WooPayments gateway settings screen.
	 *
	 * @return bool
	 */
	private function is_on_gateway_settings_screen(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'wc-settings' === $page
			&& self::SETTINGS_TAB === $tab
			&& self::SETTINGS_SECTION === $section;
	}

	/**
	 * Translate the gateway's `form_fields` array into the WooCommerce settings
	 * definition shape `ReactSettingsSchema::build_response()` expects.
	 *
	 * Differences:
	 *   - form_fields is keyed by field id; settings definitions carry an
	 *     explicit `id` per entry.
	 *   - form_fields stores the description under `description`; the SDK
	 *     reads `desc`.
	 *   - Non-title entries get an inline `value` populated from the gateway's
	 *     option store. The SDK's default `WC_Admin_Settings::get_option()` lookup
	 *     would miss it (the gateway stores values serialized under a single
	 *     option key, not per-field), so we short-circuit by setting `value`.
	 *   - `title` entries open a section group; we synthesise matching
	 *     `sectionend` markers so `build_response()` can assemble them.
	 *
	 * @return array
	 */
	private function get_settings_definitions(): array {
		if ( null !== $this->settings_definitions ) {
			return $this->settings_definitions;
		}

		$definitions   = [];
		$current_id    = null;
		$group_counter = 0;

		foreach ( $this->gateway->get_form_fields() as $key => $field ) {
			$type = $field['type'] ?? 'text';

			if ( 'title' === $type ) {
				if ( null !== $current_id ) {
					$definitions[] = [
						'type' => 'sectionend',
						'id'   => $current_id,
					];
				}
				$current_id    = 'wcpay_modern_settings_group_' . $key;
				$definitions[] = [
					'type'  => 'title',
					'id'    => $current_id,
					'title' => $field['title'] ?? '',
					'desc'  => $field['description'] ?? '',
				];
				continue;
			}

			if ( null === $current_id ) {
				$current_id    = 'wcpay_modern_settings_group_' . $group_counter;
				$definitions[] = [
					'type'  => 'title',
					'id'    => $current_id,
					'title' => $this->gateway->get_method_title(),
					'desc'  => '',
				];
				++$group_counter;
			}

			$definition = $this->build_field_definition( (string) $key, $field );
			if ( null !== $definition ) {
				$definitions[] = $definition;
			}
		}

		if ( null !== $current_id ) {
			$definitions[] = [
				'type' => 'sectionend',
				'id'   => $current_id,
			];
		}

		$this->settings_definitions = $definitions;
		return $this->settings_definitions;
	}

	/**
	 * Build a single SDK field definition from a gateway form_fields entry.
	 *
	 * Returns null when the field can't be rendered by the SDK in its current
	 * shape — currently: select / multiselect fields whose `options` array is
	 * empty. WCPay populates these dynamically from the payment-methods registry
	 * (e.g. `upe_enabled_payment_method_ids`), and DataForm has no fallback Edit
	 * for an option-less multi-value field. Wiring the runtime options into
	 * `ReactSettingsPageInterface::get_field_options()` is a follow-up beyond
	 * this PoC.
	 *
	 * @param string $key   Field id.
	 * @param array  $field Raw form_fields entry.
	 * @return array|null
	 */
	private function build_field_definition( string $key, array $field ): ?array {
		$type    = $field['type'] ?? 'text';
		$default = $field['default'] ?? '';
		$value   = $this->gateway->get_option( $key, $default );

		$has_options = isset( $field['options'] ) && is_array( $field['options'] ) && ! empty( $field['options'] );
		if ( in_array( $type, [ 'select', 'multiselect', 'radio' ], true ) && ! $has_options ) {
			return null;
		}

		// `multiselect` is in the SDK's supported set but its baseFieldTransformer
		// produces a `type: 'array'` DataForm field with no built-in Edit in the
		// 10.8-dev DataViews bundle currently shipping with WC. The example
		// plugin sidesteps it for the same reason. Skip in the PoC; revisit when
		// DataViews ships an `array` Edit (or wire a custom transformer).
		if ( 'multiselect' === $type ) {
			return null;
		}

		$definition = [
			'id'      => $key,
			'type'    => $type,
			'title'   => $field['title'] ?? '',
			'desc'    => $field['description'] ?? '',
			'default' => $default,
			'value'   => $value,
		];

		if ( $has_options ) {
			$definition['options'] = $field['options'];
		}

		return $definition;
	}
}
