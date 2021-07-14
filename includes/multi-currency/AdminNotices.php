<?php
/**
 * Admin notices for Multi-Currency.
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that will display admin notices.
 */
class AdminNotices {
	/**
	 * Notices.
	 *
	 * @var array
	 */
	private $notices = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'wp_loaded', [ $this, 'hide_notices' ] );
	}

	/**
	 * Display any notices we've collected thus far.
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->check_for_notices();

		foreach ( $this->notices as $notice_key => $notice ) {
			echo '<div class="' . esc_attr( $notice['class'] ) . '" style="position:relative;">';

			if ( $notice['dismissible'] ) {
				?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wcpay-multi-currency-hide-notice', $notice_key ), 'wcpay_multi_currency_hide_notices_nonce', '_wcpay_multi_currency_notice_nonce' ) ); ?>" class="woocommerce-message-close notice-dismiss" style="position:relative;float:right;padding:9px 0px 9px 9px 9px;text-decoration:none;"></a>
				<?php
			}

			echo '<p>';
			echo wp_kses(
				$notice['message'],
				[
					'a' => [
						'href'   => [],
						'target' => [],
					],
				]
			);
			echo '</p></div>';
		}
	}

	/**
	 * Hides any admin notices.
	 */
	public function hide_notices() {
		if ( isset( $_GET['wcpay-multi-currency-hide-notice'] ) && isset( $_GET['_wcpay_multi_currency_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wcpay_multi_currency_notice_nonce'] ) ), 'wcpay_multi_currency_hide_notices_nonce' ) ) {
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'woocommerce-payments' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'woocommerce-payments' ) );
			}

			$notice = wc_clean( wp_unslash( $_GET['wcpay-multi-currency-hide-notice'] ) );

			if ( 'currency_changed' === $notice ) {
				update_option( 'wcpay_multi_currency_show_store_currency_changed_notice', 'no' );
			}
		}
	}

	/**
	 * Adds admin notice to be displayed.
	 *
	 * @param string $slug        Slug for the notice.
	 * @param string $class       Class(es) for the notice.
	 * @param string $message     Message in the notice.
	 * @param bool   $dismissible Whether the notice can be dismissed or not.
	 */
	private function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = [
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		];
	}

	/**
	 * Checks for notices and add them.
	 */
	private function check_for_notices() {
		$manual_currencies = get_option( 'wcpay_multi_currency_show_store_currency_changed_notice', false );

		if ( is_array( $manual_currencies ) ) {
			$currencies = implode( ', ', $manual_currencies );
			// translators: %s List of currencies that are already translated in WooCommerce core.
			$this->add_admin_notice( 'currency_changed', 'notice notice-warning', sprintf( __( 'The store currency was recently changed. The following currencies are set to manual rates and may need updates: %s', 'woocommerce-payments' ), $currencies ), true );
		}
	}
}
