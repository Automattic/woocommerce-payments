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
 * WC requires at least: 4.0
 * WC tested up to: 5.1
 * Requires WP: 5.5
 * Version: 2.5.0
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPAY_PLUGIN_FILE', __FILE__ );
define( 'WCPAY_ABSPATH', __DIR__ . '/' );
define( 'WCPAY_MIN_WC_ADMIN_VERSION', '0.23.2' );

require_once __DIR__ . '/vendor/autoload_packages.php';

/**
 * Plugin activation hook.
 */
function wcpay_activated() {
	// Do not take any action if activated in a REST request (via wc-admin).
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
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
$is_autoloading_ready = class_exists( Automattic\Jetpack\Connection\Rest_Authentication::class ) && class_exists( MyCLabs\Enum\Enum::class );
if ( ! $is_autoloading_ready ) {
	return;
}

// Subscribe to automated translations.
add_filter( 'woocommerce_translations_updates_for_woocommerce-payments', '__return_true' );

/**
 * Initialize the Jetpack connection functionality.
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
			'name' => __( 'WooCommerce Payments', 'woocommerce-payments' ),
		]
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
}

// Make sure this is run *after* WooCommerce has a chance to initialize its packages (wc-admin, etc). That is run with priority 10.
// If you change the priority of this action, you'll need to change it in the wcpay_check_old_jetpack_version function too.
add_action( 'plugins_loaded', 'wcpay_init', 11 );

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
		<p><b><?php echo esc_html( __( 'WooCommerce Payments', 'woocommerce-payments' ) ); ?></b></p>
		<p><?php echo esc_html( __( 'The version of Jetpack installed is too old to be used with WooCommerce Payments. WooCommerce Payments has been disabled. Please deactivate or update Jetpack.', 'woocommerce-payments' ) ); ?></p>
	</div>
	<?php
}
