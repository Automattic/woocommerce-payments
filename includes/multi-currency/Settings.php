<?php
/**
 * WooCommerce Payments Multi-currency Settings
 *
 * @package WooCommerce\Admin
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class Settings extends \WC_Settings_Page {

	/**
	 * The id of the plugin.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * The tab label.
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Instance of MultiCurrency class.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;
		$this->id             = $this->multi_currency->id;
		$this->label          = _x( 'Multi-currency', 'Settings tab label', 'woocommerce-payments' );

		parent::__construct();
	}

	/**
	 * Initializes this class' WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// TODO: Only register emoji script in settings page. Until WC Admin decide if they will enable it too: https://github.com/woocommerce/woocommerce-admin/issues/6388.
		add_action( 'admin_print_scripts', [ $this, 'maybe_add_print_emoji_detection_script' ] );
		add_action( 'woocommerce_admin_field_wcpay_multi_currency_settings_page', [ $this, 'wcpay_multi_currency_settings_page' ] );
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Section being shown.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		return [
			[
				'type' => 'wcpay_multi_currency_settings_page',
			],
		];
	}

	/**
	 * Output container for enabled currencies list.
	 */
	public function wcpay_multi_currency_settings_page() {
		// Hide original save button.
		$GLOBALS['hide_save_button'] = true;
		?>
			<div id="wcpay_multi_currency_settings_container" aria-describedby="wcpay_multi_currency_settings_container-description"></div>
		<?php
	}

	/**
	 * Load inline Emoji detection script on multi-currency settings page
	 */
	public function maybe_add_print_emoji_detection_script() {
		if ( WC_Payments_Multi_Currency()->is_multi_currency_settings_page() ) {
			print_emoji_detection_script();
		}
	}
}
