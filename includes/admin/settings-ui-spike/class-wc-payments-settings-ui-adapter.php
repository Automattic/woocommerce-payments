<?php
/**
 * Class WC_Payments_Settings_UI_Adapter
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Settings\SettingsUIPageInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WOOPMNT-6211 spike: hand-built canonical settings schema for the WooPayments
 * settings, rendered by the WooCommerce Settings UI SDK and matching the
 * "Modernised WooPayments Settings i1" Figma designs (file G9FaeEbkIqLcx9tzoFLKhb,
 * node 6058:881238 — see .claude/tmp/figma-specs/woopayments-settings-general.md).
 *
 * Design tabs (General / Payment methods / Payouts / Store and checkout) map to
 * a `wcpay_tab` query param on the gateway section URL, rendered by the SDK's
 * native `shell.sectionNavigation` tab bar. Persistable field ids match
 * `/wc/v3/payments/settings` REST arguments so the JS save handler passes
 * changed values straight through.
 */
class WC_Payments_Settings_UI_Adapter implements SettingsUIPageInterface {

	/**
	 * Get the stable page id used for scoping the settings UI.
	 *
	 * @return string
	 */
	public function get_page_id(): string {
		return WC_Payments_Settings_UI_Spike::PAGE_ID;
	}

	/**
	 * Build the canonical settings schema for the WooPayments gateway section.
	 *
	 * @param string $section Section id ('woocommerce_payments' on this screen).
	 * @return array
	 */
	public function get_schema( string $section ): array {
		$active_tab = $this->get_active_tab();

		return [
			'id'      => $this->get_page_id(),
			'title'   => __( 'WooPayments settings', 'woocommerce-payments' ),
			'section' => '' === $section ? 'default' : $section,
			'save'    => [
				'adapter' => 'custom',
				'handler' => WC_Payments_Settings_UI_Spike::SAVE_HANDLER,
			],
			'shell'   => [
				'title'             => __( 'WooPayments settings', 'woocommerce-payments' ),
				'breadcrumbs'       => [
					[
						'label' => __( 'Payments', 'woocommerce-payments' ),
						'href'  => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
					],
				],
				// Native SDK tab bar. Design gap: the i1 header also has a
				// subtitle and an "Active" badge — no shell slots exist for
				// those (upstream SDK ask, see spike findings).
				'sectionNavigation' => $this->get_tabs( $active_tab ),
			],
			'groups'  => 'general' === $active_tab ? $this->get_general_groups() : $this->get_placeholder_groups( $active_tab ),
		];
	}

	/**
	 * Script handles that must load before the settings UI app mounts.
	 *
	 * @param string $section Section id.
	 * @return string[]
	 */
	public function get_script_handles( string $section ): array {
		return [ WC_Payments_Settings_UI_Spike::SCRIPT_HANDLE ];
	}

	/**
	 * Default save adapter for fields on this page.
	 *
	 * `none` — saving happens through the schema-level custom save handler
	 * (the WooPayments REST API), not the WC settings form POST.
	 *
	 * @param string $section Section id.
	 * @return string
	 */
	public function get_save_adapter( string $section ): string {
		return 'none';
	}

