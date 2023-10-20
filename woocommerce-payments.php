<?php
/**
 * Plugin Name: WooCommerce Payments
 * Plugin URI: https://woocommerce.com/payments/
 * Description: Accept payments via credit card. Manage transactions within WordPress.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Woo: 5278104:bf3cf30871604e15eec560c962593c1f
 * Text Domain: woocommerce-payments
 * Domain Path: /languages
 * WC requires at least: 7.6
 * WC tested up to: 7.8.0
 * Requires at least: 6.0
 * Requires PHP: 7.3
 * Version: 6.6.2
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPAY_PLUGIN_FILE', __FILE__ );
define( 'WCPAY_ABSPATH', __DIR__ . '/' );
define( 'WCPAY_MIN_WC_ADMIN_VERSION', '0.23.2' );
define( 'WCPAY_SUBSCRIPTIONS_ABSPATH', __DIR__ . '/vendor/woocommerce/subscriptions-core/' );

require_once __DIR__ . '/vendor/autoload_packages.php';
require_once __DIR__ . '/includes/class-wc-payments-features.php';
require_once __DIR__ . '/includes/woopay-user/class-woopay-extension.php';
require_once __DIR__ . '/includes/woopay/class-woopay-session.php';

/**
 * Plugin activation hook.
 */
function wcpay_activated() {
	// When WooCommerce Payments is installed and activated from the WooCommerce onboarding wizard (via wc-admin REST request), check if the site is eligible for subscriptions.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		update_option( 'wcpay_check_subscriptions_eligibility_after_onboarding', true );
		return;
	}

	if (
		// Only redirect to onboarding when activated on its own. Either with a link...
		isset( $_GET['action'] ) && 'activate' === $_GET['action'] // phpcs:ignore WordPress.Security.NonceVerification
		// ...or with a bulk action.
		|| isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) && 1 === count( $_POST['checked'] ) // phpcs:ignore WordPress.Security.NonceVerification
	) {
		update_option( 'wcpay_should_redirect_to_onboarding', true );
	}
}

/**
 * Plugin deactivation hook.
 */
function wcpay_deactivated() {
	require_once WCPAY_ABSPATH . '/includes/class-wc-payments.php';
	WC_Payments::remove_woo_admin_notes();
}

register_activation_hook( __FILE__, 'wcpay_activated' );
register_deactivation_hook( __FILE__, 'wcpay_deactivated' );

// The JetPack autoloader might not catch up yet when activating the plugin. If so, we'll stop here to avoid JetPack connection failures.
$is_autoloading_ready = class_exists( Automattic\Jetpack\Connection\Rest_Authentication::class );
if ( ! $is_autoloading_ready ) {
	return;
}

// Subscribe to automated translations.
add_filter( 'woocommerce_translations_updates_for_woocommerce-payments', '__return_true' );

/**
 * Initialize the Jetpack functionalities: connection, identity crisis, etc.
 */
function wcpay_jetpack_init() {
	if ( ! wcpay_check_old_jetpack_version() ) {
		return;
	}
	$jetpack_config = new Automattic\Jetpack\Config();
	$jetpack_config->ensure(
		'connection',
		[
			'slug' => 'woocommerce-payments',
			'name' => 'WooPayments',
		]
	);
	$jetpack_config->ensure(
		'identity_crisis',
		[
			'slug'          => 'woocommerce-payments',
			'customContent' => wcpay_get_jetpack_idc_custom_content(),
			'logo'          => plugins_url( 'assets/images/logo.svg', WCPAY_PLUGIN_FILE ),
			'admin_page'    => '/wp-admin/admin.php?page=wc-admin',
			'priority'      => 5,
		]
	);

	// When only WooPayments is active, minimize the data to send back to WPcom for supporting Woo Mobile apps.
	$jetpack_config->ensure(
		'sync',
		array_merge_recursive(
			\Automattic\Jetpack\Sync\Data_Settings::MUST_SYNC_DATA_SETTINGS,
			[
				'jetpack_sync_modules'           =>
					[
						'Automattic\\Jetpack\\Sync\\Modules\\Options',
						'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync',
					],
				'jetpack_sync_options_whitelist' =>
					[
						'active_plugins',
						'blogdescription',
						'blogname',
						'timezone_string',
						'gmt_offset',
					],
			]
		)
	);

	// Trigger the first Jetpack full-sync when updating from old WCPay versions,
	// which do not have Jetpack Sync package.
	add_action(
		'woocommerce_woocommerce_payments_updated',
		function () {
			$version_check = version_compare( '3.8.0', get_option( 'woocommerce_woocommerce_payments_version' ), '>' );
			$method_check  = method_exists( '\Automattic\Jetpack\Sync\Actions', 'do_only_first_initial_sync' );
			if ( $version_check && $method_check ) {
				\Automattic\Jetpack\Sync\Actions::do_only_first_initial_sync();
			}
		}
	);
}
// Jetpack's Rest_Authentication needs to be initialized even before plugins_loaded.
Automattic\Jetpack\Connection\Rest_Authentication::init();

