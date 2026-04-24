<?php
/**
 * WooPayments modern settings page adapter.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( '\Automattic\WooCommerce\Admin\Settings\LegacySettingsPageAdapter' ) ) {
	/**
	 * Adapts the WooPayments gateway settings for WooCommerce modern settings.
	 */
	final class WC_Payments_Modern_Settings_Page_Adapter extends \Automattic\WooCommerce\Admin\Settings\LegacySettingsPageAdapter {

		/**
		 * Query arg used for the WooPayments modern settings subtab.
		 */
		const TAB_QUERY_ARG = 'wcpay-settings-tab';

		/**
		 * General tab id.
		 */
		const TAB_GENERAL = 'general';

		/**
		 * Payment methods tab id.
		 */
		const TAB_PAYMENT_METHODS = 'payment-methods';

		/**
		 * Transactions tab id.
		 */
		const TAB_TRANSACTIONS = 'transactions';

		/**
		 * Fraud protection tab id.
		 */
		const TAB_FRAUD_PROTECTION = 'fraud-protection';

		/**
		 * WooPayments gateway.
		 *
		 * @var WC_Payment_Gateway_WCPay
		 */
		private $gateway;

		/**
		 * Constructor.
		 *
		 * @param WC_Settings_Page         $settings_page Settings page.
		 * @param WC_Payment_Gateway_WCPay $gateway       WooPayments gateway.
		 */
		public function __construct( WC_Settings_Page $settings_page, WC_Payment_Gateway_WCPay $gateway ) {
			parent::__construct( $settings_page );
			$this->gateway = $gateway;
		}

		/**
		 * Get the stable page id used for scoping the modern settings UI.
		 *
		 * @return string
		 */
		public function get_page_id(): string {
			return 'checkout';
		}

		/**
		 * Build the modern settings schema.
		 *
		 * @param string $section Section ID.
		 * @return array
		 */
		public function get_schema( string $section ): array {
			return [
				'id'      => $this->get_page_id(),
				'title'   => 'WooPayments',
				'section' => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				'groups'  => $this->get_groups_for_active_tab(),
			];
		}

		/**
		 * Get WooPayments modern settings tabs.
		 *
		 * @return array
		 */
		public static function get_tabs(): array {
			return [
				self::TAB_GENERAL          => __( 'General', 'woocommerce-payments' ),
				self::TAB_PAYMENT_METHODS  => __( 'Payment methods', 'woocommerce-payments' ),
				self::TAB_TRANSACTIONS     => __( 'Transactions', 'woocommerce-payments' ),
				self::TAB_FRAUD_PROTECTION => __( 'Fraud protection', 'woocommerce-payments' ),
			];
		}

		/**
		 * Get the active WooPayments modern settings tab.
		 *
		 * @return string
		 */
		public static function get_active_tab(): string {
			$tab = isset( $_GET[ self::TAB_QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::TAB_QUERY_ARG ] ) ) : self::TAB_GENERAL; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return array_key_exists( $tab, self::get_tabs() ) ? $tab : self::TAB_GENERAL;
		}

		/**
		 * Get custom component script handles.
		 *
		 * @param string $section Section ID.
		 * @return array
		 */
		public function get_script_handles( string $section ): array {
			return [ 'WCPAY_MODERN_SETTINGS' ];
		}

		/**
		 * Build the modern settings groups.
		 *
		 * @return array
		 */
		private function get_groups_for_active_tab(): array {
			$groups = $this->get_groups();

			switch ( self::get_active_tab() ) {
				case self::TAB_PAYMENT_METHODS:
					return array_intersect_key(
						$groups,
						array_flip(
							[
								'payment-methods',
								'buy-now-pay-later',
								'express-checkouts',
							]
						)
					);
				case self::TAB_TRANSACTIONS:
					return array_intersect_key(
						$groups,
						array_flip(
							[
								'transactions',
								'customer-statements',
								'customer-support',
							]
						)
					);
				case self::TAB_FRAUD_PROTECTION:
					return [
						'advanced-fraud-protection' => $groups['advanced-fraud-protection'],
					];
				case self::TAB_GENERAL:
				default:
					return array_intersect_key(
						$groups,
						array_flip(
							[
								'general',
								'payouts',
								'account-notifications',
								'fraud-protection',
								'advanced-settings',
							]
						)
					);
			}
		}

		/**
		 * Build all modern settings groups.
		 *
		 * @return array
		 */
		private function get_groups(): array {
			$form_fields = $this->gateway->get_form_fields();

			return [
				'general'                   => [
					'id'          => 'general',
					'title'       => __( 'General', 'woocommerce-payments' ),
					'description' => sprintf(
						/* translators: %s: WooPayments */
						__( 'Enable or disable %s on your store.', 'woocommerce-payments' ),
						'WooPayments'
					),
					'order'       => 0,
					'fields'      => $this->get_fields(
						$form_fields,
						[
							'test_mode',
						]
					),
				],
				'payouts'                   => [
					'id'          => 'payouts',
					'title'       => __( 'Payouts', 'woocommerce-payments' ),
					'description' => sprintf(
						/* translators: %d: number of business days. */
						__( 'Funds are available for payout %d business days after they’re received.', 'woocommerce-payments' ),
						$this->get_deposit_delay_days()
					),
					'order'       => 1,
					'fields'      => [
						$this->get_display_field(
							'payout_schedule',
							__( 'Payout schedule', 'woocommerce-payments' ),
							'woopayments/payout-schedule'
						),
					],
				],
				'account-notifications'     => [
					'id'          => 'account-notifications',
					'title'       => __( 'Account notifications', 'woocommerce-payments' ),
					'description' => __( 'Receive important notifications about your WooPayments account.', 'woocommerce-payments' ),
					'order'       => 2,
					'fields'      => [
						$this->get_account_field(
							'account_communications_email',
							__( 'Email address', 'woocommerce-payments' ),
							'email',
							__( 'Provide an email address where you would like to receive communications about your WooPayments account.', 'woocommerce-payments' )
						),
					],
				],
				'fraud-protection'          => [
					'id'          => 'fraud-protection',
					'title'       => __( 'Fraud protection', 'woocommerce-payments' ),
					'description' => __( 'Help avoid unauthorized transactions and disputes by setting your fraud protection level.', 'woocommerce-payments' ),
					'order'       => 3,
					'fields'      => [
						$this->get_display_field(
							'fraud_protection_level',
							__( 'Set your payment risk level', 'woocommerce-payments' ),
							'woopayments/fraud-protection-level'
						),
					],
				],
				'advanced-settings'         => [
					'id'          => 'advanced-settings',
					'title'       => __( 'Advanced settings', 'woocommerce-payments' ),
					'description' => __( 'More options for specific payment needs.', 'woocommerce-payments' ),
					'order'       => 4,
					'fields'      => $this->get_fields(
						$form_fields,
						[
							'enable_logging',
						]
					),
				],
				'payment-methods'           => [
					'id'          => 'payment-methods',
					'title'       => __( 'Payments accepted on checkout', 'woocommerce-payments' ),
					'description' => __( 'Add and edit payments available to customers at checkout. Based on their device type, location, and purchase history, your customers will only see the most relevant payment methods.', 'woocommerce-payments' ),
					'order'       => 10,
					'fields'      => $this->get_fields(
						$this->with_payment_method_options( $form_fields, 'all' ),
						[
							'upe_enabled_payment_method_ids',
						]
					),
				],
				'buy-now-pay-later'         => [
					'id'          => 'buy-now-pay-later',
					'title'       => __( 'Buy now, pay later', 'woocommerce-payments' ),
					'description' => __( 'Boost sales by offering customers additional buying power and flexible payment options.', 'woocommerce-payments' ),
					'order'       => 11,
					'fields'      => [
						$this->get_payment_methods_field( 'upe_enabled_payment_method_ids_bnpl', 'buy-now-pay-later' ),
					],
				],
				'express-checkouts'         => [
					'id'          => 'express-checkouts',
					'title'       => __( 'Express checkouts', 'woocommerce-payments' ),
					'description' => __( 'Let your customers use their favorite express payment methods and digital wallets for faster, more secure checkouts across different parts of your store.', 'woocommerce-payments' ),
					'order'       => 12,
					'fields'      => [
						$this->get_express_checkout_field(),
					],
				],
				'transactions'              => [
					'id'          => 'transactions',
					'title'       => __( 'Transactions', 'woocommerce-payments' ),
					'description' => __( "Update your store's configuration to ensure smooth transactions.", 'woocommerce-payments' ),
					'order'       => 20,
					'fields'      => $this->get_fields(
						$form_fields,
						[
							'saved_cards',
							'manual_capture',
						]
					),
				],
				'customer-statements'       => [
					'id'          => 'customer-statements',
					'title'       => '',
					'description' => '',
					'order'       => 21,
					'fields'      => $this->get_fields(
						$form_fields,
						[
							'account_statement_descriptor',
						]
					),
				],
				'customer-support'          => [
					'id'          => 'customer-support',
					'title'       => '',
					'description' => '',
					'order'       => 22,
					'fields'      => [
						$this->get_account_field(
							'account_business_support_email',
							__( 'Support email', 'woocommerce-payments' ),
							'email',
							__( 'This may be visible on receipts, invoices, and automated emails from your store.', 'woocommerce-payments' )
						),
						$this->get_account_field(
							'account_business_support_phone',
							__( 'Support phone number', 'woocommerce-payments' ),
							'tel',
							__( 'This may be visible on receipts, invoices, and automated emails from your store.', 'woocommerce-payments' )
						),
					],
				],
				'advanced-fraud-protection' => [
					'id'          => 'advanced-fraud-protection',
					'title'       => __( 'Filter configuration', 'woocommerce-payments' ),
					'description' => __( 'Set up advanced fraud filters. Enable at least one filter to activate advanced protection.', 'woocommerce-payments' ),
					'order'       => 30,
					'fields'      => [
						$this->get_display_field(
							'advanced_fraud_protection_settings',
							__( 'Advanced fraud protection', 'woocommerce-payments' ),
							'woopayments/advanced-fraud-protection'
						),
					],
				],
			];
		}

		/**
		 * Build a list of modern fields from gateway form fields.
		 *
		 * @param array $form_fields Gateway form fields.
		 * @param array $field_ids   Field IDs.
		 * @return array
		 */
		private function get_fields( array $form_fields, array $field_ids ): array {
			$fields = [];

			foreach ( $field_ids as $field_id ) {
				if ( empty( $form_fields[ $field_id ] ) || ! is_array( $form_fields[ $field_id ] ) ) {
					continue;
				}

				$fields[] = $this->get_field( $field_id, $form_fields[ $field_id ] );
			}

			return array_values( array_filter( $fields ) );
		}

		/**
		 * Build a modern field from a gateway form field.
		 *
		 * @param string $field_id Field ID.
		 * @param array  $field    Gateway form field.
		 * @return array|null
		 */
		private function get_field( string $field_id, array $field ): ?array {
			$type = $this->normalize_type( $field['type'] ?? 'text' );
			if ( ! $type ) {
				return null;
			}

			$modern_field = [
				'id'          => $field_id,
				'label'       => $this->get_field_label( $field_id, $field ),
				'type'        => $type,
				'description' => $this->get_field_description( $field ),
				'value'       => $this->get_field_value( $field_id, $field, $type ),
				'save'        => [
					'adapter' => 'form_post',
					'name'    => $this->gateway->get_field_key( $field_id ),
				],
			];

			if ( ! empty( $field['placeholder'] ) && is_scalar( $field['placeholder'] ) ) {
				$modern_field['placeholder'] = (string) $field['placeholder'];
			}

			if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
				$modern_field['customAttributes'] = $this->get_custom_attributes( $field['custom_attributes'] );
			}

			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				$modern_field['options'] = $this->get_options( $field['options'] );
			}

			if ( 'upe_enabled_payment_method_ids' === $field_id ) {
				$modern_field['component'] = 'woopayments/payment-methods';
			}

			return $modern_field;
		}

		/**
		 * Normalize legacy field types to SDK field types.
		 *
		 * @param string $type Legacy field type.
		 * @return string|null
		 */
		private function normalize_type( string $type ): ?string {
			if ( 'account_statement_descriptor' === $type ) {
				return 'text';
			}

			if ( 'multiselect' === $type ) {
				return 'array';
			}

			if ( in_array( $type, [ 'text', 'password', 'email', 'url', 'tel', 'number', 'textarea', 'checkbox', 'select', 'radio' ], true ) ) {
				return $type;
			}

			return null;
		}

		/**
		 * Get field label.
		 *
		 * @param string $field_id Field ID.
		 * @param array  $field    Gateway form field.
		 * @return string
		 */
		private function get_field_label( string $field_id, array $field ): string {
			if ( 'checkbox' === ( $field['type'] ?? '' ) && ! empty( $field['label'] ) && is_scalar( $field['label'] ) ) {
				return wp_strip_all_tags( html_entity_decode( (string) $field['label'] ) );
			}

			if ( ! empty( $field['title'] ) && is_scalar( $field['title'] ) ) {
				return wp_strip_all_tags( html_entity_decode( (string) $field['title'] ) );
			}

			return $field_id;
		}

		/**
		 * Get field description.
		 *
		 * @param array $field Gateway form field.
		 * @return string
		 */
		private function get_field_description( array $field ): string {
			if ( ! empty( $field['description'] ) && is_scalar( $field['description'] ) ) {
				return wp_kses_post( (string) $field['description'] );
			}

			return '';
		}

		/**
		 * Get field value.
		 *
		 * @param string $field_id Field ID.
		 * @param array  $field    Gateway form field.
		 * @param string $type     Modern field type.
		 * @return mixed
		 */
		private function get_field_value( string $field_id, array $field, string $type ) {
			$value = $this->gateway->get_option( $field_id, $field['default'] ?? '' );

			if ( 'checkbox' === $type ) {
				return wc_string_to_bool( $value );
			}

			if ( 'array' === $type ) {
				return is_array( $value ) ? array_values( $value ) : [];
			}

			return $value;
		}

		/**
		 * Normalize field options.
		 *
		 * @param array $options Field options.
		 * @return array
		 */
		private function get_options( array $options ): array {
			$modern_options = [];

			foreach ( $options as $value => $label ) {
				if ( is_array( $label ) && isset( $label['value'], $label['label'] ) ) {
					$modern_options[] = $label;
					continue;
				}

				if ( ! is_scalar( $label ) && null !== $label ) {
					continue;
				}

				$modern_options[] = [
					'label' => is_scalar( $label ) ? html_entity_decode( (string) $label ) : '',
					'value' => (string) $value,
				];
			}

			return $modern_options;
		}

		/**
		 * Normalize custom attributes.
		 *
		 * @param array $custom_attributes Custom attributes.
		 * @return array
		 */
		private function get_custom_attributes( array $custom_attributes ): array {
			$attributes = [];

			foreach ( $custom_attributes as $attribute => $value ) {
				if ( is_scalar( $value ) ) {
					$attributes[ (string) $attribute ] = $value;
				}
			}

			return $attributes;
		}

		/**
		 * Add payment method options to the gateway fields.
		 *
		 * @param array  $form_fields Gateway form fields.
		 * @param string $category   Payment method category.
		 * @return array
		 */
		private function with_payment_method_options( array $form_fields, string $category ): array {
			if ( empty( $form_fields['upe_enabled_payment_method_ids'] ) ) {
				return $form_fields;
			}

			$form_fields['upe_enabled_payment_method_ids']['options'] = $this->get_payment_method_options( $category );

			return $form_fields;
		}

		/**
		 * Build a synthetic payment methods field.
		 *
		 * @param string $field_id Field ID.
		 * @param string $category Payment method category.
		 * @return array
		 */
		private function get_payment_methods_field( string $field_id, string $category ): array {
			$field = $this->get_field(
				'upe_enabled_payment_method_ids',
				[
					'title'   => $field_id,
					'type'    => 'multiselect',
					'default' => [ 'card' ],
					'options' => [],
				]
			);

			$field['id']        = $field_id;
			$field['label']     = $field_id;
			$field['component'] = 'woopayments/payment-methods';
			$field['options']   = $this->get_payment_method_options( 'all' );
			$field['save']      = [
				'adapter' => 'none',
			];

			return $field;
		}

		/**
		 * Build payment method options for a category.
		 *
		 * @param string $category Payment method category.
		 * @return array
		 */
		private function get_payment_method_options( string $category ): array {
			$options         = [];
			$account_country = strtoupper( $this->gateway->get_account_country() );
			$bnpl_methods    = [ 'affirm', 'afterpay_clearpay', 'klarna' ];
			$express_methods = [ 'apple_pay', 'google_pay', 'amazon_pay', 'link', 'woopay' ];

			foreach ( $this->gateway->get_upe_available_payment_methods() as $payment_method_id ) {
				if ( 'buy-now-pay-later' === $category && ! in_array( $payment_method_id, $bnpl_methods, true ) ) {
					continue;
				}

				if ( 'standard' === $category && ( in_array( $payment_method_id, $bnpl_methods, true ) || in_array( $payment_method_id, $express_methods, true ) ) ) {
					continue;
				}

				$payment_method = WC_Payments::get_payment_method_by_id( $payment_method_id );
				if ( ! $payment_method ) {
					continue;
				}

				$options[] = [
					'label'       => $payment_method->get_title( $account_country ),
					'value'       => $payment_method_id,
					'description' => $payment_method->get_description( $account_country ),
					'icon'        => $payment_method->get_icon( $account_country ),
					'category'    => $this->get_payment_method_category( $payment_method_id, $bnpl_methods, $express_methods ),
				];
			}

			return $options;
		}

		/**
		 * Get payment method category.
		 *
		 * @param string $payment_method_id Payment method ID.
		 * @param array  $bnpl_methods      Buy now, pay later method IDs.
		 * @param array  $express_methods   Express checkout method IDs.
		 * @return string
		 */
		private function get_payment_method_category( string $payment_method_id, array $bnpl_methods, array $express_methods ): string {
			if ( in_array( $payment_method_id, $bnpl_methods, true ) ) {
				return 'buy-now-pay-later';
			}

			if ( in_array( $payment_method_id, $express_methods, true ) ) {
				return 'express';
			}

			return 'standard';
		}

		/**
		 * Build a display-only field rendered by a custom component.
		 *
		 * @param string $field_id  Field ID.
		 * @param string $label     Field label.
		 * @param string $component Component name.
		 * @return array
		 */
		private function get_display_field( string $field_id, string $label, string $component ): array {
			return [
				'id'        => $field_id,
				'label'     => $label,
				'type'      => 'info',
				'value'     => $this->get_display_field_value( $field_id ),
				'component' => $component,
				'save'      => [
					'adapter' => 'none',
				],
			];
		}

		/**
		 * Get custom display field value.
		 *
		 * @param string $field_id Field ID.
		 * @return mixed
		 */
		private function get_display_field_value( string $field_id ) {
			if ( 'fraud_protection_level' === $field_id ) {
				return get_option( 'current_protection_level', 'basic' );
			}

			if ( 'advanced_fraud_protection_settings' === $field_id ) {
				return wp_json_encode( $this->gateway->get_option( 'advanced_fraud_protection_settings' ) );
			}

			return '';
		}

		/**
		 * Build an account-backed field.
		 *
		 * @param string $field_id    Field ID.
		 * @param string $label       Field label.
		 * @param string $type        Field type.
		 * @param string $description Field description.
		 * @return array
		 */
		private function get_account_field( string $field_id, string $label, string $type, string $description ): array {
			return [
				'id'          => $field_id,
				'label'       => $label,
				'type'        => $type,
				'description' => $description,
				'value'       => $this->gateway->get_option( $field_id ),
				'save'        => [
					'adapter' => 'form_post',
					'name'    => $this->gateway->get_field_key( $field_id ),
				],
			];
		}

		/**
		 * Build the express checkout summary field.
		 *
		 * @return array
		 */
		private function get_express_checkout_field(): array {
			return [
				'id'        => 'express_checkout_methods_summary',
				'label'     => __( 'Express checkouts', 'woocommerce-payments' ),
				'type'      => 'info',
				'value'     => '',
				'component' => 'woopayments/express-checkouts',
				'save'      => [
					'adapter' => 'none',
				],
			];
		}

		/**
		 * Get the deposit delay in business days.
		 *
		 * @return int
		 */
		private function get_deposit_delay_days(): int {
			$account_data = WC_Payments::get_account_service()->get_cached_account_data();

			if ( isset( $account_data['deposits']['delay_days'] ) ) {
				return absint( $account_data['deposits']['delay_days'] );
			}

			return 2;
		}
	}
}
