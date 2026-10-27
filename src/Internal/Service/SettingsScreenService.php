<?php
/**
 * Class SettingsScreenService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payments;
use WC_Payments_Features;
use WC_Payments_Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Adds the WooPayments settings to WooCommerce's experimental payment settings screen.
 *
 * WooCommerce shows a page for each core-data entity of kind `woo_settings_payments`. This class supplies
 * the entities, their fields and their layout, and the settings route the entities load and save through.
 */
class SettingsScreenService {
	/**
	 * Name of the main settings entity. WooCommerce shows it at `/settings/woopayments`.
	 */
	const SCREEN_ID = 'woopayments';

	/**
	 * Entity kind of WooCommerce's payment settings pages.
	 */
	const ENTITY_KIND = 'woo_settings_payments';

	/**
	 * Admin page of WooCommerce's payment settings screen.
	 */
	const PAGE_SLUG = 'wc-settings-screen-wp-admin';

	/**
	 * REST route of the settings entities, under `wc/v3`. Each entity's record is at `{route}/{entity name}`.
	 */
	const REST_ROUTE = '/payments/settings-dataform';

	/**
	 * Script that registers the entities and fields, and provides the existing WooPayments controls.
	 */
	const SCRIPT_HANDLE = 'wcpay-settings-screen';

	/**
	 * Express checkout methods with their own customisation sub-page, keyed by the classic `method` argument.
	 * Each sub-page is an entity named `woopayments-{path}`.
	 */
	const EXPRESS_SUBPAGES = [
		'woopay'          => 'woopay',
		'payment_request' => 'apple-pay-google-pay',
		'amazon_pay'      => 'amazon-pay',
	];

	/**
	 * Path of the advanced fraud protection sub-page, the entity `woopayments-fraud-protection`.
	 */
	const FRAUD_PROTECTION_SUBPAGE = 'fraud-protection';

	/**
	 * Cards with a description. DataForm card descriptions can only be text, so each description is a
	 * read-only field, rendered with its links by the screen's script and placed first in its card.
	 */
	private const CARD_DESCRIPTIONS = [
		'test-mode'               => 'test_mode_description',
		'fraud-protection'        => 'fraud_protection_description',
		'payouts'                 => 'payouts_description',
		'transactions'            => 'transactions_description',
		'account-notifications'   => 'account_notifications_description',
		'customer-facing-details' => 'customer_facing_details_description',
	];

	/**
	 * Registers hooks when the screen is enabled.
	 */
	public function init_hooks(): void {
		if ( ! WC_Payments_Features::is_settings_dataform_enabled() ) {
			return;
		}

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'get_entity_view_config_' . self::ENTITY_KIND . '_' . self::SCREEN_ID, [ $this, 'get_view_config' ] );
		foreach ( self::EXPRESS_SUBPAGES as $method => $path ) {
			add_filter(
				'get_entity_view_config_' . self::ENTITY_KIND . '_' . self::get_subpage_name( $path ),
				fn( $config ) => $this->get_subpage_view_config( $config, $method )
			);
		}
		add_filter( 'get_entity_view_config_' . self::ENTITY_KIND . '_' . self::get_subpage_name( self::FRAUD_PROTECTION_SUBPAGE ), [ $this, 'get_fraud_protection_view_config' ] );
		// wp-build fires this while it enqueues the screen's scripts, only on the screen.
		add_action( self::PAGE_SLUG . '_init', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Gets the name of a sub-page's entity. WooCommerce shows it at `/settings/{name}`.
	 *
	 * @param string $path The sub-page's path, such as `woopay`.
	 * @return string
	 */
	public static function get_subpage_name( string $path ): string {
		return self::SCREEN_ID . '-' . $path;
	}

	/**
	 * Gets the URL of a page of the settings screen.
	 *
	 * @param string $name The page's entity name.
	 * @return string
	 */
	public static function get_page_url( string $name ): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&p=/settings/' . $name );
	}