// Jetpack-config will initialize the modules on "plugins_loaded" with priority 2, so this code needs to be run before that.
add_action( 'plugins_loaded', 'wcpay_jetpack_init', 1 );

/**
 * Initialize the extension. Note that this gets called on the "plugins_loaded" filter,
 * so WooCommerce classes are guaranteed to exist at this point (if WooCommerce is enabled).
 */
function wcpay_init() {
	require_once WCPAY_ABSPATH . '/includes/class-wc-payments.php';
	WC_Payments::init();
	/**
	 * Needs to be loaded as soon as possible
	 * Check https://github.com/Automattic/woocommerce-payments/issues/4759
	 */
	\WCPay\WooPay\WooPay_Session::init();
}

// Make sure this is run *after* WooCommerce has a chance to initialize its packages (wc-admin, etc). That is run with priority 10.
// If you change the priority of this action, you'll need to change it in the wcpay_check_old_jetpack_version function too.
add_action( 'plugins_loaded', 'wcpay_init', 11 );

if ( ! function_exists( 'wcpay_init_subscriptions_core' ) ) {

	/**
	 * Initialise subscriptions-core if WC Subscriptions (the plugin) isn't loaded
	 */
	function wcpay_init_subscriptions_core() {
		if ( ! class_exists( 'WooCommerce' ) || ! WC_Payments_Features::is_wcpay_subscriptions_enabled() ) {
			return;
		}

		$is_plugin_active = function( $plugin_name ) {
			$plugin_slug = "$plugin_name/$plugin_name.php";

			// Check if the specified $plugin_name is in the process of being activated via the Admin > Plugins screen.
			if ( isset( $_REQUEST['action'], $_REQUEST['_wpnonce'] ) && current_user_can( 'activate_plugin', $plugin_slug ) ) {
				$action            = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
				$activating_plugin = '';

				switch ( $action ) {
					case 'activate':
					case 'activate-plugin':
						if ( isset( $_REQUEST['plugin'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wpnonce'] ) ), "activate-plugin_{$plugin_slug}" ) ) {
							$activating_plugin = sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) );
						}
						break;
					case 'activate-selected':
						// When multiple plugins are being activated at once the $_REQUEST['checked'] is an array of plugin slugs. Check if the specified $plugin_name is in that array.
						if ( isset( $_REQUEST['checked'] ) && is_array( $_REQUEST['checked'] ) && in_array( $plugin_slug, $_REQUEST['checked'], true ) && wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-plugins' ) ) {
							$activating_plugin = $plugin_slug;
						}
						break;
				}

				if ( ! empty( $activating_plugin ) && $plugin_slug === $activating_plugin ) {
					return true;
				}
			}

			// Check if specified $plugin_name is in the process of being activated via the WP CLI.
			if ( defined( 'WP_CLI' ) && WP_CLI && isset( $GLOBALS['argv'] ) ) {
				$expected_arguments = [
					'plugin',
					'activate',
					$plugin_name,
				];
				if ( array_intersect( $expected_arguments, $GLOBALS['argv'] ) === $expected_arguments ) {
					return true;
				}
			}

			// Check if specified $plugin_name is active on a multisite installation via site wide plugins.
			if ( is_multisite() ) {
				$plugins = get_site_option( 'active_sitewide_plugins' );
				if ( isset( $plugins[ $plugin_slug ] ) ) {
					return true;
				}
			}

			// Finally check if specified $plugin_name is active.
			if ( class_exists( 'Automattic\WooCommerce\Admin\PluginsHelper' ) ) {
				return Automattic\WooCommerce\Admin\PluginsHelper::is_plugin_active( $plugin_slug );
			} else {
				if ( ! function_exists( 'is_plugin_active' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				return is_plugin_active( $plugin_slug );
			}
		};

		$is_subscriptions_active = $is_plugin_active( 'woocommerce-subscriptions' );
		$is_wcs_core_active      = $is_plugin_active( 'woocommerce-subscriptions-core' );
		$wcs_core_path           = $is_wcs_core_active ? WP_PLUGIN_DIR . '/woocommerce-subscriptions-core/' : WCPAY_SUBSCRIPTIONS_ABSPATH;

		/**
		 * If the current request is to activate subscriptions, don't load the subscriptions-core package.
		 *
		 * WP loads the newly activated plugin's base file later than `plugins_loaded`, and so there's no opportunity for us to not load our core feature set on a consistent hook.
		 * We also cannot init subscriptions core too late, because if we do, we miss hooks that register the subscription post types etc.
		 */
		if ( $is_subscriptions_active ) {
			return;
		}

		require_once $wcs_core_path . 'includes/class-wc-subscriptions-core-plugin.php';
		new WC_Subscriptions_Core_Plugin();
	}
}
add_action( 'plugins_loaded', 'wcpay_init_subscriptions_core', 0 );

/**
 * Check if WCPay is installed alongside an old version of Jetpack (8.1 or earlier). Due to the autoloader code in those old
 * versions, the Jetpack Config initialization code would just crash the site.
 * TODO: Remove this when Jetpack 8.1 (Released on January 2020) is so old we don't think anyone will run into this problem anymore.
 *
 * @return bool True if the plugin can keep initializing itself, false otherwise.
 */
function wcpay_check_old_jetpack_version() {
	if ( defined( 'JETPACK__VERSION' ) && version_compare( JETPACK__VERSION, '8.2', '<' ) && JETPACK__VERSION !== 'wpcom' ) {
		add_filter( 'admin_notices', 'wcpay_show_old_jetpack_notice' );
		// Prevent the rest of the plugin from initializing.
		remove_action( 'plugins_loaded', 'wcpay_init', 11 );
		return false;
	}
	return true;
}

/**
 * Display an error notice if the installed Jetpack version is too old to even start initializing the plugin.
 */
function wcpay_show_old_jetpack_notice() {
	?>
	<div class="notice wcpay-notice notice-error">
		<p><b>WooPayments</b></p>
		<p>
			<?php
				echo sprintf(
					/* translators: %1 WooPayments. */
					esc_html( __( 'The version of Jetpack installed is too old to be used with %1$s. %1$s has been disabled. Please deactivate or update Jetpack.', 'woocommerce-payments' ) ),
					'WooPayments'
				);
			?>
		</p>
	</div>
	<?php
}

/**
 * Get custom texts for Jetpack Identity Crisis (IDC) module.
 *
 * @return array
 */
function wcpay_get_jetpack_idc_custom_content(): array {
	$custom_content = [
		'headerText'                => __( 'Safe Mode', 'woocommerce-payments' ),
		'mainTitle'                 => __( 'Safe Mode activated', 'woocommerce-payments' ),
		'mainBodyText'              => sprintf(
			/* translators: %s: WooPayments. */
			__( 'We’ve detected that you have duplicate sites connected to %s. When Safe Mode is active, payments will not be interrupted. However, some features may not be available until you’ve resolved this issue below. Safe Mode is most frequently activated when you’re transferring your site from one domain to another, or creating a staging site for testing. <safeModeLink>Learn more</safeModeLink>', 'woocommerce-payments' ),
			'WooPayments'
		),
		'migratedTitle'             => sprintf(
			/* translators: %s: WooPayments. */
			__( '%s connection successfully transferred', 'woocommerce-payments' ),
			'WooPayments'
		),
		'migratedBodyText'          => sprintf(
			/* translators: %s: WooPayments. */
			__( 'Safe Mode has been deactivated and %s is fully functional.', 'woocommerce-payments' ),
			'WooPayments'
		),
		'migrateCardTitle'          => __( 'Transfer connection', 'woocommerce-payments' ),
		'migrateButtonLabel'        => __( 'Transfer your connection', 'woocommerce-payments' ),
		'startFreshCardTitle'       => __( 'Create a new connection', 'woocommerce-payments' ),
		'startFreshButtonLabel'     => __( 'Create a new connection', 'woocommerce-payments' ),
		'nonAdminTitle'             => __( 'Safe Mode activated', 'woocommerce-payments' ),
		'nonAdminBodyText'          => sprintf(
			/* translators: %s: WooPayments. */
			__( 'We’ve detected that you have duplicate sites connected to %s. When Safe Mode is active, payments will not be interrupted. However, some features may not be available until you’ve resolved this issue below. Safe Mode is most frequently activated when you’re transferring your site from one domain to another, or creating a staging site for testing. A site adminstrator can resolve this issue. <safeModeLink>Learn more</safeModeLink>', 'woocommerce-payments' ),
			'WooPayments'
		),
		'supportURL'                => 'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/safe-mode/',
		'adminBarSafeModeLabel'     => sprintf(
			/* translators: %s: WooPayments. */
			__( '%s Safe Mode', 'woocommerce-payments' ),
			'WooPayments'
		),
		'dynamicSiteUrlText'        => sprintf(
			/* translators: %s: WooPayments. */
			__( "<strong>Notice:</strong> It appears that your 'wp-config.php' file might be using dynamic site URL values. Dynamic site URLs could cause %s to enter Safe Mode. <dynamicSiteUrlSupportLink>Learn how to set a static site URL.</dynamicSiteUrlSupportLink>", 'woocommerce-payments' ),
			'WooPayments'
		),
		'dynamicSiteUrlSupportLink' => 'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/safe-mode/#dynamic-site-urls',
	];

	$urls = Automattic\Jetpack\Identity_Crisis::get_mismatched_urls();
	if ( false !== $urls ) {
		$current_url = untrailingslashit( $urls['current_url'] );
		$wpcom_url   = untrailingslashit( $urls['wpcom_url'] );

		$custom_content['migrateCardBodyText'] = sprintf(
			/* translators: %1$s: The current site domain name. %2$s: The original site domain name. Please keep hostname tags in your translation so that they can be formatted properly. %3$s: WooPayments. */
			__(
				'Transfer your %3$s connection from <hostname>%2$s</hostname> to this site <hostname>%1$s</hostname>. <hostname>%2$s</hostname> will be disconnected from %3$s.',
				'woocommerce-payments'
			),
			$current_url,
			$wpcom_url,
			'WooPayments'
		);

		$custom_content['startFreshCardBodyText'] = sprintf(
			/* translators: %1$s: The current site domain name. %2$s: The original site domain name. Please keep hostname tags in your translation so that they can be formatted properly. %3$s: WooPayments. */
			__(
				'Create a new connection to %3$s for <hostname>%1$s</hostname>. You’ll have to re-verify your business details to begin accepting payments. Your <hostname>%2$s</hostname> connection will remain as is.',
				'woocommerce-payments'
			),
			$current_url,
			$wpcom_url,
			'WooPayments'
		);
	}

	return $custom_content;
}

/**
 * Initialize WC_Payments tasks. This exists outside of wcpay_init()
 * to ensure hooks run in time to be included in WooCommerce TaskLists.
 *
 * Note that this gets called on the "plugins_loaded" filter,
 * so WooCommerce classes are guaranteed to exist at this point (if WooCommerce is enabled).
 */
function wcpay_tasks_init() {
	if ( class_exists( 'Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task' ) ) {
		include_once WCPAY_ABSPATH . '/includes/class-wc-payments-tasks.php';
		WC_Payments_Tasks::init();
	}
}

add_action( 'plugins_loaded', 'wcpay_tasks_init' );

/**
 * Register blocks extension for woopay.
 */
function register_woopay_extension() {
	( new WooPay_Extension() )->register_extend_rest_api_update_callback();
}

add_action( 'woocommerce_blocks_loaded', 'register_woopay_extension' );

/**
 * As the class is defined in later versions of WC, Psalm infers error.
 *
 * @psalm-suppress UndefinedClass
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'woocommerce-payments/woocommerce-payments.php', true );
		}
	}
);
