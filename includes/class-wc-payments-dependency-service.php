<?php
/**
 * WC_Payments_Dependency_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Validates dependencies (core, plugins, versions) for WCPAY
 * Used in the plugin main class for validation.
 */
class WC_Payments_Dependency_Service {

	const WOOCORE_NOT_FOUND     = 'woocore_disabled';
	const WOOCORE_INCOMPATIBLE  = 'woocore_outdated';
	const WOOADMIN_NOT_FOUND    = 'wc_admin_not_found';
	const WOOADMIN_INCOMPATIBLE = 'wc_admin_outdated';
	const WP_INCOMPATIBLE       = 'wp_outdated';

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_filter( 'admin_notices', [ $this, 'display_admin_notices' ] );
	}

	/**
	 * Checks if all the dependencies needed to run WooCommerce Payments are present
	 *
	 * @return bool True if all required dependencies are met.
	 */
	public function has_valid_dependencies() {

		if ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) {
			return true;
		}

		return empty( $this->get_invalid_dependencies() );

	}

	/**
	 * Render admin notices for unmet dependencies. Called on the admin_notices hook.
	 *
	 * @return null.
	 */
	public function display_admin_notices() {

		// Do not show alerts while installing plugins.
		if ( self::is_at_plugin_install_page() ) {
			return;
		}

		$invalid_dependencies = $this->get_invalid_dependencies();

		if ( ! empty( $invalid_dependencies ) ) {
			WC_Payments::display_admin_error( $this->get_notice_for_invalid_dependency( $invalid_dependencies[0] ) );
		}
	}

	/**
	 * Return an array of invalid dependencies
	 *
	 * @return array of invalid dependencies as string constants.
	 */
	public function get_invalid_dependencies( bool $check_account_connection ) {

		$invalid_dependencies = [];
		
		// Either ignore the account connection check or check if there's a cached account connection
		// TODO: maybe there's a better name for this?
		$account_check_result = ! $check_account_connection || ! $this->has_cached_account_connection();

		if ( ! $this->is_woo_core_active() ) {
			$invalid_dependencies[] = self::WOOCORE_NOT_FOUND;
		}

		if ( $account_check_result && ! $this->is_woo_core_version_compatible() ) {
			$invalid_dependencies[] = self::WOOCORE_INCOMPATIBLE;
		}

		if ( ! $this->is_wc_admin_enabled() ) {
			$invalid_dependencies[] = self::WOOADMIN_NOT_FOUND;
		}

		if ( $account_check_result && ! $this->is_wc_admin_version_compatible() ) {
			$invalid_dependencies[] = self::WOOADMIN_INCOMPATIBLE;
		}

		if ( $account_check_result && ! $this->is_wp_version_compatible() ) {
			$invalid_dependencies[] = self::WP_INCOMPATIBLE;
		}

		return $invalid_dependencies;

	}

	/**
	 * Checks if WooCommerce is installed and activated.
	 *
	 * @return bool True if WooCommerce is installed and activated.
	 */
	public function is_woo_core_active() {
		// Check if WooCommerce is installed and active.
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Checks if the version of WooCommerce is compatible with WooCommerce Payments.
	 *
	 * @return bool True if WooCommerce version is greater than or equal the minimum accepted
	 */
	public function is_woo_core_version_compatible() {

		$plugin_headers = WC_Payments::get_plugin_headers();
		$wc_version     = $plugin_headers['WCRequires'];

		// Check if the version of WooCommerce is compatible with WooCommerce Payments.
		if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $wc_version, '<' ) ) {

			/**
			 * If WCPay account is connected, still silently load the plugin.
			 * Can not use $this->$account->is_stripe_connected() as many dependencies are not loaded at this point.
			 *
			 * @since 3.1.0
			 */
			return $this->has_cached_account_connection();
		}
		return true;
	}

	/**
	 * Checks if the WooCommerce version has WooCommerce Admin bundled (WC 4.0+)
	 * but it's disabled using a filter.
	 *
	 * @return bool True if WC Admin is found
	 */
	public function is_wc_admin_enabled() {

		// Check if the current WooCommerce version has WooCommerce Admin bundled (WC 4.0+) but it's disabled using a filter.
		if ( ! defined( 'WC_ADMIN_VERSION_NUMBER' ) || apply_filters( 'woocommerce_admin_disabled', false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if the version of WC Admin is compatible with WooCommerce Payments.
	 *
	 * @return bool True if WC Admin version is greater than or equal the minimum accepted
	 */
	public function is_wc_admin_version_compatible() {

		// Check if the version of WooCommerce Admin is compatible with WooCommerce Payments.
		if ( version_compare( WC_ADMIN_VERSION_NUMBER, WCPAY_MIN_WC_ADMIN_VERSION, '<' ) ) {
			/**
			 * If WCPay account is connected, still silently load the plugin.
			 * Can not use $this->$account->is_stripe_connected() as many dependencies are not loaded at this point.
			 *
			 * @since 3.1.0
			 */
			return $this->has_cached_account_connection();
		}
		return true;
	}

	/**
	 * Checks if the version of WordPress is compatible with WooCommerce Payments.
	 *
	 * @return bool True if WordPress version is greater than or equal the minimum accepted
	 */
	public function is_wp_version_compatible() {

		$plugin_headers = WC_Payments::get_plugin_headers();
		$wp_version     = $plugin_headers['RequiresWP'];

		if ( version_compare( get_bloginfo( 'version' ), $wp_version, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the error constant of an invalid dependency, and transforms it into HTML to be used in an Admin Notice.
	 *
	 * @param string $code - invalid dependency constant.
	 *
	 * @return string HTML to render admin notice for the unmet dependency.
	 */
	private function get_notice_for_invalid_dependency( $code ) {

		$plugin_headers = WC_Payments::get_plugin_headers();
		$wp_version     = $plugin_headers['RequiresWP'];
		$wc_version     = $plugin_headers['WCRequires'];

		$error_message = '';

		switch ( $code ) {
			case self::WOOCORE_NOT_FOUND:
				$error_message = WC_Payments_Utils::esc_interpolated_html(
					__( 'WooCommerce Payments requires <a>WooCommerce</a> to be installed and active.', 'woocommerce-payments' ),
					[ 'a' => '<a href="https://wordpress.org/plugins/woocommerce">' ]
				);

				if ( current_user_can( 'install_plugins' ) ) {
					if ( is_wp_error( validate_plugin( 'woocommerce/woocommerce.php' ) ) ) {
						// WooCommerce is not installed.
						$activate_url  = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
						$activate_text = __( 'Install WooCommerce', 'woocommerce-payments' );
					} else {
						// WooCommerce is installed, so it just needs to be enabled.
						$activate_url  = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ), 'activate-plugin_woocommerce/woocommerce.php' );
						$activate_text = __( 'Activate WooCommerce', 'woocommerce-payments' );
					}
					$error_message .= ' <a href="' . $activate_url . '">' . $activate_text . '</a>';
				}

				break;
			case self::WOOCORE_INCOMPATIBLE:
				$error_message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %1: current WooCommerce Payment version, %2: required WC version number, %3: currently installed WC version number */
						__( 'WooCommerce Payments %1$s requires <strong>WooCommerce %2$s</strong> or greater to be installed (you are using %3$s). ', 'woocommerce-payments' ),
						WCPAY_VERSION_NUMBER,
						$wc_version,
						WC_VERSION
					),
					[ 'strong' => '<strong>' ]
				);

				if ( current_user_can( 'update_plugins' ) ) {
					// Take the user to the "plugins" screen instead of trying to update WooCommerce inline. WooCommerce adds important information
					// on its plugin row regarding the currently installed extensions and their compatibility with the latest WC version.
					$error_message .= '<br/>' . WC_Payments_Utils::esc_interpolated_html(
						/* translators: a1: link to the Plugins page, a2: link to the page having all previous versions */
						__( '<a1>Update WooCommerce</a1> <strong>(recommended)</strong> or manually re-install <a2>a previous version</a2> of WooCommerce Payments.', 'woocommerce-payments' ),
						[

							'a1'     => '<a href="' . admin_url( 'plugins.php' ) . '">',
							'strong' => '<strong>',
							'a2'     => '<a href="https://wordpress.org/plugins/woocommerce-payments/advanced/#download-previous-link" target="_blank">',
						]
					);
				}

				break;
			case self::WOOADMIN_NOT_FOUND:
				$error_message = WC_Payments_Utils::esc_interpolated_html(
					__( 'WooCommerce Payments requires WooCommerce Admin to be enabled. Please remove the <code>woocommerce_admin_disabled</code> filter to use WooCommerce Payments.', 'woocommerce-payments' ),
					[ 'code' => '<code>' ]
				);

				break;
			case self::WOOADMIN_INCOMPATIBLE:
				$error_message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %1: required WC-Admin version number, %2: currently installed WC-Admin version number */
						__( 'WooCommerce Payments requires <strong>WooCommerce Admin %1$s</strong> or greater to be installed (you are using %2$s).', 'woocommerce-payments' ),
						WCPAY_MIN_WC_ADMIN_VERSION,
						WC_ADMIN_VERSION_NUMBER
					),
					[ 'strong' => '<strong>' ]
				);

				// Let's assume for now that any WC-Admin version bundled with WooCommerce will meet our minimum requirements.
				$error_message .= ' ' . __( 'There is a newer version of WooCommerce Admin bundled with WooCommerce.', 'woocommerce-payments' );

				if ( current_user_can( 'deactivate_plugins' ) ) {
					$deactivate_url = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=woocommerce-admin/woocommerce-admin.php' ), 'deactivate-plugin_woocommerce-admin/woocommerce-admin.php' );
					$error_message .= ' <a href="' . $deactivate_url . '">' . __( 'Use the bundled version of WooCommerce Admin', 'woocommerce-payments' ) . '</a>';
				}

				break;
			case self::WP_INCOMPATIBLE:
				$error_message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %1: required WP version number, %2: currently installed WP version number */
						__( 'WooCommerce Payments requires <strong>WordPress %1$s</strong> or greater (you are using %2$s).', 'woocommerce-payments' ),
						$wp_version,
						get_bloginfo( 'version' )
					),
					[ 'strong' => '<strong>' ]
				);
				if ( current_user_can( 'update_core' ) ) {
					$error_message .= ' <a href="' . admin_url( 'update-core.php' ) . '">' . __( 'Update WordPress', 'woocommerce-payments' ) . '</a>';
				}
				break;
		}

		return $error_message;
	}

	/**
	 * Checks if current page is plugin installation process page.
	 *
	 * @return bool True when installing plugin.
	 */
	private static function is_at_plugin_install_page() {
		$cur_screen = get_current_screen();
		return 'update' === $cur_screen->id && 'plugins' === $cur_screen->parent_base;
	}

	/**
	 * Check if the current WCPay Account has cache data.
	 *
	 * @return bool True if the cache data exists in wp_options.
	 */
	private static function has_cached_account_connection(): bool {
		$account_data = get_option( 'wcpay_account_data' );
		return isset( $account_data['account'] ) && is_array( $account_data['account'] );
	}
}