	/**
	 * Gets the settings entities: the main page and its sub-pages, with their titles and fields.
	 *
	 * @return array<int, array{name: string, label: string, fields: array[]}>
	 */
	public function get_entities(): array {
		$entities = [
			[
				'name'   => self::SCREEN_ID,
				'label'  => __( 'WooPayments settings', 'woocommerce-payments' ),
				'fields' => $this->get_fields(),
			],
		];
		foreach ( $this->get_subpage_titles() as $method => $title ) {
			$entities[] = [
				'name'   => self::get_subpage_name( self::EXPRESS_SUBPAGES[ $method ] ),
				'label'  => $title,
				'fields' => $this->get_subpage_fields( $method ),
			];
		}
		$entities[] = [
			'name'   => self::get_subpage_name( self::FRAUD_PROTECTION_SUBPAGE ),
			'label'  => __( 'Advanced fraud protection', 'woocommerce-payments' ),
			'fields' => $this->get_fraud_protection_fields(),
		];
		return $entities;
	}

	/**
	 * Registers the settings entities' route.
	 */
	public function register_routes(): void {
		register_rest_route(
			'wc/v3',
			self::REST_ROUTE . '/(?P<id>' . self::SCREEN_ID . '(?:-[a-z-]+)?)',
			[
				'methods'             => [ 'GET', 'POST', 'PUT' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'callback'            => [ $this, 'handle_settings_request' ],
			]
		);
	}

	/**
	 * Only store managers can read or change the settings. The delegated route checks this too.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Reads or saves the settings through the existing settings route, returning the saved record.
	 *
	 * The existing route keeps its validation and side effects. Its save response wraps the record
	 * in another response, so the saved settings are read again for core-data. Every entity reads the
	 * same settings, with the entity's name as the record ID.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_settings_request( WP_REST_Request $request ) {
		$id = (string) $request->get_param( 'id' );
		if ( 'GET' === $request->get_method() ) {
			return $this->get_record( $id );
		}

		$params = $request->get_params();
		unset( $params['id'] );

		// The existing route saves the advanced rules and the protection level only together, but core-data only sends changed values.
		$has_level = isset( $params['current_protection_level'] );
		$has_rules = array_key_exists( 'advanced_fraud_protection_settings', $params );
		if ( $has_level !== $has_rules ) {
			$current = $this->get_settings();
			if ( is_wp_error( $current ) ) {
				return $current;
			}
			if ( $has_level ) {
				$params['advanced_fraud_protection_settings'] = $current->get_data()['advanced_fraud_protection_settings'] ?? [];
			} else {
				$params['current_protection_level'] = $current->get_data()['current_protection_level'] ?? '';
			}
		}

		$save = new WP_REST_Request( 'POST', '/wc/v3/payments/settings' );
		$save->set_body_params( $params );
		$response = rest_do_request( $save );
		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $this->get_record( $id );
	}

	/**
	 * Gets the fields of the settings entity.
	 *
	 * @return array[]
	 */
	public function get_fields(): array {
		$toggle   = static function ( string $id, string $label, string $description = '' ): array {
			return array_filter(
				[
					'id'          => $id,
					'type'        => 'boolean',
					'label'       => $label,
					'description' => $description,
					'Edit'        => 'toggle',
				]
			);
		};
		$checkbox = static fn( string $id, string $label, string $description = '' ): array => array_merge( $toggle( $id, $label, $description ), [ 'Edit' => 'checkbox' ] );
		$text     = static function ( string $id, string $label, string $type = 'text' ): array {
			return [
				'id'    => $id,
				'type'  => $type,
				'label' => $label,
			];
		};
		$select   = static function ( string $id, string $label, array $options ): array {
			return [
				'id'       => $id,
				'type'     => 'text',
				'label'    => $label,
				'Edit'     => 'select',
				'elements' => array_map(
					static fn( $value, $option_label ) => [
						'value' => $value,
						'label' => $option_label,
					],
					array_keys( $options ),
					$options
				),
			];
		};

		$intervals = [
			'daily'   => __( 'Daily', 'woocommerce-payments' ),
			'weekly'  => __( 'Weekly', 'woocommerce-payments' ),
			'monthly' => __( 'Monthly', 'woocommerce-payments' ),
		];
		// Japanese accounts can't have daily payouts.
		if ( 'JP' === $this->get_account_country() ) {
			unset( $intervals['daily'] );
		}

		$descriptions = array_map(
			static fn( string $id ): array => [
				'id'       => $id,
				'type'     => 'text',
				'label'    => __( 'Description', 'woocommerce-payments' ),
				'readOnly' => true,
			],
			[ ...array_values( self::CARD_DESCRIPTIONS ), 'payout_bank_account', 'payout_schedule_notice', 'test_account_notice', 'fraud_protection_error', 'fraud_rules_link' ]
		);

		return array_merge(
			$descriptions,
			[
				$toggle(
					'is_test_mode_enabled',
					__( 'Enable test mode', 'woocommerce-payments' ),
					__( 'When test mode is on, orders are simulated and customers won’t be charged. Turn it off before accepting real payments.', 'woocommerce-payments' )
				),
				$text( 'account_communications_email', __( 'Email address', 'woocommerce-payments' ), 'email' ),
				array_merge(
					$select(
						'current_protection_level',
						__( 'Set your payment risk level', 'woocommerce-payments' ),
						[
							'basic'    => __( 'Basic', 'woocommerce-payments' ),
							'advanced' => __( 'Advanced', 'woocommerce-payments' ),
						]
					),
					[ 'Edit' => 'radio' ]
				),
				[
					'id'    => 'enabled_payment_method_ids',
					'label' => __( 'Payment methods', 'woocommerce-payments' ),
				],
				[
					'id'    => 'is_payment_request_enabled',
					'label' => __( 'Express checkouts', 'woocommerce-payments' ),
				],
				$toggle(
					'is_saved_cards_enabled',
					__( 'Enable payments via saved cards', 'woocommerce-payments' ),
					__( 'When enabled, users will be able to pay with a saved card during checkout. Card details are stored in our platform, not on your store.', 'woocommerce-payments' )
				),
				$toggle( 'is_manual_capture_enabled', __( 'Enable manual capture', 'woocommerce-payments' ) ),
				$text( 'account_statement_descriptor', __( 'Customer bank statement', 'woocommerce-payments' ) ),
				$text( 'account_statement_descriptor_kanji', __( 'Full bank statement (Kanji)', 'woocommerce-payments' ) ),
				$text( 'account_statement_descriptor_kana', __( 'Full bank statement (Kana)', 'woocommerce-payments' ) ),
				array_merge(
					$text( 'account_business_support_email', __( 'Support email', 'woocommerce-payments' ), 'email' ),
					[ 'description' => __( 'This may be visible on receipts, invoices, and automated emails from your store.', 'woocommerce-payments' ) ]
				),
				array_merge(
					$text( 'account_business_support_phone', __( 'Support phone number', 'woocommerce-payments' ) ),
					[
						'description' => __( "This number may appear on customer bank statements and in-person purchase receipts, but not in order emails. Use a number you're comfortable sharing publicly.", 'woocommerce-payments' ),
						'isValid'     => [ 'required' => true ],
					]
				),
				$select( 'deposit_schedule_interval', __( 'Frequency', 'woocommerce-payments' ), $intervals ),
				$select(
					'deposit_schedule_weekly_anchor',
					__( 'Day', 'woocommerce-payments' ),
					[
						'monday'    => __( 'Monday', 'woocommerce-payments' ),
						'tuesday'   => __( 'Tuesday', 'woocommerce-payments' ),
						'wednesday' => __( 'Wednesday', 'woocommerce-payments' ),
						'thursday'  => __( 'Thursday', 'woocommerce-payments' ),
						'friday'    => __( 'Friday', 'woocommerce-payments' ),
					]
				),
				[
					'id'    => 'deposit_schedule_monthly_anchor',
					'type'  => 'integer',
					'label' => __( 'Date', 'woocommerce-payments' ),
					'Edit'  => 'select',
				],
				$toggle( 'is_multi_currency_enabled', __( 'Enable Multi-Currency', 'woocommerce-payments' ) ),
				$toggle( 'is_stripe_billing_enabled', __( 'Enable Stripe Billing for future subscriptions', 'woocommerce-payments' ) ),
				$checkbox(
					'is_debug_log_enabled',
					__( 'Log error messages', 'woocommerce-payments' ),
					__( 'When enabled, payment error logs will be saved to WooCommerce > Status > Logs.', 'woocommerce-payments' )
				),
			]
		);
	}

	/**
	 * Lays the fields out as one page of cards, in the order of the designs.
	 *
	 * @param mixed $config The View Config container.
	 * @return mixed
	 */
	public function get_view_config( $config ) {
		if ( ! is_object( $config ) || ! is_callable( [ $config, 'merge' ] ) ) {
			return $config;
		}

		// The payment methods control shows the express checkouts too, in tabs inside its card.
		$payment_methods = [
			'id'     => 'enabled_payment_method_ids',
			'layout' => [
				'type'          => 'regular',
				'labelPosition' => 'none',
			],
		];

		// Frequency and day sit side by side.
		$payout_schedule     = [
			'id'       => 'payout-schedule',
			'layout'   => [ 'type' => 'row' ],
			'children' => [ 'deposit_schedule_interval', 'deposit_schedule_weekly_anchor', 'deposit_schedule_monthly_anchor' ],
		];
		$unlabelled          = static fn( string $id ): array => [
			'id'     => $id,
			'layout' => [
				'type'          => 'regular',
				'labelPosition' => 'none',
			],
		];
		$payout_bank_account = $unlabelled( 'payout_bank_account' );
		$fraud_rules_link    = $unlabelled( 'fraud_rules_link' );

		$cards = [
			'test-mode'               => [ __( 'Test mode', 'woocommerce-payments' ), [ 'is_test_mode_enabled' ] ],
			'fraud-protection'        => [ __( 'Fraud protection', 'woocommerce-payments' ), [ $unlabelled( 'fraud_protection_error' ), 'current_protection_level', $fraud_rules_link ] ],
			'payouts'                 => [ __( 'Payout schedule', 'woocommerce-payments' ), [ $unlabelled( 'payout_schedule_notice' ), $payout_schedule, $payout_bank_account ] ],
			'payment-methods'         => [ __( 'Payment methods', 'woocommerce-payments' ), [ $payment_methods ] ],
			'transactions'            => [ __( 'Transaction preferences', 'woocommerce-payments' ), [ 'is_saved_cards_enabled', 'is_manual_capture_enabled', 'is_multi_currency_enabled', 'is_stripe_billing_enabled' ] ],
			'account-notifications'   => [ __( 'Account notifications', 'woocommerce-payments' ), [ 'account_communications_email' ] ],
			'customer-facing-details' => [ __( 'Customer-facing details', 'woocommerce-payments' ), [ 'account_statement_descriptor', 'account_statement_descriptor_kanji', 'account_statement_descriptor_kana', 'account_business_support_email', 'account_business_support_phone' ] ],
			'debug'                   => [ __( 'Debug mode', 'woocommerce-payments' ), [ 'is_debug_log_enabled' ] ],
		];

		// Notices about the whole account sit above the cards; a notice about one setting sits in its card, after the description.
		$fields = [ $unlabelled( 'test_account_notice' ) ];
		foreach ( $cards as $id => [ $label, $children ] ) {
			if ( isset( self::CARD_DESCRIPTIONS[ $id ] ) ) {
				array_unshift( $children, $unlabelled( self::CARD_DESCRIPTIONS[ $id ] ) );
			}
			$fields[] = [
				'id'       => $id,
				'label'    => $label,
				'layout'   => [
					'type'          => 'card',
					'isCollapsible' => false,
				],
				'children' => $children,
			];
		}

		return $config->merge(
			[
				'form' => [
					'layout' => [
						'type'          => 'regular',
						'labelPosition' => 'top',
					],
					'fields' => $fields,
				],
			],
			1
		);
	}

	/**
	 * Enqueues the screen's script and styles on the payment settings screen.
	 *
	 * The script registers the entities and their fields before the screen loads, from the definitions
	 * passed here.
	 */
	public function enqueue_scripts(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( self::SCRIPT_HANDLE, 'dist/settings-screen' );
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wcpaySettingsScreenConfig',
			[
				'kind'          => self::ENTITY_KIND,
				'baseURL'       => '/wc/v3' . self::REST_ROUTE,
				'entities'      => $this->get_entities(),
				'fraudRulesUrl' => self::get_page_url( self::get_subpage_name( self::FRAUD_PROTECTION_SUBPAGE ) ),
			]
		);
		wp_enqueue_script( self::SCRIPT_HANDLE );
		wp_set_script_translations( self::SCRIPT_HANDLE, 'woocommerce-payments' );
		WC_Payments_Utils::register_style(
			self::SCRIPT_HANDLE,
			plugins_url( 'dist/settings-screen.css', WCPAY_PLUGIN_FILE ),
			[ 'WCPAY_ADMIN_SETTINGS' ],
			WC_Payments::get_file_version( 'dist/settings-screen.css' ),
			'all'
		);
		wp_enqueue_style( self::SCRIPT_HANDLE );

		/**
		 * Adds the account data and styles the existing settings controls need.
		 *
		 * @since 11.2.0
		 *
		 * @param string $handle The screen's script handle.
		 */
		do_action( 'wcpay_settings_screen_register_scripts', self::SCRIPT_HANDLE );
	}

