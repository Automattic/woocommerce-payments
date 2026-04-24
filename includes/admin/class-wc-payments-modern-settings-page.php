<?php
/**
 * WooPayments modern settings page registration.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers WooPayments with WooCommerce modern settings.
 */
final class WC_Payments_Modern_Settings_Page {

	/**
	 * WooPayments gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway WooPayments gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Initializes hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'woocommerce_get_settings_pages', [ $this, 'replace_checkout_settings_page' ], 20 );
		add_action( 'woocommerce_update_options_payment_gateways_' . WC_Payment_Gateway_WCPay::GATEWAY_ID, [ $this, 'process_gateway_admin_options' ] );
	}

	/**
	 * Replace the Checkout settings page with a WooPayments-aware modern settings page.
	 *
	 * @param array $settings_pages Settings pages.
	 * @return array
	 */
	public function replace_checkout_settings_page( array $settings_pages ): array {
		if ( ! $this->is_modern_settings_enabled() || ! class_exists( 'WC_Settings_Payment_Gateways' ) || ! class_exists( 'WC_Payments_Modern_Settings_Page_Adapter' ) ) {
			return $settings_pages;
		}

		foreach ( $settings_pages as $index => $settings_page ) {
			if ( ! $settings_page instanceof WC_Settings_Payment_Gateways ) {
				continue;
			}

			$this->remove_checkout_page_hooks( $settings_page );
			$settings_pages[ $index ] = $this->create_checkout_settings_page();
			break;
		}

		return $settings_pages;
	}

	/**
	 * Process WooPayments gateway options for the SDK form_post save flow.
	 *
	 * @return void
	 */
	public function process_gateway_admin_options() {
		if ( ! $this->is_modern_settings_enabled() ) {
			return;
		}

		$this->gateway->process_admin_options();
		$this->update_account_settings_from_post();
	}

	/**
	 * Determine if WooCommerce modern settings are enabled.
	 *
	 * @return bool
	 */
	private function is_modern_settings_enabled(): bool {
		return class_exists( '\Automattic\WooCommerce\Admin\Features\Features' ) && \Automattic\WooCommerce\Admin\Features\Features::is_enabled( 'modern-settings' );
	}

