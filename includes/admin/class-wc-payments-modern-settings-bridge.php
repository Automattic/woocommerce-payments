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
	 * Wire the WordPress hooks needed to publish the SDK payload and register
	 * custom field-type transformers on the JS side.
	 *
	 * The mount-markup emit is driven from the gateway's admin_options() and
	 * does not need a hook.
	 */
	public function init_hooks(): void {
		add_filter( 'woocommerce_admin_shared_settings', [ $this, 'maybe_publish_payload' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_field_transformers' ] );
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
	 * Enqueue the custom multiselect field-type transformer on the gateway
	 * settings screen.
	 *
	 * The script attaches to `wc-admin-settings-embed` — the WC-side bundle
	 * that boots the modern settings registry and calls
	 * `registerReactSettingsScreens()`. Because that bundle exposes
	 * `window.wcReactSettings.registerFieldTypeTransformer` at module load
	 * time and `createRoot().render()` is deferred, a script enqueued with
	 * `$in_footer=true` after the bundle registers in time to override the
	 * broken built-in `multiselect` → `array` handling before React's first
	 * commit.
	 */
	public function maybe_enqueue_field_transformers(): void {
		if ( ! ReactSettingsSchema::is_feature_enabled() || ! $this->is_on_gateway_settings_screen() ) {
			return;
		}

		$handle       = 'wcpay-modern-settings-field-transformers';
		$script_rel   = 'assets/js/admin/modern-settings-field-transformers.js';
		$script_abs   = WCPAY_ABSPATH . $script_rel;
		$style_handle = 'wcpay-modern-settings-field-transformers-style';
		$style_rel    = 'assets/css/admin/modern-settings-field-transformers.css';
		$style_abs    = WCPAY_ABSPATH . $style_rel;

		if ( file_exists( $script_abs ) ) {
			wp_enqueue_script(
				$handle,
				plugins_url( $script_rel, WCPAY_PLUGIN_FILE ),
				[ 'wc-admin-settings-embed', 'wp-element', 'wp-components' ],
				(string) filemtime( $script_abs ),
				true
			);
		}

		if ( file_exists( $style_abs ) ) {
			wp_enqueue_style(
				$style_handle,
				plugins_url( $style_rel, WCPAY_PLUGIN_FILE ),
				[ 'wp-components' ],
				(string) filemtime( $style_abs )
			);
		}
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
	 *   - When the form starts with a non-title entry (e.g. `enabled`), we
	 *     synthesise an opening section seeded with the gateway's method title
	 *     and description so the React form has a meaningful header rather
	 *     than an unlabeled card.
	 *
	 * @return array
	 */
	private function get_settings_definitions(): array {
		if ( null !== $this->settings_definitions ) {
			return $this->settings_definitions;
		}

		$definitions = [];
		$current_id  = null;

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
					'desc'  => $this->normalize_description( $field['description'] ?? '' ),
				];
				continue;
			}

			if ( null === $current_id ) {
				$current_id    = 'wcpay_modern_settings_group_default';
				$definitions[] = [
					'type'  => 'title',
					'id'    => $current_id,
					'title' => $this->gateway->get_method_title(),
					'desc'  => $this->normalize_description( $this->gateway->get_method_description() ),
				];
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
	 * Returns null for select / multiselect / radio fields with an empty
	 * `options` array — WCPay populates those dynamically from the
	 * payment-methods registry (e.g. `upe_enabled_payment_method_ids`), and
	 * the modern renderer has no way to render an option-less multi-value
	 * field. Wiring the runtime options via `ReactSettingsPageInterface::get_field_options()`
	 * is a follow-up beyond this PoC.
	 *
	 * `multiselect` fields with real options ARE emitted here — DataForm's
	 * default `type: 'array'` rendering is broken in the 10.8-dev build, but
	 * the gateway settings ship a custom JS transformer via
	 * `WC_Payments_Modern_Settings_Scripts` that overrides it using
	 * `window.wcReactSettings.registerFieldTypeTransformer()`.
	 *
	 * @param string $key   Field id.
	 * @param array  $field Raw form_fields entry.
	 * @return array|null
	 */
	private function build_field_definition( string $key, array $field ): ?array {
		$type    = $field['type'] ?? 'text';
		$title   = $this->resolve_field_title( $key, $field );
		$default = $field['default'] ?? '';
		$value   = $this->gateway->get_option( $key, $default );
		$desc    = $this->normalize_description( $field['description'] ?? '' );

		$has_options = isset( $field['options'] ) && is_array( $field['options'] ) && ! empty( $field['options'] );
		if ( in_array( $type, [ 'select', 'multiselect', 'radio' ], true ) && ! $has_options ) {
			return null;
		}

		$definition = [
			'id'      => $key,
			'type'    => $type,
			'title'   => $title,
			'desc'    => $desc,
			'default' => $default,
			'value'   => $value,
		];

		if ( $has_options ) {
			$definition['options'] = $field['options'];
		}

		return $definition;
	}

	/**
	 * Resolve a human-readable title for a form field.
	 *
	 * Some WCPay form_fields entries (notably `enabled` and
	 * `platform_checkout_custom_message`) ship without a `title`. Falling back
	 * to a humanized field id keeps the rendered form readable and avoids the
	 * "naked input" effect when DataForm gets an empty label.
	 *
	 * @param string $key   Field id.
	 * @param array  $field Raw form_fields entry.
	 * @return string
	 */
	private function resolve_field_title( string $key, array $field ): string {
		$title = $field['title'] ?? '';
		if ( '' !== $title ) {
			return $title;
		}

		$label = $field['label'] ?? '';
		if ( '' !== $label ) {
			return $label;
		}

		return ucfirst( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Normalize a form_fields description for the React `desc` channel.
	 *
	 * The legacy renderer runs descriptions through `wpautop` / `wp_kses_post`
	 * and emits HTML; the React DataForm renders `desc` as plain text. Stripping
	 * tags here keeps descriptions readable instead of leaking raw `<a>` markup
	 * into the UI. Inline links are lost in the conversion — a richer text
	 * channel for the modern renderer is pending upstream.
	 *
	 * @param string $description Raw description.
	 * @return string
	 */
	private function normalize_description( string $description ): string {
		if ( '' === $description ) {
			return '';
		}

		return trim( wp_strip_all_tags( $description ) );
	}
}
