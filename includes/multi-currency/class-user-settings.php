<?php
/**
 * WooCommerce Payments Multi Currency User Settings
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that add Multi Currency settings to user my account page.
 */
class User_Settings {

	/**
	 * Multi-Currency instance.
	 *
	 * @var Multi_Currency
	 */
	protected $multi_currency;

	/**
	 * Constructor.
	 *
	 * @param Multi_Currency $multi_currency The Multi_Currency instance.
	 */
	public function __construct( Multi_Currency $multi_currency ) {
		$this->multi_currency = $multi_currency;

		add_action( 'woocommerce_edit_account_form', [ $this, 'add_presentment_currency_switch' ] );
		add_action( 'woocommerce_save_account_details', [ $this, 'save_presentment_currency' ] );
	}

	/**
	 * Add a select to allow user choose default currency in `my account > account details`.
	 *
	 * @return void
	 */
	public function add_presentment_currency_switch() {
		?>
		<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
			<label for="wcpay_selected_currency"><?php esc_html_e( 'Default currency', 'woocommerce-payments' ); ?></label>
			<select
				name="wcpay_selected_currency"
				id="wcpay_selected_currency"
			>
				<?php
				foreach ( $this->multi_currency->get_enabled_currencies() as $currency ) {
					$code     = $currency->get_code();
					$symbol   = $currency->get_symbol();
					$selected = $this->multi_currency->get_selected_currency()->code === $code ? ' selected' : '';

					echo "<option value=\"$code\"$selected>$symbol $code</option>"; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</select>
			<span><em><?php esc_html_e( 'Select your preferred currency for shopping and payments.', 'woocommerce-payments' ); ?></em></span>
		</p>
		<div class="clear"></div>
		<?php
	}

	/**
	 * Hook into save account details to capture the new value `wcpay_selected_currency` and persist it.
	 *
	 * @return void
	 */
	public function save_presentment_currency() {
		if ( isset( $_POST['wcpay_selected_currency'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$currency_code = wc_clean( wp_unslash( $_POST['wcpay_selected_currency'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$this->multi_currency->update_selected_currency( $currency_code );
		}
	}

}