	/**
	 * Resolve the active design tab from the request.
	 *
	 * @return string
	 */
	private function get_active_tab(): string {
		$tab = isset( $_GET['wcpay_tab'] ) ? sanitize_key( wp_unslash( $_GET['wcpay_tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ids = [ 'general', 'payment-methods', 'payouts', 'store-and-checkout' ];

		return in_array( $tab, $ids, true ) ? $tab : 'general';
	}

	/**
	 * Tab definitions for the SDK's native sectionNavigation tab bar.
	 *
	 * @param string $active_tab Active tab id.
	 * @return array
	 */
	private function get_tabs( string $active_tab ): array {
		$base = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' );
		$tabs = [
			'general'            => __( 'General', 'woocommerce-payments' ),
			'payment-methods'    => __( 'Payment methods', 'woocommerce-payments' ),
			'payouts'            => __( 'Payouts', 'woocommerce-payments' ),
			'store-and-checkout' => __( 'Store and checkout', 'woocommerce-payments' ),
		];

		$navigation = [];
		foreach ( $tabs as $id => $label ) {
			$navigation[] = [
				'id'     => $id,
				'label'  => $label,
				'href'   => 'general' === $id ? $base : add_query_arg( 'wcpay_tab', $id, $base ),
				'active' => $active_tab === $id,
			];
		}

		return $navigation;
	}

	/**
	 * Groups for the General tab, per the i1 Figma designs.
	 *
	 * @return array
	 */
	private function get_general_groups(): array {
		$gateway = WC_Payments::get_gateway();

		$is_test_mode = false;
		$is_dev_mode  = false;
		try {
			$is_test_mode = WC_Payments::mode()->is_test();
			$is_dev_mode  = WC_Payments::mode()->is_dev();
		} catch ( Exception $e ) {
			// Default to false when the mode is not initialized.
			unset( $e );
		}

		// In dev mode the REST controller refuses to change test mode and debug
		// logging (they are forced on) — render those toggles disabled.
		$dev_mode_note = $is_dev_mode ? ' ' . __( 'This setting is locked because the site runs in dev mode.', 'woocommerce-payments' ) : '';

		$protection_level = $gateway ? (string) $gateway->get_option( 'current_protection_level', 'basic' ) : 'basic';
		// The i1 design collapses protection levels to basic/advanced. Map
		// legacy intermediate levels (standard/high) onto basic for display.
		if ( ! in_array( $protection_level, [ 'basic', 'advanced' ], true ) ) {
			$protection_level = 'basic';
		}

		return [
			'wcpay_test_mode'             => [
				'id'          => 'wcpay_test_mode',
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'description' => __( 'Try out your payment setup before accepting real payments. You can place test orders and issue refunds without using real payment details.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 0,
				'fields'      => [
					[
						'id'               => 'is_test_mode_enabled',
						'label'            => __( 'Activate test mode', 'woocommerce-payments' ),
						'type'             => 'checkbox',
						'component'        => 'wcpay/toggle',
						'description'      => __( 'When test mode is on, orders are simulated and customers won’t be charged. Turn it off before accepting real payments.', 'woocommerce-payments' ) . $dev_mode_note,
						'value'            => $is_test_mode,
						'disabled'         => $is_dev_mode,
						'customAttributes' => [
							'learnMoreUrl' => 'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/',
						],
					],
				],
			],
			'wcpay_fraud_protection'      => [
				'id'          => 'wcpay_fraud_protection',
				'title'       => __( 'Fraud protection', 'woocommerce-payments' ),
				'description' => __( 'Select a fraud protection level that fits your store — from standard coverage to advanced, customizable filters.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 1,
				'fields'      => [
					[
						'id'               => 'current_protection_level',
						'label'            => __( 'Protection level', 'woocommerce-payments' ),
						'type'             => 'radio',
						'component'        => 'wcpay/protection-level',
						'description'      => '',
						'value'            => $protection_level,
						'options'          => [
							[
								'label' => __( 'Basic', 'woocommerce-payments' ),
								'value' => 'basic',
							],
							[
								'label' => __( 'Advanced', 'woocommerce-payments' ),
								'value' => 'advanced',
							],
						],
						'customAttributes' => [
							'helpBasic'    => __( 'Provides the base level of platform protection.', 'woocommerce-payments' ),
							'helpAdvanced' => __( 'Advanced filtering and customizable controls.', 'woocommerce-payments' ),
						],
					],
				],
			],
			'wcpay_tax_id'                => [
				'id'          => 'wcpay_tax_id',
				'title'       => __( 'Tax ID', 'woocommerce-payments' ),
				'description' => __( 'Your Tax ID and business details for tax invoices.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 2,
				'fields'      => [
					[
						// Not backed by the settings REST API yet (see WOOPMNT-5395);
						// excluded from the save handler allowlist.
						'id'          => 'wcpay_spike_vat_registered',
						'label'       => __( 'I’m registered for a VAT number', 'woocommerce-payments' ),
						'type'        => 'checkbox',
						'component'   => 'wcpay/toggle',
						'description' => __( 'If your sales exceed the VAT threshold for your country, you’re required to register for a VAT number.', 'woocommerce-payments' ),
						'value'       => false,
						'save'        => [ 'adapter' => 'none' ],
					],
				],
			],
			'wcpay_account_notifications' => [
				'id'          => 'wcpay_account_notifications',
				'title'       => __( 'Account notifications', 'woocommerce-payments' ),
				'description' => __( 'Set the email address where important updates about your payments and payouts are sent.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 3,
				'fields'      => [
					[
						// Native email renderer. Design gap: the i1 design adds
						// an envelope icon prefix — native text/email fields
						// have no prefix slot (upstream SDK ask).
						'id'          => 'account_communications_email',
						'label'       => __( 'Email address', 'woocommerce-payments' ),
						'type'        => 'email',
						'description' => '',
						'value'       => $gateway ? (string) $gateway->get_option( 'account_communications_email' ) : '',
					],
				],
			],
			'wcpay_debug_mode'            => [
				'id'          => 'wcpay_debug_mode',
				'title'       => __( 'Debug mode', 'woocommerce-payments' ),
				// Design copy is a placeholder ("Helper text.") — flagged for design.
				'description' => __( 'Capture additional payment process details for troubleshooting.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 4,
				'fields'      => [
					[
						'id'          => 'is_debug_log_enabled',
						'label'       => __( 'Activate debug mode', 'woocommerce-payments' ),
						'type'        => 'checkbox',
						'component'   => 'wcpay/toggle',
						'description' => __( 'When debug mode is on, payment errors logs will be saved to WooCommerce → Status → Logs.', 'woocommerce-payments' ) . $dev_mode_note,
						'value'       => $is_dev_mode || ( $gateway && 'yes' === $gateway->get_option( 'enable_logging' ) ),
						'disabled'    => $is_dev_mode,
					],
				],
			],
		];
	}

	/**
	 * Placeholder groups for design tabs that are out of the spike's scope.
	 *
	 * @param string $tab Active tab id.
	 * @return array
	 */
	private function get_placeholder_groups( string $tab ): array {
		return [
			'wcpay_placeholder' => [
				'id'          => 'wcpay_placeholder',
				'title'       => ucwords( str_replace( '-', ' ', $tab ) ),
				'description' => __( 'This tab is not part of the WOOPMNT-6211 spike.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 0,
				'fields'      => [
					[
						'id'          => 'wcpay_spike_placeholder_note',
						'label'       => '',
						'type'        => 'info',
						'description' => __( 'These settings remain available in the classic WooPayments settings screen (with the settings-ui feature flag disabled).', 'woocommerce-payments' ),
						'value'       => null,
						'save'        => [ 'adapter' => 'none' ],
					],
				],
			],
		];
	}
}
