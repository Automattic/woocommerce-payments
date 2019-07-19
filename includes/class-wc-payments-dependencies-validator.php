<?php
/**
 * Class WC_Payments_Dependencies_Validator
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class validates that the WordPress site satisfies all of the plugin's requirements. PHP version, WooCommerce installed, etc.
 */
class WC_Payments_Dependencies_Validator {

	const WC_PLUGIN_SLUG = 'woocommerce';
	const WC_PLUGIN_NAME = 'woocommerce/woocommerce.php';
	const WC_PLUGIN_URL  = 'https://wordpress.org/plugins/woocommerce/';

	const WC_ADMIN_PLUGIN_SLUG = 'woocommerce-admin';
	const WC_ADMIN_PLUGIN_NAME = 'woocommerce-admin/woocommerce-admin.php';
	const WC_ADMIN_PLUGIN_URL  = 'https://wordpress.org/plugins/woocommerce-admin/';

	/**
	 * Checks if all the dependencies needed to run this plugin are present
	 *
	 * @param bool $silent True if the function should just return true/false, False if this function should display notice messages for failed dependencies.
	 * @return bool True if all dependencies are met, false otherwise.
	 */
	public function check_plugin_dependencies( $silent ) {
		foreach ( $this->match_plugin_requirements( $this->get_plugin_requirements() ) as $condition ) {
			if ( ! $condition['satisfied'] ) {
				if ( ! $silent ) {
					$this->display_admin_error( call_user_func_array( array( $this, $condition['get_message'] ), $condition['get_message_args'] ) );
				}
				return false;
			}
		}
		return true;
	}

	/**
	 * Parses the readme.txt file, the plugin header, and some plugin-defined global constants to build a list of
	 * all the requirements this plugin has.
	 *
	 * @return array Associative array with the versions of all the requirements.
	 */
	public function get_plugin_requirements() {
		$plugin_headers = get_file_data(
			WCPAY_PLUGIN_FILE,
			array(
				// Mirrors the functionality on WooCommerce core: https://github.com/woocommerce/woocommerce/blob/ff2eadeccec64aa76abd02c931bf607dd819bbf0/includes/wc-core-functions.php#L1916 .
				'WCRequires' => 'WC requires at least',
			)
		);
		$readme_headers = get_file_data(
			WCPAY_ABSPATH . '/readme.txt',
			array(
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
			),
			'plugin'
		);

		return array(
			'wc_version'                   => $plugin_headers['WCRequires'],
			'wp_version'                   => $readme_headers['requires'],
			'php_version'                  => $readme_headers['requires_php'],
			'wc_admin_version'             => WCPAY_WC_ADMIN_VERSION_REQUIRED,
			'wc_min_version_with_wc_admin' => WCPAY_WC_MIN_VERSION_WITH_WC_ADMIN,
		);
	}

	/**
	 * Prints the given message in an "admin notice" wrapper with "error" class.
	 *
	 * @param string $message Message to print. Can contain HTML.
	 */
	private function display_admin_error( $message ) {
		?>
		<div class="notice notice-error">
			<p><?php echo $message; // PHPCS:Ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		</div>
		<?php
	}