	/**
	 * Update connected account settings saved through the gateway form.
	 *
	 * @return void
	 */
	private function update_account_settings_from_post() {
		$account_settings = [];

		foreach ( array_keys( WC_Payment_Gateway_WCPay::ACCOUNT_SETTINGS_MAPPING ) as $field_id ) {
			$field_key = $this->gateway->get_field_key( $field_id );
			if ( ! isset( $_POST[ $field_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked by WC_Admin_Settings::save().
				continue;
			}

			if ( isset( $this->gateway->settings[ $field_id ] ) ) {
				$account_settings[ $field_id ] = $this->gateway->settings[ $field_id ];
				continue;
			}

			$value = wc_clean( wp_unslash( $_POST[ $field_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked by WC_Admin_Settings::save().

			if ( ! $this->validate_modern_account_field( $field_id, $value ) ) {
				continue;
			}

			$account_settings[ $field_id ] = $value;
		}

		if ( empty( $account_settings ) ) {
			return;
		}

		$result = $this->gateway->update_account_settings( $account_settings );
		if ( is_wp_error( $result ) ) {
			WC_Admin_Settings::add_error( $result->get_error_message() );
		}
	}

	/**
	 * Validate account-backed fields that are only present on the modern settings form.
	 *
	 * @param string $field_id Field ID.
	 * @param string $value    Field value.
	 * @return bool
	 */
	private function validate_modern_account_field( string $field_id, string $value ): bool {
		if ( 'account_communications_email' === $field_id && '' === $value ) {
			WC_Admin_Settings::add_error( __( 'Error: Communications email is required.', 'woocommerce-payments' ) );
			return false;
		}

		if ( in_array( $field_id, [ 'account_communications_email', 'account_business_support_email' ], true ) && '' !== $value && ! is_email( $value ) ) {
			WC_Admin_Settings::add_error(
				sprintf(
					/* translators: %s: Email address. */
					__( 'Error: Invalid email address: %s', 'woocommerce-payments' ),
					$value
				)
			);
			return false;
		}

		if ( 'account_business_support_phone' === $field_id && '' !== $value && ! WC_Validation::is_phone( $value ) ) {
			WC_Admin_Settings::add_error(
				sprintf(
					/* translators: %s: Phone number. */
					__( 'Error: Invalid phone number: %s', 'woocommerce-payments' ),
					$value
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Remove hooks registered by the original Checkout settings page instance.
	 *
	 * @param WC_Settings_Payment_Gateways $settings_page Original settings page.
	 * @return void
	 */
	private function remove_checkout_page_hooks( WC_Settings_Payment_Gateways $settings_page ) {
		remove_filter( 'woocommerce_settings_tabs_array', [ $settings_page, 'add_settings_page' ], 20 );
		remove_action( 'woocommerce_sections_' . $settings_page->get_id(), [ $settings_page, 'output_sections' ] );
		remove_action( 'woocommerce_settings_' . $settings_page->get_id(), [ $settings_page, 'output' ] );
		remove_action( 'woocommerce_settings_save_' . $settings_page->get_id(), [ $settings_page, 'save' ] );
		remove_action( 'woocommerce_admin_field_add_settings_slot', [ $settings_page, 'add_settings_slot' ] );
		remove_filter( 'admin_body_class', [ $settings_page, 'add_modern_settings_body_class' ] );
		remove_filter( 'admin_body_class', [ $settings_page, 'add_body_classes' ], 30 );
		remove_action( 'admin_head', [ $settings_page, 'hide_help_tabs' ] );
		remove_action( 'in_admin_header', [ $settings_page, 'suppress_admin_notices' ], PHP_INT_MAX );
		remove_filter( 'woocommerce_admin_features', [ $settings_page, 'suppress_store_alerts' ], PHP_INT_MAX );
	}

	/**
	 * Create the Checkout settings page replacement.
	 *
	 * @return WC_Settings_Payment_Gateways
	 */
	private function create_checkout_settings_page(): WC_Settings_Payment_Gateways {
		return new class( $this->gateway ) extends WC_Settings_Payment_Gateways {
			/**
			 * WooPayments gateway.
			 *
			 * @var WC_Payment_Gateway_WCPay
			 */
			private $wcpay_gateway;

			/**
			 * Constructor.
			 *
			 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WooPayments gateway.
			 */
			public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway ) {
				$this->wcpay_gateway = $wcpay_gateway;
				parent::__construct();
			}

			/**
			 * Get the modern settings page adapter for WooPayments.
			 *
			 * @return Automattic\WooCommerce\Admin\Settings\ModernSettingsPageInterface|null
			 */
			public function get_modern_settings_page(): ?\Automattic\WooCommerce\Admin\Settings\ModernSettingsPageInterface {
				if ( ! $this->is_wcpay_settings_section() ) {
					return null;
				}

				return new WC_Payments_Modern_Settings_Page_Adapter( $this, $this->wcpay_gateway );
			}

			/**
			 * Add body classes.
			 *
			 * @param string $classes Existing body classes.
			 * @return string
			 */
			public function add_body_classes( $classes ) {
				if ( $this->is_wcpay_settings_section() ) {
					return $classes . ' woocommerce-modern-settings-page woopayments-modern-settings-page';
				}

				return parent::add_body_classes( $classes );
			}

			/**
			 * Output the settings.
			 *
			 * @return void
			 */
			public function output() {
				global $current_section;

				$modern_settings_page = $this->get_modern_settings_page();
				if ( is_object( $modern_settings_page ) ) {
					do_action( 'woocommerce_woocommerce_payments_admin_notices' );

					$script_handles = is_callable( [ $modern_settings_page, 'get_script_handles' ] ) ? call_user_func( [ $modern_settings_page, 'get_script_handles' ], (string) $current_section ) : [];

					foreach ( $script_handles as $script_handle ) {
						if ( is_string( $script_handle ) && '' !== $script_handle ) {
							wp_enqueue_script( $script_handle );
							wp_enqueue_style( $script_handle );
						}
					}

					$GLOBALS['hide_save_button'] = true;

					$this->output_wcpay_modern_settings_header();

					$page_id = is_callable( [ $modern_settings_page, 'get_page_id' ] ) ? (string) call_user_func( [ $modern_settings_page, 'get_page_id' ] ) : 'checkout';

					printf(
						'<div id="%1$s" data-wc-modern-settings="1" data-wc-settings-page="%2$s" data-wc-settings-section="%3$s"></div>',
						esc_attr( 'wc_settings_modern_checkout_' . sanitize_html_class( WC_Payment_Gateway_WCPay::GATEWAY_ID ) ),
						esc_attr( $page_id ),
						esc_attr( WC_Payment_Gateway_WCPay::GATEWAY_ID )
					);
					return;
				}

				parent::output();
			}

			/**
			 * Output the WooPayments modern settings header.
			 *
			 * @return void
			 */
			private function output_wcpay_modern_settings_header() {
				$active_tab = WC_Payments_Modern_Settings_Page_Adapter::get_active_tab();
				$tabs       = WC_Payments_Modern_Settings_Page_Adapter::get_tabs();
				$base_url   = admin_url(
					'admin.php?page=wc-settings&tab=checkout&section=' . WC_Payment_Gateway_WCPay::GATEWAY_ID
				);
				?>
				<div class="woopayments-modern-settings-header">
					<div class="woopayments-modern-settings-header__bar">
						<a class="woopayments-modern-settings-header__back" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>" aria-label="<?php esc_attr_e( 'Back to payments settings', 'woocommerce-payments' ); ?>">
							<span aria-hidden="true">&lsaquo;</span>
						</a>
						<h1><?php esc_html_e( 'WooPayments', 'woocommerce-payments' ); ?></h1>
						<button class="button button-primary woopayments-modern-settings-header__save" type="submit" name="save" value="<?php esc_attr_e( 'Save changes', 'woocommerce-payments' ); ?>">
							<?php esc_html_e( 'Save', 'woocommerce-payments' ); ?>
						</button>
					</div>
					<nav class="woopayments-modern-settings-header__tabs" aria-label="<?php esc_attr_e( 'WooPayments settings', 'woocommerce-payments' ); ?>">
						<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
							<a
								class="<?php echo esc_attr( 'woopayments-modern-settings-header__tab' . ( $active_tab === $tab_id ? ' is-active' : '' ) ); ?>"
								href="<?php echo esc_url( add_query_arg( WC_Payments_Modern_Settings_Page_Adapter::TAB_QUERY_ARG, $tab_id, $base_url ) ); ?>"
								<?php echo $active_tab === $tab_id ? 'aria-current="page"' : ''; ?>
							>
								<?php echo esc_html( $tab_label ); ?>
							</a>
						<?php endforeach; ?>
					</nav>
				</div>
				<?php
			}

			/**
			 * Determine if the current section is WooPayments.
			 *
			 * @return bool
			 */
			private function is_wcpay_settings_section(): bool {
				global $current_tab, $current_section;

				return self::TAB_NAME === $current_tab && WC_Payment_Gateway_WCPay::GATEWAY_ID === $current_section;
			}
		};
	}
}
