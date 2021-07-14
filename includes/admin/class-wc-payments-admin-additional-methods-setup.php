<?php
/**
 * Adds the onboarding steps to the WC home, when necessary.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Admin_Additional_Methods_Setup Class.
 */
class WC_Payments_Admin_Additional_Methods_Setup {
	/**
	 * The WC Pay payment gateway, holding the settings.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * WC_Payments_Admin_Additional_Methods_Setup constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway the WC Pay payment gateway, holding the settings.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		$this->gateway = $gateway;
	}

	/**
	 * Adds the scripts to the WC home, when necessary.
	 */
	public function enqueue_scripts() {
		if ( ! wc_admin_is_registered_page() ) {
			return;
		}

		$available_methods = $this->gateway->get_upe_available_payment_methods();
		if ( empty( $available_methods ) ) {
			return;
		}

		if ( 1 >= count( $available_methods ) ) {
			return;
		}

		// TODO: we also need to check whether giropay/etc are available and whether they have been set up before or not.
		$script_src_url    = plugins_url( 'dist/additional-methods-setup.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/additional-methods-setup.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];
		wp_register_script(
			'WCPAY_ADDITIONAL_METHODS_SETUP',
			$script_src_url,
			$script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/additional-methods-setup.js' ),
			true
		);
		wp_localize_script(
			'WCPAY_ADDITIONAL_METHODS_SETUP',
			'wcpayAdditionalMethodsSetup',
			[
				'isSetupCompleted'            => get_option( 'wcpay_additional_methods_setup_completed', 'no' ),
				'isUpeSettingsPreviewEnabled' => WC_Payments_Features::is_upe_settings_preview_enabled(),
				'isUpeEnabled'                => WC_Payments_Features::is_upe_enabled(),
			]
		);

		wp_register_style(
			'WCPAY_ADDITIONAL_METHODS_SETUP',
			plugins_url( 'dist/additional-methods-setup.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/additional-methods-setup.css' )
		);

		wp_enqueue_script( 'WCPAY_ADDITIONAL_METHODS_SETUP' );
		wp_enqueue_style( 'WCPAY_ADDITIONAL_METHODS_SETUP' );

	}
}
