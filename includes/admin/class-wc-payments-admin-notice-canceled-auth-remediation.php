<?php
/**
 * Admin notice for canceled authorization fee remediation.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Admin_Notice_Canceled_Auth_Remediation
 *
 * Displays an admin notice to merchants who may be affected by the canceled
 * authorization analytics bug, prompting them to run the remediation tool.
 */
class WC_Payments_Admin_Notice_Canceled_Auth_Remediation {

	/**
	 * Option key for tracking if notice has been dismissed.
	 */
	const NOTICE_DISMISSED_OPTION = 'wcpay_canceled_auth_remediation_notice_dismissed';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'admin_notices', [ $this, 'maybe_show_notice' ] );
		add_action( 'wp_ajax_wcpay_dismiss_canceled_auth_notice', [ $this, 'dismiss_notice' ] );
	}

	/**
	 * Maybe show the admin notice.
	 *
	 * @return void
	 */
	public function maybe_show_notice(): void {
		// Only show to users who can manage WooCommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Don't show if already dismissed.
		if ( get_option( self::NOTICE_DISMISSED_OPTION, false ) ) {
			return;
		}

		// Don't show if remediation is already complete.
		if ( $this->is_remediation_complete() ) {
			return;
		}

		// Don't show if remediation is already running.
		if ( $this->is_remediation_running() ) {
			return;
		}

		// Only show if there are affected orders.
		if ( ! $this->has_affected_orders() ) {
			// Mark as dismissed so we don't keep checking.
			update_option( self::NOTICE_DISMISSED_OPTION, true );
			return;
		}

		$this->render_notice();
	}

	/**
	 * Check if remediation is complete.
	 *
	 * @return bool
	 */
	private function is_remediation_complete(): bool {
		return 'completed' === get_option( 'wcpay_fee_remediation_status', '' );
	}

	/**
	 * Check if remediation is currently running.
	 *
	 * @return bool
	 */
	private function is_remediation_running(): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
		return as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK );
	}

	/**
	 * Check if there are orders that need remediation.
	 *
	 * @return bool
	 */
	private function has_affected_orders(): bool {
		include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
		$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();
		return $remediation->has_affected_orders();
	}

	/**
	 * Render the admin notice.
	 *
	 * @return void
	 */
	private function render_notice(): void {
		$tools_url = admin_url( 'admin.php?page=wc-status&tab=tools' );
		?>
		<div class="notice notice-warning is-dismissible wcpay-canceled-auth-notice">
			<p>
				<strong><?php esc_html_e( 'WooPayments: Action Required', 'woocommerce-payments' ); ?></strong>
			</p>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: URL to WooCommerce Tools page */
						__( 'Some orders with canceled payment authorizations have incorrect data that may cause negative values in your WooCommerce Analytics. This affects stores using manual capture (authorize and capture separately). <a href="%s">Run the fix tool</a> to correct this.', 'woocommerce-payments' ),
						esc_url( $tools_url )
					),
					[ 'a' => [ 'href' => [] ] ]
				);
				?>
			</p>
		</div>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( '.wcpay-canceled-auth-notice' ).on( 'click', '.notice-dismiss', function() {
					$.post( ajaxurl, {
						action: 'wcpay_dismiss_canceled_auth_notice',
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wcpay_dismiss_canceled_auth_notice' ) ); ?>'
					} );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * AJAX handler to dismiss the notice.
	 *
	 * @return void
	 */
	public function dismiss_notice(): void {
		check_ajax_referer( 'wcpay_dismiss_canceled_auth_notice' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		update_option( self::NOTICE_DISMISSED_OPTION, true );
		wp_die();
	}
}