	/**
	 * From a given list of version requirements, this function checks every one of them against the current environment.
	 *
	 * @param array $requirements List of required versions, see the get_plugin_requirements method for context.
	 * @return array List of all the requirements. It should be ordered by priority, because only the first failed requirement will be displayed to the merchant.
	 * Each requirement will have the following properties:
	 * - satisfied: true/false Whether the site fulfills this particular requirement or not.
	 * - get_message: Which method of this class should be called to get the error message that should be displayed to the merchant about this failed requirement.
	 * - get_message_args: Array of arguments that will be passed to the get_message function.
	 */
	public function match_plugin_requirements( $requirements ) {
		$has_wc_with_bundled_wc_admin = $requirements['wc_min_version_with_wc_admin'] && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, $requirements['wc_min_version_with_wc_admin'], '>=' );
		return array(
			// Minimum PHP version.
			array(
				'satisfied'        => version_compare( phpversion(), $requirements['php_version'], '>=' ),
				'get_message'      => 'get_update_php_error_message',
				'get_message_args' => array(
					phpversion(),
					$requirements['php_version'],
				),
			),
			// WooCommerce installed & active.
			array(
				'satisfied'        => class_exists( 'WooCommerce' ),
				'get_message'      => 'get_install_woocommerce_error_message',
				'get_message_args' => array(
					current_user_can( 'install_plugins' ),
					function_exists( 'validate_plugin' ) && 0 === validate_plugin( self::WC_PLUGIN_NAME ),
				),
			),
			// Minimum WooCommerce version.
			array(
				'satisfied'        => defined( 'WC_VERSION' ) && version_compare( WC_VERSION, $requirements['wc_version'], '>=' ),
				'get_message'      => 'get_update_woocommerce_error_message',
				'get_message_args' => array(
					defined( 'WC_VERSION' ) ? WC_VERSION : null,
					$requirements['wc_version'],
					$requirements['wc_min_version_with_wc_admin'],
					current_user_can( 'update_plugins' ),
				),
			),
			// Minimum WordPress version.
			array(
				'satisfied'        => version_compare( get_bloginfo( 'version' ), $requirements['wp_version'], '>=' ),
				'get_message'      => 'get_update_wordpress_error_message',
				'get_message_args' => array(
					get_bloginfo( 'version' ),
					$requirements['wp_version'],
					current_user_can( 'update_core' ),
				),
			),
			// WooCommerce Admin installed & active.
			array(
				// This requirement and the next one can be satisfied by installing/updating WC-Admin *or* updating WooCommerce to a version that has WC-Admin bundled in.
				'satisfied'        => $has_wc_with_bundled_wc_admin || defined( 'WC_ADMIN_VERSION_NUMBER' ),
				'get_message'      => 'get_install_wc_admin_error_message',
				'get_message_args' => array(
					defined( 'WC_VERSION' ) ? WC_VERSION : null,
					$requirements['wc_min_version_with_wc_admin'],
					current_user_can( 'install_plugins' ),
					function_exists( 'validate_plugin' ) && 0 === validate_plugin( self::WC_ADMIN_PLUGIN_NAME ),
				),
			),
			// Minimum WooCommerce Admin version.
			array(
				'satisfied'        => $has_wc_with_bundled_wc_admin || ( defined( 'WC_ADMIN_VERSION_NUMBER' ) && version_compare( WC_ADMIN_VERSION_NUMBER, $requirements['wc_admin_version'], '>=' ) ),
				'get_message'      => 'get_update_wc_admin_error_message',
				'get_message_args' => array(
					defined( 'WC_ADMIN_VERSION_NUMBER' ) ? WC_ADMIN_VERSION_NUMBER : null,
					$requirements['wc_admin_version'],
					defined( 'WC_VERSION' ) ? WC_VERSION : null,
					$requirements['wc_min_version_with_wc_admin'],
					current_user_can( 'update_plugins' ),
				),
			),
		);
	}

	/**
	 * Build the error message that will be displayed to the merchant if the site is running a version of PHP that's too old.
	 *
	 * @param string $current_php_version PHP version the site is using.
	 * @param string $required_php_version Required PHP version for the plugin to run.
	 * @return string Error message. Can contain HTML.
	 */
	public function get_update_php_error_message( $current_php_version, $required_php_version ) {
		return sprintf(
			/* translators: %1: required PHP version number, %2: currently installed PHP version number */
			__( 'WooCommerce Payments requires <strong>PHP %1$s</strong> or greater (you are using %2$s).', 'woocommerce-payments' ),
			$required_php_version,
			$current_php_version
		);
	}

	/**
	 * Build the error message that will be displayed to the merchant if the site doesn't have WooCommerce installed.
	 *
	 * @param boolean $can_install_plugin Whether the current user can install plugins or not.
	 * @param boolean $is_installed Whether WooCommerce is installed (but deactivated), or not installed at all.
	 * @return string Error message. Can contain HTML.
	 */
	public function get_install_woocommerce_error_message( $can_install_plugin, $is_installed ) {
		$message = sprintf(
			/* translators: %1: WooCommerce plugin URL */
			__( 'WooCommerce Payments requires <a href="%1$s">WooCommerce</a> to be installed and active.', 'woocommerce-payments' ),
			self::WC_PLUGIN_URL
		);

		if ( $can_install_plugin ) {
			if ( $is_installed ) {
				// The plugin is installed, so it just needs to be enabled.
				$activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . self::WC_PLUGIN_NAME ), 'activate-plugin_' . self::WC_PLUGIN_NAME );
				$message     .= ' <a href="' . $activate_url . '">' . __( 'Activate WooCommerce', 'woocommerce-payments' ) . '</a>';
			} else {
				// The plugin is not installed.
				$activate_url = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . self::WC_PLUGIN_SLUG ), 'install-plugin_' . self::WC_PLUGIN_SLUG );
				$message     .= ' <a href="' . $activate_url . '">' . __( 'Install WooCommerce', 'woocommerce-payments' ) . '</a>';
			}
		}

		return $message;
	}

	/**
	 * Build the error message that will be displayed to the merchant if the site has a version of WooCommerce that's too old.
	 *
	 * @param string       $current_wc_version WooCommerce version the site is using.
	 * @param string       $required_wc_version Required WooCommerce version for the plugin to run.
	 * @param string|false $recommended_wc_version Recommenced WooCommerce version. It will be the version that has WC-Admin built-in, or False if there's no WooCommerce version that includes WC-Admin yet.
	 * @param boolean      $can_update_plugin Whether the current user can update plugins or not.
	 * @return string Error message. Can contain HTML.
	 */
	public function get_update_woocommerce_error_message( $current_wc_version, $required_wc_version, $recommended_wc_version, $can_update_plugin ) {
		$message = sprintf(
			/* translators: %1: required WC version number, %2: currently installed WC version number */
			__( 'WooCommerce Payments requires <strong>WooCommerce %1$s</strong> or greater to be installed (you are using %2$s).', 'woocommerce-payments' ),
			$required_wc_version,
			$current_wc_version
		);

		if ( $recommended_wc_version && $recommended_wc_version !== $required_wc_version ) {
			$message .= ' ' . sprintf(
				/* translators: %1: recommended WC version number */
				__( '<strong>WooCommerce %1$s</strong> or greater is recommended, since it includes all the functionality from the WooCommerce Admin plugin.', 'woocommerce-payments' ),
				$recommended_wc_version
			);
		}

		if ( $can_update_plugin ) {
			// Take the user to the "plugins" screen instead of trying to update WooCommerce inline. WooCommerce adds important information
			// on its plugin row regarding the currently installed extensions and their compatibility with the latest WC version.
			$message .= ' <a href="' . admin_url( 'plugins.php' ) . '">' . __( 'Update WooCommerce', 'woocommerce-payments' ) . '</a>';
		}

		return $message;
	}

	/**
	 * Build the error message that will be displayed to the merchant if the site has a version of WordPress that's too old.
	 *
	 * @param string  $current_wp_version WordPress version the site is using.
	 * @param string  $required_wp_version Required WordPress version for the plugin to run.
	 * @param boolean $can_update_wp Whether the current user can update WordPress or not.
	 * @return string Error message. Can contain HTML.
	 */
	public function get_update_wordpress_error_message( $current_wp_version, $required_wp_version, $can_update_wp ) {
		$message = sprintf(
			/* translators: %1: required WP version number, %2: currently installed WP version number */
			__( 'WooCommerce Payments requires <strong>WordPress %1$s</strong> or greater (you are using %2$s).', 'woocommerce-payments' ),
			$required_wp_version,
			$current_wp_version
		);

		if ( $can_update_wp ) {
			$message .= ' <a href="' . admin_url( 'update-core.php' ) . '">' . __( 'Update WordPress', 'woocommerce-payments' ) . '</a>';
		}

		return $message;
	}

	/**
	 * Build the error message that will be displayed to the merchant if the site doesn't have WC-Admin installed.
	 * If there's a version of WooCommerce that already has WC-Admin built-in, we will recommend the merchant to update WooCommerce instead of installing WC-Admin.
	 *
	 * @param string       $current_wc_version WooCommerce version the site is using.
	 * @param string|false $wc_min_version_with_wc_admin Minimum WooCommerce version that already includes WC-Admin. False if there's no WooCommerce version that includes WC-Admin yet.
	 * @param boolean      $can_install_plugin Whether the current user can install plugins or not.
	 * @param boolean      $is_installed Whether WC-Admin is installed (but deactivated), or not installed at all.
	 * @return string Error message. Can contain HTML.
	 */
	public function get_install_wc_admin_error_message( $current_wc_version, $wc_min_version_with_wc_admin, $can_install_plugin, $is_installed ) {
		if ( $wc_min_version_with_wc_admin ) {
			$message_wc       = $this->get_update_woocommerce_error_message( $current_wc_version, $wc_min_version_with_wc_admin, null, $can_install_plugin );
			$message_wc_admin = sprintf(
				/* translators: %1: WooCommerce Admin plugin URL */
				__( 'If updating WooCommerce at this moment is inconvenient, you can also use <a href="%1$s">WooCommerce Admin</a> to start using WooCommerce Payments.', 'woocommerce-payments' ),
				self::WC_ADMIN_PLUGIN_URL
			);
		} else {
			$message_wc_admin = sprintf(
				/* translators: %1: WooCommerce Admin plugin URL */
				__( 'WooCommerce Payments requires <a href="%1$s">WooCommerce Admin</a> to be installed and active.', 'woocommerce-payments' ),
				self::WC_ADMIN_PLUGIN_URL
			);
		}

		if ( $can_install_plugin ) {
			if ( $is_installed ) {
				// The plugin is installed, so it just needs to be enabled.
				$activate_url      = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . self::WC_ADMIN_PLUGIN_NAME ), 'activate-plugin_' . self::WC_ADMIN_PLUGIN_NAME );
				$message_wc_admin .= ' <a href="' . $activate_url . '">' . __( 'Activate WooCommerce Admin', 'woocommerce-payments' ) . '</a>';
			} else {
				// The plugin is not installed.
				$activate_url      = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . self::WC_ADMIN_PLUGIN_SLUG ), 'install-plugin_' . self::WC_ADMIN_PLUGIN_SLUG );
				$message_wc_admin .= ' <a href="' . $activate_url . '">' . __( 'Install WooCommerce Admin', 'woocommerce-payments' ) . '</a>';
			}
		}

		return $wc_min_version_with_wc_admin ? ( $message_wc . '<br/>' . $message_wc_admin ) : $message_wc_admin;
	}

	/**
	 * Build the error message that will be displayed to the merchant if the site has an WC-Admin version that's too old.
	 * If there's a version of WooCommerce that already has WC-Admin built-in, we will recommend the merchant to update WooCommerce instead of updating WC-Admin.
	 *
	 * @param string       $current_wc_admin_version WC-Admin version the site is using.
	 * @param string       $required_wc_admin_version Required WC-Admin version for the plugin to run.
	 * @param string       $current_wc_version WooCommerce version the site is using.
	 * @param string|false $wc_min_version_with_wc_admin Minimum WooCommerce version that already includes WC-Admin. False if there's no WooCommerce version that includes WC-Admin yet.
	 * @param boolean      $can_update_plugin Whether the current user can update plugins or not.
	 * @return string Error message. Can contain HTML.
	 */
	public function get_update_wc_admin_error_message( $current_wc_admin_version, $required_wc_admin_version, $current_wc_version, $wc_min_version_with_wc_admin, $can_update_plugin ) {
		if ( $wc_min_version_with_wc_admin ) {
			$message_wc       = $this->get_update_woocommerce_error_message( $current_wc_version, $wc_min_version_with_wc_admin, null, $can_update_plugin );
			$message_wc_admin = __( 'If updating WooCommerce at this moment is inconvenient, you can also update <strong>WooCommerce Admin</strong> to start using WooCommerce Payments.', 'woocommerce-payments' );
		} else {
			$message_wc_admin = sprintf(
				/* translators: %1: required WC-Admin version number, %2: currently installed WC-Admin version number */
				__( 'WooCommerce Payments requires <strong>WooCommerce Admin %1$s</strong> or greater to be installed (you are using %2$s).', 'woocommerce-payments' ),
				$required_wc_admin_version,
				$current_wc_admin_version
			);
		}

		if ( $can_update_plugin ) {
			$update_url        = wp_nonce_url( admin_url( 'update.php?action=upgrade-plugin&plugin=' . self::WC_ADMIN_PLUGIN_NAME ), 'upgrade-plugin_' . self::WC_ADMIN_PLUGIN_NAME );
			$message_wc_admin .= ' <a href="' . $update_url . '">' . __( 'Update WooCommerce Admin', 'woocommerce-payments' ) . '</a>';
		}

		return $wc_min_version_with_wc_admin ? ( $message_wc . '<br/>' . $message_wc_admin ) : $message_wc_admin;
	}
}
