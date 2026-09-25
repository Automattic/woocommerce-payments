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
	const DEV_ASSETS_NOT_BUILT  = 'dev_assets_not_built';

	/**
	 * Dependencies that prevent the plugin from loading at all. The other
	 * (version) dependencies only produce a warning notice.
	 */
	const BLOCKING_DEPENDENCIES = [
		self::WOOCORE_NOT_FOUND,
		self::WOOADMIN_NOT_FOUND,
	];

	const UPDATE_WC_REQUIREMENT_TRANSIENT = 'wcpay_update_wc_requirement';

	/**
	 * Details of an update offer withheld by gate_plugin_updates() during this request, or null.
	 *
	 * @var array|null
	 */
	private $gated_update;

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'admin_notices', [ $this, 'display_admin_notices' ] );
		add_filter( 'site_transient_update_plugins', [ $this, 'gate_plugin_updates' ] );
		add_action( 'after_plugin_row_' . plugin_basename( WCPAY_PLUGIN_FILE ), [ $this, 'display_gated_update_row_notice' ] );
	}

	/**
	 * Checks if the dependencies needed to load WooPayments are present.
	 * Version incompatibilities do not block loading; they only produce a warning notice.
	 *
	 * @return bool True if all blocking dependencies are met.
	 */
	public function has_valid_dependencies() {

		if ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) {
			return true;
		}

		return empty( $this->get_blocking_dependencies() );
	}

	/**
	 * Returns the invalid dependencies that prevent the plugin from loading.
	 *
	 * @return array of invalid dependencies as string constants.
	 */
	public function get_blocking_dependencies() {
		return array_values( array_intersect( $this->get_invalid_dependencies(), self::BLOCKING_DEPENDENCIES ) );
	}

	/**
	 * Withholds the plugin update offer when the offered version requires a newer
	 * WooCommerce version than the one installed. Mirrors how WordPress core keeps
	 * updates requiring a newer PHP version away from incompatible sites.
	 *
	 * @param mixed $transient Value of the update_plugins site transient.
	 *
	 * @return mixed The (possibly modified) transient value.
	 */
	public function gate_plugin_updates( $transient ) {
		$plugin_file = plugin_basename( WCPAY_PLUGIN_FILE );

		if ( ! is_object( $transient ) || empty( $transient->response[ $plugin_file ] ) || ! defined( 'WC_VERSION' ) ) {
			return $transient;
		}

		$update      = $transient->response[ $plugin_file ];
		$new_version = $update->new_version ?? null;

		if ( ! $new_version ) {
			return $transient;
		}

		$required_wc_version = $this->get_wc_version_required_by( $new_version );

		// Fail open: without a known requirement, leave the offer alone.
		if ( ! $required_wc_version || version_compare( WC_VERSION, $required_wc_version, '>=' ) ) {
			return $transient;
		}

		// Moving the offer to no_update hides the update badge and keeps auto-updates away.
		unset( $transient->response[ $plugin_file ] );
		$transient->no_update[ $plugin_file ] = $update;

		$this->gated_update = [
			'new_version' => $new_version,
			'wc_requires' => $required_wc_version,
		];

		return $transient;
	}

	/**
	 * Returns the "WC requires at least" header of the given released plugin version,
	 * fetched from the WordPress.org plugin repository and cached for 12 hours.
	 *
	 * @param string $version Version of WooPayments to look up.
	 *
	 * @return string|null The minimum required WooCommerce version, or null when unknown.
	 */
	public function get_wc_version_required_by( $version ) {
		$cached = get_transient( self::UPDATE_WC_REQUIREMENT_TRANSIENT );

		if ( is_array( $cached ) && ( $cached['version'] ?? null ) === $version ) {
			return $cached['wc_requires'];
		}

		// Only fetch in admin or cron contexts, where update checks belong.
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return null;
		}

		$response = wp_remote_get(
			'https://plugins.svn.wordpress.org/woocommerce-payments/tags/' . rawurlencode( $version ) . '/woocommerce-payments.php',
			[ 'timeout' => 5 ]
		);

		$wc_requires = null;

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$matched     = preg_match( '/^[ \t\/*#@]*WC requires at least:[ \t]*([0-9][0-9.]*)/mi', wp_remote_retrieve_body( $response ), $matches );
			$wc_requires = $matched ? $matches[1] : null;
		}

		// Cache failures for a shorter time so a transient network error does not stick.
		$expiration = null === $wc_requires ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS;
		set_transient(
			self::UPDATE_WC_REQUIREMENT_TRANSIENT,
			[
				'version'     => $version,
				'wc_requires' => $wc_requires,
			],
			$expiration
		);

		return $wc_requires;
	}

	/**
	 * Renders a plugins-screen row notice when an update offer was withheld
	 * because the site's WooCommerce version is too old. Called on the
	 * after_plugin_row_{$plugin_file} action.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 *
	 * @return void
	 */
	public function display_gated_update_row_notice( $plugin_file ) {
		if ( null === $this->gated_update || ! defined( 'WC_VERSION' ) ) {
			return;
		}

		$colspan = 4;
		if ( function_exists( '_get_list_table' ) ) {
			$colspan = _get_list_table( 'WP_Plugins_List_Table' )->get_column_count();
		}

		$is_active = function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file );

		$message = sprintf(
			/* translators: %1: WooPayments, %2: new WooPayments version number, %3: WooCommerce, %4: WC version required by the new version, %5: currently installed WC version */
			__( '%1$s %2$s is available, but it requires %3$s %4$s or greater (you are using %5$s). Update %3$s to receive new versions of %1$s, including security fixes.', 'woocommerce-payments' ),
			'WooPayments',
			$this->gated_update['new_version'],
			'WooCommerce',
			$this->gated_update['wc_requires'],
			WC_VERSION
		);

		printf(
			'<tr class="plugin-update-tr %1$s" id="%2$s-update" data-slug="%2$s" data-plugin="%3$s"><td colspan="%4$d" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>%5$s</p></div></td></tr>',
			$is_active ? 'active' : 'inactive',
			'woocommerce-payments',
			esc_attr( $plugin_file ),
			(int) $colspan,
			esc_html( $message )
		);
	}

	/**
	 * Render admin notices for unmet dependencies. Called on the admin_notices hook.
	 *
	 * @return void
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

		if ( empty( $invalid_dependencies ) ) {
			return;
		}

		$blocking_dependencies = array_values( array_intersect( $invalid_dependencies, self::BLOCKING_DEPENDENCIES ) );

		if ( ! empty( $blocking_dependencies ) ) {
			WC_Payments::display_admin_error( $this->get_notice_for_invalid_dependency( $blocking_dependencies[0] ) );
			return;
		}

		WC_Payments::display_admin_notice( $this->get_notice_for_invalid_dependency( $invalid_dependencies[0] ), 'notice-warning' );
	}

	/**
	 * Returns an array of invalid dependencies
	 *
	 * @return array of invalid dependencies as string constants.
	 */
	public function get_invalid_dependencies() {

		$invalid_dependencies = [];

		if ( ! $this->is_woo_core_active() ) {
			$invalid_dependencies[] = self::WOOCORE_NOT_FOUND;
		}

		if ( ! $this->is_woo_core_version_compatible() ) {
			$invalid_dependencies[] = self::WOOCORE_INCOMPATIBLE;
		}

		if ( ! $this->is_wc_admin_enabled() ) {
			$invalid_dependencies[] = self::WOOADMIN_NOT_FOUND;
		}

		if ( ! $this->is_wc_admin_version_compatible() ) {
			$invalid_dependencies[] = self::WOOADMIN_INCOMPATIBLE;
		}

		if ( ! $this->is_wp_version_compatible() ) {
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
		if ( ! defined( 'WC_ADMIN_VERSION_NUMBER' ) || apply_filters( 'woocommerce_admin_disabled', false ) ) { // phpcs:ignore WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle -- WooCommerce core hook, not defined by WooPayments.
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
						/* translators: %1: WooPayments, %2: current WooCommerce Payment version, %3: WooCommerce, %4: minimum supported WC version number, %5: currently installed WC version number */
						__( '%1$s %2$s supports <strong>%3$s %4$s</strong> or greater (you are using %5$s). Some features may not work as expected until you update. ', 'woocommerce-payments' ),
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
						/* translators: %1: WooPayments, %2: WooCommerce Admin, %3: minimum supported WC-Admin version number, %4: currently installed WC-Admin version number */
						__( '%1$s supports <strong>%2$s %3$s</strong> or greater (you are using %4$s).', 'woocommerce-payments' ),
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
						/* translators: %1: WooPayments, %2: minimum supported WP version number, %3: currently installed WP version number */
						__( '%1$s supports <strong>WordPress %2$s</strong> or greater (you are using %3$s). Some features may not work as expected until you update.', 'woocommerce-payments' ),
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
}
