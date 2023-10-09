<?php
/**
 * WooCommerce Payments Multi-Currency Settings
 *
 * @package WooCommerce\Admin
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * MultiCurrency settings placeholder containing a CTA to connect the account.
 */
class SettingsOnboardCta extends \WC_Settings_Page {
	/**
	 * Link to the Multi-Currency documentation page.
	 *
	 * @var string
	 */
	const LEARN_MORE_URL = 'https://woocommerce.com/document/woopayments/currencies/multi-currency-setup/';

	/**
	 * MultiCurrency instance.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

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
		add_action( 'woocommerce_admin_field_wcpay_currencies_settings_onboarding_cta', [ $this, 'currencies_settings_onboarding_cta' ] );
	}

	/**
	 * Output the call to action button if needing to onboard.
	 */
	public function currencies_settings_onboarding_cta() {
		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];
		$href   = admin_url( add_query_arg( $params, 'admin.php' ) );
		?>
			<div>
				<p>
					<?php
						printf(
							/* translators: %s: WooPayments */
							esc_html__( 'To add new currencies to your store, please finish setting up %s.', 'woocommerce-payments' ),
							'WooPayments'
						);
					?>
				</p>
				<a href="<?php echo esc_url( $href ); ?>" id="wcpay_enabled_currencies_onboarding_cta" type="button" class="button-primary">
					<?php esc_html_e( 'Get started', 'woocommerce-payments' ); ?>
				</a>
			</div>
		<?php
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Section being shown.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		// Hide the save button because there are no settings to save.
		global $hide_save_button;
		$hide_save_button = true;

		return [
			[
				'title' => __( 'Enabled currencies', 'woocommerce-payments' ),
				'desc'  => sprintf(
					/* translators: %s: url to documentation. */
					__( 'Accept payments in multiple currencies. Prices are converted based on exchange rates and rounding rules. <a href="%s">Learn more</a>', 'woocommerce-payments' ),
					self::LEARN_MORE_URL
				),
				'type'  => 'title',
				'id'    => $this->id . '_enabled_currencies',
			],
			[
				'type' => 'wcpay_currencies_settings_onboarding_cta',
			],
			[
				'type' => 'sectionend',
				'id'   => $this->id . '_enabled_currencies',
			],
		];
	}
}
