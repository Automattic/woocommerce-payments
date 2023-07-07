<?php
/**
 * WC_Payments_Status class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Hooks into Woo Status pages to provide extra tooling and information about WCPay.
 */
class WC_Payments_Status {
	/**
	 * Instance of WC_Payments_Http_Interface
	 *
	 * @var WC_Payments_Http_Interface
	 */
	private $http;

	/**
	 * Instance of WC_Payments_Account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Status constructor.
	 *
	 * @param WC_Payments_Http_Interface $http    A class implementing WC_Payments_Http_Interface.
	 * @param WC_Payments_Account        $account The account service.
	 */
	public function __construct( $http, $account ) {
		$this->http    = $http;
		$this->account = $account;

		add_action( 'woocommerce_system_status_report', [ $this, 'render_status_report_section' ] );
		add_filter( 'woocommerce_debug_tools', [ $this, 'debug_tools' ] );
	}

	/**
	 * Add WCPay tools to the Woo debug tools.
	 *
	 * @param array $tools List of current available tools.
	 */
	public function debug_tools( $tools ) {
		$tools['clear_wcpay_account_cache'] = [
			'name'     => sprintf(
				/* translators: %s: WooPayments */
				__( 'Clear %s account cache', 'woocommerce-payments' ),
				'WooPayments'
			),
			'button'   => __( 'Clear', 'woocommerce-payments' ),
			'desc'     => sprintf(
				/* translators: %s: WooPayments */
				__( 'This tool will clear the account cached values used in %s.', 'woocommerce-payments' ),
				'WooPayments'
			),
			'callback' => [ $this->account, 'refresh_account_data' ],
		];
		return $tools;
	}

	/**
	 * Renders WCPay information on the status page.
	 */
	public function render_status_report_section() {
		?>
			<table class="wc_status_table widefat" cellspacing="0">
				<thead>
					<tr>
						<th colspan="3" data-export-label="WooCommerce Payments">
							<h2>WooPayments</h2>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td data-export-label="Version"><?php esc_html_e( 'Version', 'woocommerce-payments' ); ?>:</td>
						<td class="help">&nbsp;</td>
						<td><?php echo esc_html( WCPAY_VERSION_NUMBER ); ?></td>
					</tr>
					<tr>
						<td data-export-label="Connected to WPCOM"><?php esc_html_e( 'Connected to WPCOM', 'woocommerce-payments' ); ?>:</td>
						<td class="help">&nbsp;</td>
						<td><?php $this->http->is_connected() ? esc_html_e( 'Yes', 'woocommerce-payments' ) : esc_html_e( 'No', 'woocommerce-payments' ); ?></td>
					</tr>
					<tr>
						<td data-export-label="Blog ID"><?php esc_html_e( 'Blog ID', 'woocommerce-payments' ); ?>:</td>
						<td class="help">&nbsp;</td>
						<td><?php echo esc_html( $this->http->is_connected() ? $this->http->get_blog_id() : '-' ); ?></td>
					</tr>
					<tr>
						<td data-export-label="Account ID"><?php esc_html_e( 'Account ID', 'woocommerce-payments' ); ?>:</td>
						<td class="help">&nbsp;</td>
						<td><?php echo esc_html( $this->account->get_stripe_account_id() ?? '-' ); ?></td>
					</tr>
				</tbody>
			</table>
		<?php
	}
}
