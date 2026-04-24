<?php
/**
 * WooPayments modern settings page adapter.
 *
 * @package WCPay\Internal\Admin\Settings
 */

declare( strict_types=1 );

namespace WCPay\Internal\Admin\Settings;

use Automattic\WooCommerce\Internal\Admin\Settings\ReactSettingsPageInterface;
use Automattic\WooCommerce\Internal\Admin\Settings\ReactSettingsSchema;
use WC_Payment_Gateway_WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * PoC adapter that lets the WooPayments gateway settings render through WooCommerce's modern settings SDK.
 *
 * WooPayments currently owns a payment-gateway section (`checkout/woocommerce_payments`) rather than a
 * `WC_Settings_Page` subclass. This small adapter supplies the same contract shape that
 * `ReactSettingsSchema` expects while keeping the implementation scoped to WooPayments.
 */
final class WooPaymentsModernSettingsPage implements ReactSettingsPageInterface {

	private const TAB_ID = 'checkout';

	private const SECTION_ID = WC_Payment_Gateway_WCPay::GATEWAY_ID;

	/**
	 * WooPayments gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private WC_Payment_Gateway_WCPay $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway WooPayments gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Label used by ReactSettingsSchema.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'WooPayments';
	}

	/**
	 * Expose the React settings page contract to ReactSettingsSchema.
	 *
	 * @return ReactSettingsPageInterface|null
	 */
	public function get_react_settings_page(): ?ReactSettingsPageInterface {
		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $section Section id.
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
	 * @param string $section Section id.
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
	 * @param string $section  Section id.
	 * @return array<int, array{label: string, value: string}>|null
	 */
	public function get_field_options( string $field_id, array $field, string $section ): ?array {
		if (
			'upe_enabled_payment_method_ids' !== $field_id
			&& $this->gateway->get_field_key( 'upe_enabled_payment_method_ids' ) !== $field_id
		) {
			return null;
		}

		$options = [];
		foreach ( $this->gateway->get_payment_methods() as $payment_method_id => $payment_method ) {
			$options[] = [
				'label' => method_exists( $payment_method, 'get_title' )
					? (string) $payment_method->get_title( $this->gateway->get_account_country() )
					: (string) $payment_method_id,
				'value' => (string) $payment_method_id,
			];
		}

		return $options;
	}

	/**
	 * Output the modern settings mount when WooCommerce's modern-settings feature is enabled.
	 *
	 * @return bool Whether the modern mount was output.
	 */
	public function output(): bool {
		if ( ! class_exists( ReactSettingsSchema::class ) || ! ReactSettingsSchema::is_feature_enabled() ) {
			return false;
		}

		$settings_definitions = $this->get_settings_definitions();
		$render_plan          = ReactSettingsSchema::get_screen_render_context(
			self::TAB_ID,
			self::SECTION_ID,
			$settings_definitions,
			$this
		);

		if ( ! $render_plan['should_render'] || empty( $render_plan['response'] ) ) {
			return false;
		}

		$GLOBALS['hide_save_button'] = true;
		wp_dequeue_script( 'woocommerce_settings' );

		$response = $this->include_ungrouped_fields_in_response( $render_plan['response'], $settings_definitions );

		$this->enqueue_modern_settings_assets();
		$this->enqueue_preloaded_settings_data( $render_plan['payload_path'], $response );
		$this->output_checkbox_save_compatibility_script( $render_plan['mount_id'] );

		echo '<div id="' . esc_attr( $render_plan['mount_id'] ) . '" data-wc-modern-settings="1" data-wc-settings-tab="' . esc_attr( self::TAB_ID ) . '" data-wc-settings-section="' . esc_attr( self::SECTION_ID ) . '"> </div>';

		return true;
	}

	/**
	 * Get modern-ready definitions from the legacy WooPayments gateway fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings_definitions(): array {
		$definitions = [];

		foreach ( $this->gateway->get_form_fields() as $field_id => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$definitions[] = $this->normalize_gateway_field( (string) $field_id, $field );
		}

		return $definitions;
	}

	/**
	 * Get the gateway POST field names for checkbox settings.
	 *
	 * @return array<int, string>
	 */
	public function get_checkbox_field_names(): array {
		$field_names = [];

		foreach ( $this->gateway->get_form_fields() as $field_id => $field ) {
			if ( is_array( $field ) && 'checkbox' === ( $field['type'] ?? '' ) ) {
				$field_names[] = $this->gateway->get_field_key( (string) $field_id );
			}
		}

		return $field_names;
	}

	/**
	 * Normalize a WC_Settings_API gateway field for ReactSettingsSchema.
	 *
	 * @param string $field_id Field id.
	 * @param array  $field    Gateway field.
	 * @return array<string, mixed>
	 */
	private function normalize_gateway_field( string $field_id, array $field ): array {
		$field['id']         = $this->gateway->get_field_key( $field_id );
		$field['field_name'] = $field['id'];
		$field['name']       = $field['field_name'];
		$field['value']      = $this->gateway->get_option( $field_id );

		if ( empty( $field['type'] ) ) {
			$field['type'] = 'text';
		}

		if ( 'checkbox' === $field['type'] && ! empty( $field['label'] ) ) {
			$field['title'] = $field['label'];
		}

		if ( ! isset( $field['title'] ) ) {
			$field['title'] = $this->get_fallback_field_title( $field_id );
		}

		if ( ! isset( $field['desc'] ) && isset( $field['description'] ) ) {
			$field['desc'] = $field['description'];
		}

		return $field;
	}

	/**
	 * Get a readable title for legacy settings that were not originally rendered as form rows.
	 *
	 * @param string $field_id Field id.
	 * @return string
	 */
	private function get_fallback_field_title( string $field_id ): string {
		if ( 'platform_checkout_custom_message' === $field_id ) {
			return __( 'WooPay custom message', 'woocommerce-payments' );
		}

		return ucwords( str_replace( '_', ' ', $field_id ) );
	}

	/**
	 * Enqueue the settings payload for WooCommerce's settings embed registry.
	 *
	 * @param array<int, string>   $payload_path Payload path.
	 * @param array<string, mixed> $response     React settings response.
	 * @return void
	 */
	private function enqueue_preloaded_settings_data( array $payload_path, array $response ): void {
		$script = sprintf(
			'( function() {
				window.wcSettings = window.wcSettings || {};
				window.wcSettings.admin = window.wcSettings.admin || {};
				var target = window.wcSettings.admin;
				var path = %1$s;
				for ( var i = 0; i < path.length - 1; i++ ) {
					target[ path[ i ] ] = target[ path[ i ] ] || {};
					target = target[ path[ i ] ];
				}
				target[ path[ path.length - 1 ] ] = %2$s;
			} )();',
			wp_json_encode( $payload_path ),
			wp_json_encode( $response )
		);

