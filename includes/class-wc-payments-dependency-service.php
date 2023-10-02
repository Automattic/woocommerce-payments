<?php
/**
 * WC_Payments_Dependency_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Database_Cache;

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
	const DEV_ASSETS_NOT_BUILT  = 'dev_assets_not_built';

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'admin_notices', [ $this, 'display_admin_notices' ] );
	}

	/**
	 * Checks if all the dependencies needed to run WooPayments are present
	 *
	 * @return bool True if all required dependencies are met.
	 */
	public function has_valid_dependencies() {

		if ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) {
			return true;
		}

		return empty( $this->get_invalid_dependencies( true ) );

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

		// Show a message when assets are not built in a dev build.
		if ( ! $this->are_assets_built() ) {
			WC_Payments::display_admin_error( $this->get_notice_for_invalid_dependency( self::DEV_ASSETS_NOT_BUILT ) );
		}

		$invalid_dependencies = $this->get_invalid_dependencies();

		if ( ! empty( $invalid_dependencies ) ) {
			WC_Payments::display_admin_error( $this->get_notice_for_invalid_dependency( $invalid_dependencies[0] ) );
		}
	}

	/**
	 * Returns an array of invalid dependencies
	 *
	 * @param bool $check_account_connection - if should bypass dependency version validation when an account is connected.
	 *
	 * @return array of invalid dependencies as string constants.
	 */
	public function get_invalid_dependencies( bool $check_account_connection = false ) {

		$invalid_dependencies = [];

		// Either ignore the account connection check or check if there's a cached account connection.
		$ignore_when_account_is_connected = $check_account_connection && $this->has_cached_account_connection();

		if ( ! $this->is_woo_core_active() ) {
			$invalid_dependencies[] = self::WOOCORE_NOT_FOUND;
		}

		if ( ! $ignore_when_account_is_connected && ! $this->is_woo_core_version_compatible() ) {
			$invalid_dependencies[] = self::WOOCORE_INCOMPATIBLE;
		}

		if ( ! $this->is_wc_admin_enabled() ) {
			$invalid_dependencies[] = self::WOOADMIN_NOT_FOUND;
		}

		if ( ! $ignore_when_account_is_connected && ! $this->is_wc_admin_version_compatible() ) {
			$invalid_dependencies[] = self::WOOADMIN_INCOMPATIBLE;
		}

		if ( ! $ignore_when_account_is_connected && ! $this->is_wp_version_compatible() ) {
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
	 * Checks if the version of WooCommerce is compatible with WooPayments.
	 *
	 * @return bool True if WooCommerce version is greater than or equal the minimum accepted
	 */
	public function is_woo_core_version_compatible() {

		$plugin_headers = WC_Payments::get_plugin_headers();
		$wc_version     = $plugin_headers['WCRequires'];

		// Check if the version of WooCommerce is compatible with WooPayments.
		return ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, $wc_version, '>=' ) );
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
	 * Checks if the version of WC Admin is compatible with WooPayments.
	 *
	 * @return bool True if WC Admin version is greater than or equal the minimum accepted
	 */
	public function is_wc_admin_version_compatible() {

		// Check if the version of WooCommerce Admin is compatible with WooPayments.
		return ( defined( 'WC_ADMIN_VERSION_NUMBER' ) && version_compare( WC_ADMIN_VERSION_NUMBER, WCPAY_MIN_WC_ADMIN_VERSION, '>=' ) );
	}

	/**
	 * Checks if the version of WordPress is compatible with WooPayments.
	 *
	 * @return bool True if WordPress version is greater than or equal the minimum accepted
	 */
	public function is_wp_version_compatible() {

		$plugin_headers = WC_Payments::get_plugin_headers();
		$wp_version     = $plugin_headers['RequiresWP'];

		return version_compare( get_bloginfo( 'version' ), $wp_version, '>=' );
	}

	/**
	 * Checks some of the asset files to confirm scripts and styles have been correctly built.
	 *
	 * @return bool TRUE if assets have been built or FALSE otherwise.
	 */
	public function are_assets_built() {
		return ( file_exists( WCPAY_ABSPATH . 'dist/index.js' ) && file_exists( WCPAY_ABSPATH . 'dist/index.css' ) );
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
					sprintf(
						/* translators: %1$s: WooPayments, %2$s: WooCommerce */
						__( '%1$s requires <a>%2$s</a> to be installed and active.', 'woocommerce-payments' ),
						'WooPayments',
						'WooCommerce'
					),
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
						/* translators: %1: WooPayments, %2: current WooCommerce Payment version, %3: WooCommerce, %4: required WC version number, %5: currently installed WC version number */
						__( '%1$s %2$s requires <strong>%3$s %4$s</strong> or greater to be installed (you are using %5$s). ', 'woocommerce-payments' ),
						'WooPayments',
						WCPAY_VERSION_NUMBER,
						'WooCommerce',
						$wc_version,
						WC_VERSION
					),
					[ 'strong' => '<strong>' ]
				);

				if ( current_user_can( 'update_plugins' ) ) {
					// Take the user to the "plugins" screen instead of trying to update WooCommerce inline. WooCommerce adds important information
					// on its plugin row regarding the currently installed extensions and their compatibility with the latest WC version.
					$error_message .= '<br/>' . WC_Payments_Utils::esc_interpolated_html(
						sprintf(
							/* translators: %1$s: WooCommerce, %2$s: WooPayments, a1: link to the Plugins page, a2: link to the page having all previous versions */
							__( '<a1>Update %1$s</a1> <strong>(recommended)</strong> or manually re-install <a2>a previous version</a2> of %2$s.', 'woocommerce-payments' ),
							'WooCommerce',
							'WooPayments'
						),
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
					sprintf(
						/* translators: %1$s: WooPayments, %2$s: WooCommerce Admin */
						__( '%1$s requires %2$s to be enabled. Please remove the <code>woocommerce_admin_disabled</code> filter to use %1$s.', 'woocommerce-payments' ),
						'WooPayments',
						'WooCommerce Admin'
					),
					[ 'code' => '<code>' ]
				);

				break;
			case self::WOOADMIN_INCOMPATIBLE:
				$error_message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %1: WooPayments, %2: WooCommerce Admin, %3: required WC-Admin version number, %4: currently installed WC-Admin version number */
						__( '%1$s requires <strong>%2$s %3$s</strong> or greater to be installed (you are using %4$s).', 'woocommerce-payments' ),
						'WooPayments',
						'WooCommerce Admin',
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
						/* translators: %1: WooPayments, %2: required WP version number, %3: currently installed WP version number */
						__( '%1$s requires <strong>WordPress %2$s</strong> or greater (you are using %3$s).', 'woocommerce-payments' ),
						'WooPayments',
						$wp_version,
						get_bloginfo( 'version' )
					),
					[ 'strong' => '<strong>' ]
				);
				if ( current_user_can( 'update_core' ) ) {
					$error_message .= ' <a href="' . admin_url( 'update-core.php' ) . '">' . __( 'Update WordPress', 'woocommerce-payments' ) . '</a>';
				}
				break;
			case self::DEV_ASSETS_NOT_BUILT:
				$error_message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %s: WooPayments */
						__(
							'You have installed a development version of %s which requires files to be built. From the plugin directory, run <code>npm run build:client</code> to build and minify assets. Alternatively, you can download a pre-built version of the plugin from the <a1>WordPress.org repository</a1> or by visiting the <a2>releases page in the GitHub repository</a2>.',
							'woocommerce-payments'
						),
						'WooPayments'
					),
					[
						'code' => '<code>',
						'a1'   => '<a href="https://wordpress.org/plugins/woocommerce-payments/">',
						'a2'   => '<a href="https://github.com/automattic/woocommerce-payments/releases/">',
					]
				);
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
		return $cur_screen && 'update' === $cur_screen->id && 'plugins' === $cur_screen->parent_base;
	}

	/**
	 * Check if the current WCPay Account has cache data.
	 *
	 * @return bool True if the cache data exists in wp_options.
	 */
	private static function has_cached_account_connection(): bool {
		$account_data = get_option( Database_Cache::ACCOUNT_KEY );
		return isset( $account_data['data'] ) && is_array( $account_data['data'] ) && ! empty( $account_data['data'] );
	}
}