	/**
	 * Gets the fields of an express checkout's customisation sub-page.
	 *
	 * Each method's placement is stored as the method's ID in the express checkout location lists, so the
	 * script module maps the `{method}_on_{location}` toggles to those lists. The button style is shared by
	 * all express checkout buttons, as on the classic page.
	 *
	 * @param string $method The express checkout method, a key of `EXPRESS_SUBPAGES`.
	 * @return array[]
	 */
	public function get_subpage_fields( string $method ): array {
		$read_only = static fn( string $id ): array => [
			'id'       => $id,
			'type'     => 'text',
			'label'    => __( 'Description', 'woocommerce-payments' ),
			'readOnly' => true,
		];
		$select    = function ( string $id, string $label, string $edit ): array {
			$options = $this->get_form_field_options( $id );
			return [
				'id'       => $id,
				'type'     => 'text',
				'label'    => $label,
				'Edit'     => $edit,
				'elements' => array_map(
					static fn( $value, $option_label ) => [
						'value' => $value,
						'label' => $option_label,
					],
					array_keys( $options ),
					$options
				),
			];
		};

		$fields = [ $read_only( "{$method}_placement_description" ) ];
		foreach ( [
			'product'  => __( 'Show on product page', 'woocommerce-payments' ),
			'cart'     => __( 'Show on cart page', 'woocommerce-payments' ),
			'checkout' => __( 'Show on checkout page', 'woocommerce-payments' ),
		] as $location => $label ) {
			$fields[] = [
				'id'    => "{$method}_on_{$location}",
				'type'  => 'boolean',
				'label' => $label,
				'Edit'  => 'toggle',
			];
		}

		if ( 'woopay' === $method ) {
			$fields[] = [
				'id'    => 'woopay_store_logo',
				'type'  => 'text',
				'label' => __( 'Checkout logo', 'woocommerce-payments' ),
			];
			$fields[] = [
				'id'    => 'is_woopay_global_theme_support_enabled',
				'type'  => 'boolean',
				'label' => __( 'Enable global theme support', 'woocommerce-payments' ),
				'Edit'  => 'checkbox',
			];
			$fields[] = [
				'id'    => 'woopay_custom_message',
				'type'  => 'text',
				'label' => __( 'Checkout policies', 'woocommerce-payments' ),
				'Edit'  => 'textarea',
			];
			$fields[] = $read_only( 'woopay_checkout_preview' );
		}

		if ( 'amazon_pay' !== $method ) {
			$fields[] = $read_only( 'button_style_description' );
			$fields[] = $select( 'payment_request_button_type', __( 'Button label', 'woocommerce-payments' ), 'select' );
			$fields[] = $select( 'payment_request_button_size', __( 'Button size', 'woocommerce-payments' ), 'radio' );
			$fields[] = $select( 'payment_request_button_theme', __( 'Theme', 'woocommerce-payments' ), 'radio' );
			$fields[] = [
				'id'    => 'payment_request_button_border_radius',
				'type'  => 'integer',
				'label' => __( 'Corner radius', 'woocommerce-payments' ),
			];
			$fields[] = $read_only( 'button_preview' );
		}

		return $fields;
	}

