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
 * node 6001:76767 — full section with all tabs).
 *
 * Design tabs (General / Payment methods / Payouts / Store and checkout, plus
 * the hidden Apple Pay & Google Pay customize subscreen) all ship in one
 * schema; JS-registered groupVisibility predicates toggle them client-side on
 * the hidden wcpay_active_tab value, so switching tabs needs no page load.
 * Persistable field ids match `/wc/v3/payments/settings` REST arguments so
 * the JS save handler passes changed values through (with a few documented
 * compound mappings).
 */
class WC_Payments_Settings_UI_Adapter implements SettingsUIPageInterface {

	/**
	 * BNPL payment method ids, split into their own card per the design.
	 *
	 * @var string[]
	 */
	const BNPL_METHOD_IDS = [ 'affirm', 'afterpay_clearpay', 'klarna' ];

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
				'title'               => __( 'WooPayments settings', 'woocommerce-payments' ),
				// Native shell header fields (WC settings UI subtitle + badges).
				'subtitle'            => __( 'Manage your WooPayments settings, including payment methods and payouts.', 'woocommerce-payments' ),
				'badges'              => [
					[
						'label'  => __( 'Active', 'woocommerce-payments' ),
						'intent' => 'success',
					],
				],
				'breadcrumbs'         => [
					[
						'label' => __( 'Payments', 'woocommerce-payments' ),
						'href'  => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
					],
				],
				// Soft tab navigation: rendered by the wcpay/subnav region,
				// switching tabs client-side via groupVisibility predicates.
				'navigationComponent' => 'wcpay/subnav',
				'wcpayTabs'           => $this->get_tabs( $active_tab ),
			],
			'groups'  => $this->with_tab_state_field(
				array_merge(
					$this->get_general_groups(),
					$this->get_payment_methods_groups(),
					$this->get_express_customize_groups(),
					$this->get_payouts_groups(),
					$this->get_store_checkout_groups()
				),
				$active_tab
			),
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
		$ids = [ 'general', 'payment-methods', 'payouts', 'store-and-checkout', 'express-customize' ];

		return in_array( $tab, $ids, true ) ? $tab : 'general';
	}

	/**
	 * Tab definitions for the wcpay/subnav region. The express-customize
	 * subscreen is reachable via its Customize link, not the tab bar.
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
	 * Prepend the hidden active-tab UI-state field (which drives the JS
	 * groupVisibility predicates) to the first group of every design tab.
	 *
	 * The field component listens for tab-change events and must stay mounted,
	 * but only fields of visible groups are mounted — so one copy lives in
	 * each tab's first group (all share the same field id, hence the same
	 * value; exactly one copy is mounted at any time).
	 *
	 * @param array  $groups     All schema groups, keyed by group id.
	 * @param string $active_tab Active tab id from the request.
	 * @return array
	 */
	private function with_tab_state_field( array $groups, string $active_tab ): array {
		$tab_state_field = [
			'id'        => 'wcpay_active_tab',
			'label'     => '',
			'type'      => 'text',
			'component' => 'wcpay/tab-state',
			'value'     => $active_tab,
			'save'      => [ 'adapter' => 'none' ],
		];

		$first_group_per_tab = [
			'wcpay_test_mode',       // General.
			'wcpay_pm_global',       // Payment methods.
			'wcpay_ec_placement',    // Apple Pay & Google Pay customize.
			'wcpay_payout_schedule', // Payouts.
			'wcpay_sc_features',     // Store and checkout.
		];

		foreach ( $first_group_per_tab as $group_id ) {
			if ( isset( $groups[ $group_id ] ) ) {
				array_unshift( $groups[ $group_id ]['fields'], $tab_state_field );
			}
		}

		return $groups;
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
				'order'       => 1,
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
				'order'       => 2,
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
							// The advanced subpage is intentionally the existing
							// screen — the i1 design keeps it as a placeholder.
							'advancedUrl'  => admin_url( 'admin.php?page=wc-admin&path=/payments/fraud-protection' ),
						],
					],
				],
			],
			'wcpay_tax_id'                => [
				'id'          => 'wcpay_tax_id',
				'title'       => __( 'Tax ID', 'woocommerce-payments' ),
				'description' => __( 'Your Tax ID and business details for tax invoices.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 3,
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
				'order'       => 4,
				'fields'      => [
					[
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
				// Design copy is a placeholder ("Helper text.") — flagged for design.
				'title'       => __( 'Debug mode', 'woocommerce-payments' ),
				'description' => __( 'Capture additional payment process details for troubleshooting.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 5,
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
	 * Groups for the Payment methods tab.
	 *
	 * @return array
	 */
	private function get_payment_methods_groups(): array {
		$gateway = WC_Payments::get_gateway();

		$available = $gateway ? (array) $gateway->get_upe_available_payment_methods() : [];
		$enabled   = $gateway ? (array) $gateway->get_upe_enabled_payment_method_ids() : [];

		$method_option = function ( string $method_id ): array {
			$method = WC_Payments::get_payment_method_by_id( $method_id );
			return [
				'label' => $method ? $method->get_title() : ucfirst( str_replace( '_', ' ', $method_id ) ),
				'value' => $method_id,
			];
		};

		// Link, Apple Pay, and Google Pay render in the Express checkouts card;
		// BNPL methods in their own card.
		$express_ids = [ 'link', 'apple_pay', 'google_pay' ];
		$main_ids    = array_values(
			array_filter(
				$available,
				static function ( $id ) use ( $express_ids ) {
					return ! in_array( $id, $express_ids, true ) && ! in_array( $id, self::BNPL_METHOD_IDS, true );
				}
			)
		);
		$bnpl_ids    = array_values( array_intersect( $available, self::BNPL_METHOD_IDS ) );

		$is_payment_request = $gateway && 'yes' === $gateway->get_option( 'payment_request' );
		$is_woopay          = $gateway && 'yes' === $gateway->get_option( 'platform_checkout' );
		$is_link            = in_array( 'link', $enabled, true );

		$express_fields = [
			[
				'id'               => 'is_payment_request_enabled',
				'label'            => __( 'Apple Pay / Google Pay', 'woocommerce-payments' ),
				'type'             => 'checkbox',
				'component'        => 'wcpay/express-customizable',
				'description'      => __( 'Offer customers a fast, secure checkout experience with Apple Pay and Google Pay.', 'woocommerce-payments' ),
				'value'            => $is_payment_request,
				'customAttributes' => [
					'customizeTab' => 'express-customize',
				],
			],
			[
				'id'          => 'wcpay_link_enabled',
				'label'       => __( 'Link', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'component'   => 'wcpay/toggle',
				'description' => __( 'Link autofills your customers’ payment and shipping details to deliver an easy and seamless checkout experience.', 'woocommerce-payments' ),
				'value'       => $is_link,
				// Mapped into enabled_payment_method_ids by the save handler.
				'save'        => [ 'adapter' => 'none' ],
			],
		];

		if ( $is_woopay || ( $gateway && WC_Payments_Features::is_woopay_eligible() ) ) {
			$express_fields[] = [
				'id'          => 'is_woopay_enabled',
				'label'       => __( 'WooPay', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'component'   => 'wcpay/toggle',
				'description' => __( 'Allow your customers to check out faster with WooPay. (Subscreen design pending — see spike findings.)', 'woocommerce-payments' ),
				'value'       => $is_woopay,
			];
		}

		return [
			'wcpay_pm_global'  => [
				'id'          => 'wcpay_pm_global',
				'title'       => __( 'Global and local payment methods', 'woocommerce-payments' ),
				'description' => __( 'Choose which payment methods are available for use at checkout.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 10,
				'fields'      => [
					[
						'id'        => 'wcpay_pm_list_main',
						'label'     => '',
						'type'      => 'multiselect',
						'component' => 'wcpay/payment-methods-list',
						'value'     => array_values( array_intersect( $enabled, $main_ids ) ),
						'options'   => array_map( $method_option, $main_ids ),
						// Mapped into enabled_payment_method_ids by the save handler.
						'save'      => [ 'adapter' => 'none' ],
					],
				],
			],
			'wcpay_pm_express' => [
				'id'          => 'wcpay_pm_express',
				'title'       => __( 'Express checkouts', 'woocommerce-payments' ),
				'description' => __( 'Choose which payment methods are available for use at checkout.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 11,
				'fields'      => $express_fields,
			],
			'wcpay_pm_bnpl'    => [
				'id'          => 'wcpay_pm_bnpl',
				'title'       => __( 'Buy now, pay later', 'woocommerce-payments' ),
				'description' => __( 'Choose which payment methods are available for use at checkout.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 12,
				'fields'      => [
					[
						'id'        => 'wcpay_pm_list_bnpl',
						'label'     => '',
						'type'      => 'multiselect',
						'component' => 'wcpay/payment-methods-list',
						'value'     => array_values( array_intersect( $enabled, $bnpl_ids ) ),
						'options'   => array_map( $method_option, $bnpl_ids ),
						// Mapped into enabled_payment_method_ids by the save handler.
						'save'      => [ 'adapter' => 'none' ],
					],
				],
			],
		];
	}

	/**
	 * Groups for the Apple Pay & Google Pay customize subscreen (hidden tab,
	 * reachable via the Customize link on the Payment methods tab).
	 *
	 * @return array
	 */
	private function get_express_customize_groups(): array {
		$gateway = WC_Payments::get_gateway();

		$location_has_pr = static function ( string $location ) use ( $gateway ): bool {
			$methods = $gateway ? (array) $gateway->get_option( "express_checkout_{$location}_methods", [] ) : [];
			return in_array( 'payment_request', $methods, true );
		};

		$border_radius = $gateway ? (int) $gateway->get_option( 'payment_request_button_border_radius', 4 ) : 4;

		return [
			'wcpay_ec_placement' => [
				'id'          => 'wcpay_ec_placement',
				'title'       => __( 'Button placement', 'woocommerce-payments' ),
				'description' => __( 'Choose where Apple Pay and Google Pay buttons appear in your store.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 20,
				'fields'      => [
					[
						'id'        => 'wcpay_ec_back',
						'label'     => __( 'Back to Payment methods', 'woocommerce-payments' ),
						'type'      => 'info',
						'component' => 'wcpay/subscreen-back',
						'value'     => null,
						'save'      => [ 'adapter' => 'none' ],
					],
					[
						'id'        => 'wcpay_ec_location_product',
						'label'     => __( 'Product page', 'woocommerce-payments' ),
						'type'      => 'checkbox',
						'component' => 'wcpay/toggle',
						'value'     => $location_has_pr( 'product' ),
						// Mapped into express_checkout_product_methods by the save handler.
						'save'      => [ 'adapter' => 'none' ],
					],
					[
						'id'        => 'wcpay_ec_location_cart',
						'label'     => __( 'Shopping cart', 'woocommerce-payments' ),
						'type'      => 'checkbox',
						'component' => 'wcpay/toggle',
						'value'     => $location_has_pr( 'cart' ),
						'save'      => [ 'adapter' => 'none' ],
					],
					[
						'id'        => 'wcpay_ec_location_checkout',
						'label'     => __( 'Checkout', 'woocommerce-payments' ),
						'type'      => 'checkbox',
						'component' => 'wcpay/toggle',
						'value'     => $location_has_pr( 'checkout' ),
						'save'      => [ 'adapter' => 'none' ],
					],
					[
						'id'        => 'wcpay_ec_methods_product',
						'label'     => '',
						'type'      => 'multiselect',
						'component' => 'wcpay/hidden',
						'value'     => $gateway ? (array) $gateway->get_option( 'express_checkout_product_methods', [] ) : [],
						'save'      => [ 'adapter' => 'none' ],
					],
					[
						'id'        => 'wcpay_ec_methods_cart',
						'label'     => '',
						'type'      => 'multiselect',
						'component' => 'wcpay/hidden',
						'value'     => $gateway ? (array) $gateway->get_option( 'express_checkout_cart_methods', [] ) : [],
						'save'      => [ 'adapter' => 'none' ],
					],
					[
						'id'        => 'wcpay_ec_methods_checkout',
						'label'     => '',
						'type'      => 'multiselect',
						'component' => 'wcpay/hidden',
						'value'     => $gateway ? (array) $gateway->get_option( 'express_checkout_checkout_methods', [] ) : [],
						'save'      => [ 'adapter' => 'none' ],
					],
				],
			],
			'wcpay_ec_style'     => [
				'id'          => 'wcpay_ec_style',
				'title'       => __( 'Button style', 'woocommerce-payments' ),
				'description' => __( 'Customize how Apple Pay and Google Pay buttons appear in your store.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 21,
				'fields'      => [
					[
						'id'          => 'payment_request_button_type',
						'label'       => __( 'Button label', 'woocommerce-payments' ),
						'type'        => 'select',
						'description' => __( 'Choose the label shown on the payment buttons.', 'woocommerce-payments' ),
						'value'       => $gateway ? (string) $gateway->get_option( 'payment_request_button_type', 'default' ) : 'default',
						'options'     => [
							[
								'label' => __( 'Only icon', 'woocommerce-payments' ),
								'value' => 'default',
							],
							[
								'label' => __( 'Buy', 'woocommerce-payments' ),
								'value' => 'buy',
							],
							[
								'label' => __( 'Donate', 'woocommerce-payments' ),
								'value' => 'donate',
							],
							[
								'label' => __( 'Book', 'woocommerce-payments' ),
								'value' => 'book',
							],
						],
					],
					[
						'id'      => 'payment_request_button_size',
						'label'   => __( 'Size', 'woocommerce-payments' ),
						'type'    => 'radio',
						'value'   => $gateway ? (string) $gateway->get_option( 'payment_request_button_size', 'medium' ) : 'medium',
						'options' => [
							[
								'label' => __( 'Small (40 px)', 'woocommerce-payments' ),
								'value' => 'small',
							],
							[
								'label' => __( 'Medium (48 px)', 'woocommerce-payments' ),
								'value' => 'medium',
							],
							[
								'label' => __( 'Large (55 px)', 'woocommerce-payments' ),
								'value' => 'large',
							],
						],
					],
					[
						'id'      => 'payment_request_button_theme',
						'label'   => __( 'Theme', 'woocommerce-payments' ),
						'type'    => 'select',
						'value'   => $gateway ? (string) $gateway->get_option( 'payment_request_button_theme', 'dark' ) : 'dark',
						'options' => [
							[
								'label' => __( 'Dark', 'woocommerce-payments' ),
								'value' => 'dark',
							],
							[
								'label' => __( 'Light', 'woocommerce-payments' ),
								'value' => 'light',
							],
							[
								'label' => __( 'Light outline', 'woocommerce-payments' ),
								'value' => 'light-outline',
							],
						],
					],
					[
						// Design: Rectangle / Pill — mapped to the numeric
						// border radius REST arg by the save handler.
						'id'      => 'wcpay_ec_shape',
						'label'   => __( 'Shape', 'woocommerce-payments' ),
						'type'    => 'radio',
						'value'   => $border_radius >= 20 ? 'pill' : 'rectangle',
						'options' => [
							[
								'label' => __( 'Rectangle', 'woocommerce-payments' ),
								'value' => 'rectangle',
							],
							[
								'label' => __( 'Pill', 'woocommerce-payments' ),
								'value' => 'pill',
							],
						],
						'save'    => [ 'adapter' => 'none' ],
					],
				],
			],
		];
	}

	/**
	 * Groups for the Payouts tab.
	 *
	 * @return array
	 */
	private function get_payouts_groups(): array {
		$gateway = WC_Payments::get_gateway();

		$interval = $gateway ? (string) $gateway->get_option( 'deposit_schedule_interval', 'daily' ) : 'daily';

		$weekly_days    = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ];
		$weekly_options = array_map(
			static function ( $day ) {
				return [
					'label' => ucfirst( $day ),
					'value' => $day,
				];
			},
			$weekly_days
		);

		$monthly_options = [];
		for ( $i = 1; $i <= 28; $i++ ) {
			$monthly_options[] = [
				/* translators: %d: day of the month. */
				'label' => sprintf( __( '%dth', 'woocommerce-payments' ), $i ),
				'value' => (string) $i,
			];
		}

		$currency = '';
		try {
			$currency = strtoupper( WC_Payments::get_account_service()->get_account_default_currency() );
		} catch ( Exception $e ) {
			unset( $e );
		}

		return [
			'wcpay_payout_schedule' => [
				'id'          => 'wcpay_payout_schedule',
				'title'       => __( 'Payout schedule', 'woocommerce-payments' ),
				'description' => __( 'Funds are available 2 business days after you receive a payment. If a payout is scheduled for a weekend or holiday, it starts on the next business day.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 30,
				'fields'      => [
					[
						'id'      => 'deposit_schedule_interval',
						'label'   => __( 'Frequency', 'woocommerce-payments' ),
						'type'    => 'select',
						'value'   => $interval,
						'options' => [
							[
								'label' => __( 'Daily', 'woocommerce-payments' ),
								'value' => 'daily',
							],
							[
								'label' => __( 'Weekly', 'woocommerce-payments' ),
								'value' => 'weekly',
							],
							[
								'label' => __( 'Monthly', 'woocommerce-payments' ),
								'value' => 'monthly',
							],
						],
					],
					[
						'id'         => 'deposit_schedule_weekly_anchor',
						'label'      => __( 'Day', 'woocommerce-payments' ),
						'type'       => 'select',
						'value'      => $gateway ? (string) $gateway->get_option( 'deposit_schedule_weekly_anchor', 'monday' ) : 'monday',
						'options'    => $weekly_options,
						'visibility' => [
							'controller' => 'deposit_schedule_interval',
							'value'      => 'weekly',
						],
					],
					[
						'id'         => 'deposit_schedule_monthly_anchor',
						'label'      => __( 'Day', 'woocommerce-payments' ),
						'type'       => 'select',
						'value'      => $gateway ? (string) $gateway->get_option( 'deposit_schedule_monthly_anchor', '1' ) : '1',
						'options'    => $monthly_options,
						'visibility' => [
							'controller' => 'deposit_schedule_interval',
							'value'      => 'monthly',
						],
					],
				],
			],
			'wcpay_bank_account'    => [
				'id'          => 'wcpay_bank_account',
				'title'       => __( 'Connected bank account', 'woocommerce-payments' ),
				'description' => __( 'Manage and update your bank account information to receive payouts.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 31,
				'fields'      => [
					[
						// Read-only display. The design also shows the bank name,
						// last four digits, verification badge, and a "Manage in
						// Stripe" link — needs the accounts REST payload (spike gap).
						'id'          => 'wcpay_bank_account_info',
						'label'       => '',
						'type'        => 'info',
						'description' => $currency
							/* translators: %s: currency code. */
							? sprintf( __( 'Payout currency: %s', 'woocommerce-payments' ), $currency )
							: __( 'Connect an account to receive payouts.', 'woocommerce-payments' ),
						'value'       => null,
						'save'        => [ 'adapter' => 'none' ],
					],
				],
			],
		];
	}

	/**
	 * Groups for the Store and checkout tab.
	 *
	 * @return array
	 */
	private function get_store_checkout_groups(): array {
		$gateway = WC_Payments::get_gateway();

		return [
			'wcpay_sc_features'      => [
				'id'          => 'wcpay_sc_features',
				'title'       => __( 'Store and checkout features', 'woocommerce-payments' ),
				'description' => __( 'Control how payments work at checkout, including saved cards and manual capture.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 40,
				'fields'      => [
					[
						'id'          => 'is_saved_cards_enabled',
						'label'       => __( 'Allow saved cards', 'woocommerce-payments' ),
						'type'        => 'checkbox',
						'component'   => 'wcpay/toggle',
						'description' => __( 'Let returning customers pay with a card saved from a previous order. Card details are stored securely.', 'woocommerce-payments' ),
						'value'       => $gateway && 'yes' === $gateway->get_option( 'saved_cards' ),
					],
					[
						// Design adds a confirmation flow when enabling manual
						// capture (other methods become unavailable) — spike
						// renders a plain toggle, gap documented.
						'id'          => 'is_manual_capture_enabled',
						'label'       => __( 'Capture payments manually', 'woocommerce-payments' ),
						'type'        => 'checkbox',
						'component'   => 'wcpay/toggle',
						'description' => __( 'Authorize payments at checkout and capture them later.', 'woocommerce-payments' ),
						'value'       => $gateway && 'yes' === $gateway->get_option( 'manual_capture' ),
					],
				],
			],
			'wcpay_sc_multicurrency' => [
				'id'          => 'wcpay_sc_multicurrency',
				'title'       => __( 'Multicurrency support', 'woocommerce-payments' ),
				'description' => __( 'Configure multicurrency support in your store and checkout.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 41,
				'fields'      => [
					[
						'id'          => 'is_multi_currency_enabled',
						'label'       => __( 'Allow multi-currency payments', 'woocommerce-payments' ),
						'type'        => 'checkbox',
						'component'   => 'wcpay/toggle',
						'description' => __( 'Let customers shop and pay in multiple currencies.', 'woocommerce-payments' ),
						'value'       => class_exists( 'WC_Payments_Features' ) && WC_Payments_Features::is_customer_multi_currency_enabled(),
					],
					[
						// No settings REST backing — display-only in the spike.
						'id'          => 'wcpay_spike_mc_display_prices',
						'label'       => __( 'Display prices in different currencies', 'woocommerce-payments' ),
						'type'        => 'checkbox',
						'component'   => 'wcpay/toggle',
						'description' => __( 'Display product prices in multiple currencies.', 'woocommerce-payments' ),
						'value'       => false,
						'save'        => [ 'adapter' => 'none' ],
					],
				],
			],
			'wcpay_sc_statement'     => [
				'id'          => 'wcpay_sc_statement',
				'title'       => __( 'Statement name', 'woocommerce-payments' ),
				'description' => __( 'Choose how your store name appears on bank statements to help customers recognize charges from you.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 42,
				'fields'      => [
					[
						'id'               => 'account_statement_descriptor',
						'label'            => __( 'Name shown on statement', 'woocommerce-payments' ),
						'type'             => 'text',
						'description'      => '',
						'value'            => $gateway ? (string) $gateway->get_option( 'account_statement_descriptor' ) : '',
						'customAttributes' => [ 'maxLength' => 22 ],
					],
					[
						// WOOPMNT-6144 ships the REST backing; display-only here.
						'id'          => 'wcpay_spike_order_number',
						'label'       => __( 'Add order number', 'woocommerce-payments' ),
						'type'        => 'checkbox',
						'description' => __( 'Shows the order number on customers’ bank statements so they can recognize the charge.', 'woocommerce-payments' ),
						'value'       => false,
						'save'        => [ 'adapter' => 'none' ],
					],
				],
			],
			'wcpay_sc_support'       => [
				'id'          => 'wcpay_sc_support',
				'title'       => __( 'Customer support', 'woocommerce-payments' ),
				'description' => __( 'Add your support contact details. These will appear on receipts, invoices, and automated messages.', 'woocommerce-payments' ),
				'actions'     => [],
				'order'       => 43,
				'fields'      => [
					[
						'id'          => 'account_business_support_email',
						'label'       => __( 'Support email address', 'woocommerce-payments' ),
						'type'        => 'email',
						'description' => '',
						'value'       => $gateway ? (string) $gateway->get_option( 'account_business_support_email' ) : '',
					],
					[
						'id'          => 'account_business_support_phone',
						'label'       => __( 'Support phone number', 'woocommerce-payments' ),
						'type'        => 'tel',
						'description' => '',
						'value'       => $gateway ? (string) $gateway->get_option( 'account_business_support_phone' ) : '',
					],
				],
			],
		];
	}
}