		wp_add_inline_script(
			'wc-admin-settings-embed',
			$script,
			'before'
		);
	}

	/**
	 * Enqueue WCPay settings assets before WooCommerce mounts the modern settings app.
	 *
	 * @return void
	 */
	private function enqueue_modern_settings_assets(): void {
		if ( ! wp_script_is( 'WCPAY_ADMIN_SETTINGS', 'registered' ) ) {
			return;
		}

		wp_enqueue_script( 'WCPAY_ADMIN_SETTINGS' );
		wp_enqueue_style( 'WCPAY_ADMIN_SETTINGS' );

		$wp_scripts = wp_scripts();
		if (
			isset( $wp_scripts->registered['wc-admin-settings-embed'] )
			&& ! in_array( 'WCPAY_ADMIN_SETTINGS', $wp_scripts->registered['wc-admin-settings-embed']->deps, true )
		) {
			$wp_scripts->registered['wc-admin-settings-embed']->deps[] = 'WCPAY_ADMIN_SETTINGS';
		}
	}

	/**
	 * Include fields that WooCommerce's current response builder drops before the first title marker.
	 *
	 * @param array<string, mixed>            $response    React settings response.
	 * @param array<int, array<string,mixed>> $definitions Settings definitions.
	 * @return array<string, mixed>
	 */
	private function include_ungrouped_fields_in_response( array $response, array $definitions ): array {
		$fields = [];
		$values = [];

		foreach ( $definitions as $definition ) {
			$type = isset( $definition['type'] ) && is_string( $definition['type'] ) ? $definition['type'] : 'text';

			if ( 'title' === $type ) {
				break;
			}

			if ( 'sectionend' === $type || empty( $definition['id'] ) ) {
				continue;
			}

			$field = $this->transform_definition_to_field( $definition );
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				continue;
			}

			$fields[]               = $field;
			$values[ $field['id'] ] = $this->normalize_field_value( $definition['value'] ?? ( $definition['default'] ?? '' ), $field['type'] );
		}

		if ( empty( $fields ) ) {
			return $response;
		}

		$groups = isset( $response['groups'] ) && is_array( $response['groups'] ) ? $response['groups'] : [];

		$response['groups'] = array_merge(
			[
				'woocommerce_payments_general' => [
					'title'       => __( 'General', 'woocommerce-payments' ),
					'description' => '',
					'order'       => -1,
					'fields'      => $fields,
				],
			],
			$groups
		);
		$response['values'] = array_merge(
			$values,
			isset( $response['values'] ) && is_array( $response['values'] ) ? $response['values'] : []
		);

		return $response;
	}

	/**
	 * Transform a normalized gateway definition to a React settings field.
	 *
	 * @param array<string, mixed> $definition Gateway definition.
	 * @return array<string, mixed>
	 */
	private function transform_definition_to_field( array $definition ): array {
		$type      = isset( $definition['type'] ) && is_string( $definition['type'] ) ? $definition['type'] : 'text';
		$type_map  = $this->get_extra_type_map( self::SECTION_ID );
		$type      = isset( $type_map[ $type ] ) ? $type_map[ $type ] : $type;
		$field     = [
			'id'    => $definition['id'],
			'label' => $definition['title'] ?? $definition['name'] ?? $definition['id'],
			'type'  => $type,
			'desc'  => $definition['desc'] ?? '',
		];
		$pass_keys = [
			'class',
			'css',
			'custom_attributes',
			'disabled',
			'field_name',
			'name',
			'placeholder',
			'row_class',
			'suffix',
		];

		foreach ( $pass_keys as $key ) {
			if ( array_key_exists( $key, $definition ) ) {
				$field[ $key ] = $definition[ $key ];
			}
		}

		if ( isset( $definition['options'] ) && is_array( $definition['options'] ) ) {
			$field['options'] = $definition['options'];
		}

		return $field;
	}

	/**
	 * Normalize field values to the shape expected by the modern settings SDK.
	 *
	 * @param mixed  $value Field value.
	 * @param string $type  Field type.
	 * @return mixed
	 */
	private function normalize_field_value( $value, string $type ) {
		if ( 'checkbox' === $type || 'toggle' === $type ) {
			return wc_string_to_bool( $value );
		}

		if ( 'multiselect' === $type ) {
			return is_array( $value ) ? array_values( $value ) : [];
		}

		if ( 'number' === $type ) {
			if ( '' === $value || null === $value ) {
				return '';
			}

			return is_numeric( $value ) ? (float) $value : 0;
		}

		return is_string( $value ) ? $value : (string) $value;
	}

	/**
	 * Preserve WC_Settings_API checkbox save semantics.
	 *
	 * WC_Settings_API treats an absent checkbox POST field as "no" and any present value as "yes".
	 * The modern SDK submits hidden `no` values, so this PoC removes those inputs just before submit.
	 *
	 * @param string $mount_id React mount id.
	 * @return void
	 */
	private function output_checkbox_save_compatibility_script( string $mount_id ): void {
		$script = sprintf(
			'( function() {
				var mountId = %1$s;
				var checkboxNames = %2$s;
				document.addEventListener( "submit", function( event ) {
					var form = event.target;
					if ( ! form || ! form.querySelector || ! form.querySelector( "#" + mountId ) ) {
						return;
					}

					checkboxNames.forEach( function( name ) {
						Array.prototype.slice.call( form.getElementsByTagName( "input" ) ).forEach( function( input ) {
							if ( "hidden" === input.type && name === input.name && "no" === input.value && input.parentNode ) {
								input.parentNode.removeChild( input );
							}
						} );
					} );
				} );
			} )();',
			wp_json_encode( $mount_id ),
			wp_json_encode( $this->get_checkbox_field_names() )
		);

		wp_print_inline_script_tag(
			$script,
			[
				'id' => 'wcpay-modern-settings-checkbox-compat',
			]
		);
	}
}