	/**
	 * Lays out an express checkout's customisation sub-page as cards, in the order of the designs.
	 *
	 * @param mixed  $config The View Config container.
	 * @param string $method The express checkout method, a key of `EXPRESS_SUBPAGES`.
	 * @return mixed
	 */
	public function get_subpage_view_config( $config, string $method ) {
		if ( ! is_object( $config ) || ! is_callable( [ $config, 'merge' ] ) ) {
			return $config;
		}

		$card       = static fn( string $id, string $label, array $children ): array => [
			'id'       => $id,
			'label'    => $label,
			'layout'   => [
				'type'          => 'card',
				'isCollapsible' => false,
			],
			'children' => $children,
		];
		$unlabelled = static fn( string $id ): array => [
			'id'     => $id,
			'layout' => [
				'type'          => 'regular',
				'labelPosition' => 'none',
			],
		];

		$cards = [
			$card( 'placement', __( 'Button placement', 'woocommerce-payments' ), [ $unlabelled( "{$method}_placement_description" ), "{$method}_on_product", "{$method}_on_cart", "{$method}_on_checkout" ] ),
		];
		if ( 'woopay' === $method ) {
			$cards[] = $card( 'appearance', __( 'Checkout appearance', 'woocommerce-payments' ), [ 'woopay_store_logo', 'is_woopay_global_theme_support_enabled', 'woopay_custom_message', $unlabelled( 'woopay_checkout_preview' ) ] );
		}
		if ( 'amazon_pay' !== $method ) {
			$cards[] = $card( 'button-style', __( 'Button style', 'woocommerce-payments' ), [ $unlabelled( 'button_style_description' ), 'payment_request_button_type', 'payment_request_button_size', 'payment_request_button_theme', 'payment_request_button_border_radius', $unlabelled( 'button_preview' ) ] );
		}

		return $config->merge(
			[
				'form' => [
					'layout' => [
						'type'          => 'regular',
						'labelPosition' => 'top',
					],
					'fields' => $cards,
				],
			],
			1
		);
	}

	/**
	 * Gets the fields of the advanced fraud protection sub-page.
	 *
	 * The rules are one field: the script module renders the existing rule cards, which edit the ruleset together.
	 *
	 * @return array[]
	 */
	public function get_fraud_protection_fields(): array {
		return [
			[
				'id'    => 'advanced_fraud_protection_settings',
				'label' => __( 'Advanced fraud protection', 'woocommerce-payments' ),
			],
		];
	}

	/**
	 * Lays out the advanced fraud protection sub-page. The rule cards are the field's own cards.
	 *
	 * @param mixed $config The View Config container.
	 * @return mixed
	 */
	public function get_fraud_protection_view_config( $config ) {
		if ( ! is_object( $config ) || ! is_callable( [ $config, 'merge' ] ) ) {
			return $config;
		}

		return $config->merge(
			[
				'form' => [
					'layout' => [
						'type'          => 'regular',
						'labelPosition' => 'none',
					],
					'fields' => [ 'advanced_fraud_protection_settings' ],
				],
			],
			1
		);
	}

	/**
	 * Gets the titles of the express checkout sub-pages, keyed by method.
	 *
	 * @return array<string, string>
	 */
	private function get_subpage_titles(): array {
		return [
			'woopay'          => __( 'WooPay', 'woocommerce-payments' ),
			'payment_request' => __( 'Apple Pay / Google Pay', 'woocommerce-payments' ),
			'amazon_pay'      => __( 'Amazon Pay', 'woocommerce-payments' ),
		];
	}

	/**
	 * Gets the options of one of the gateway's form fields, such as the express checkout button types.
	 *
	 * @param string $key The form field key.
	 * @return array<string, string>
	 */
	private function get_form_field_options( string $key ): array {
		$options = WC_Payments::get_gateway()->get_form_fields()[ $key ]['options'] ?? [];
		return is_array( $options ) ? $options : [];
	}

	/**
	 * Reads the current settings through the existing settings route.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function get_settings() {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/payments/settings' ) );
		return $response->is_error() ? $response->as_error() : $response;
	}

	/**
	 * Reads the current settings as an entity's record.
	 *
	 * @param string $id The entity's name, used as the record ID.
	 * @return WP_REST_Response|WP_Error
	 */
	private function get_record( string $id ) {
		$response = $this->get_settings();
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = $response->get_data();
		return new WP_REST_Response( array_merge( is_array( $data ) ? $data : [], [ 'id' => $id ] ) );
	}

	/**
	 * Gets the country of the connected account.
	 *
	 * @return string
	 */
	private function get_account_country(): string {
		return (string) WC_Payments::get_gateway()->get_option( 'account_country' );
	}
}
