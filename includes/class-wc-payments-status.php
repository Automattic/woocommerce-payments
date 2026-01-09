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
	 * Instance of WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

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
	 * @param WC_Payment_Gateway_WCPay   $gateway The main gateway instance.
	 * @param WC_Payments_Http_Interface $http    A class implementing WC_Payments_Http_Interface.
	 * @param WC_Payments_Account        $account The account service.
	 */
	public function __construct( $gateway, $http, $account ) {
		$this->gateway = $gateway;
		$this->http    = $http;
		$this->account = $account;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'woocommerce_system_status_report', [ $this, 'render_status_report_section' ], 1 );
		add_filter( 'woocommerce_debug_tools', [ $this, 'debug_tools' ] );
	}

	/**
	 * Get the locations where an express checkout method is enabled.
	 *
	 * @param string $method_id The method identifier (payment_request, woopay, amazon_pay, link).
	 * @return array List of locations where the method is enabled.
	 */
	private function get_express_checkout_method_locations( $method_id ) {
		$locations         = [ 'product', 'cart', 'checkout' ];
		$enabled_locations = [];

		foreach ( $locations as $location ) {
			$enabled_methods = $this->gateway->get_option( "express_checkout_{$location}_methods", [] );
			if ( is_array( $enabled_methods ) && in_array( $method_id, $enabled_methods, true ) ) {
				$enabled_locations[] = $location;
			}
		}

		return $enabled_locations;
	}

	/**
	 * Add WCPay tools to the Woo debug tools.
	 *
	 * @param array $tools List of current available tools.
	 */
	public function debug_tools( $tools ) {
		return array_merge(
			$tools,
			[
				'clear_wcpay_account_cache'            => [
					'name'     => sprintf(
						/* translators: %s: WooPayments */
						__( 'Clear %s account cache', 'woocommerce-payments' ),
						'WooPayments'
					),
					'button'   => __( 'Clear', 'woocommerce-payments' ),
					'desc'     => sprintf(
						/* translators: %s: WooPayments */
						__( 'This tool will clear the cached account values used in %s.', 'woocommerce-payments' ),
						'WooPayments'
					),
					'callback' => [ $this->account, 'refresh_account_data' ],
				],
				'delete_wcpay_test_orders'             => [
					'name'     => sprintf(
						/* translators: %s: WooPayments */
						__( 'Delete %s test orders', 'woocommerce-payments' ),
						'WooPayments'
					),
					'button'   => __( 'Delete', 'woocommerce-payments' ),
					'desc'     => sprintf(
						/* translators: %s: WooPayments */
						__( '<strong class="red">Note:</strong> This option deletes all test mode orders placed via %s. Orders placed via other gateways will not be affected. Use with caution, as this action cannot be undone.', 'woocommerce-payments' ),
						'WooPayments'
					),
					'callback' => [ $this, 'delete_test_orders' ],
				],
				'remediate_canceled_auth_fees_dry_run' => [
					'name'     => __( 'Preview canceled authorization fix (Dry Run)', 'woocommerce-payments' ),
					'button'   => $this->get_dry_run_button_text(),
					'desc'     => __( 'Preview what orders would be affected by the canceled authorization fix without making any changes. Results are logged to WooCommerce > Status > Logs.', 'woocommerce-payments' ),
					'callback' => [ $this, 'schedule_canceled_auth_dry_run' ],
					'disabled' => $this->is_remediation_running_or_complete(),
				],
				'remediate_canceled_auth_fees'         => [
					'name'     => __( 'Fix canceled authorization analytics', 'woocommerce-payments' ),
					'button'   => $this->get_remediation_button_text(),
					'desc'     => $this->get_remediation_description(),
					'confirm'  => __( 'This will update order metadata and delete incorrect refund records for affected orders. This fixes negative values in WooCommerce Analytics. Make sure you have a recent backup before proceeding. Continue?', 'woocommerce-payments' ),
					'callback' => [ $this, 'schedule_canceled_auth_remediation' ],
					'disabled' => $this->is_remediation_running_or_complete(),
				],
			]
		);
	}

	/**
	 * Deletes all test orders created with WooPayments.
	 *
	 * Test orders are identified by the '_wcpay_mode' meta key with value 'test'.
	 *
	 * @return string Success or error message.
	 */
	public function delete_test_orders() {
		// Add explicit capability check.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return __( 'You do not have permission to delete orders.', 'woocommerce-payments' );
		}

		try {
			// Get all orders with test mode meta.
			$test_orders = wc_get_orders(
				[
					'limit'      => -1,
					// phpcs:ignore
					'meta_key'   => WC_Payments_Order_Service::WCPAY_MODE_META_KEY,
					// phpcs:ignore
					'meta_value' => 'test',
					'return'     => 'objects',
				]
			);

			if ( empty( $test_orders ) ) {
				return __( 'No test orders found.', 'woocommerce-payments' );
			}

			$deleted_count = 0;
			foreach ( $test_orders as $order ) {
				// Permanently delete the order (skip trash).
				if ( $order->delete( true ) ) {
					++$deleted_count;
				}
			}

			return sprintf(
				/* translators: %d: number of orders deleted */
				_n(
					'%d test order has been permanently deleted.',
					'%d test orders have been permanently deleted.',
					$deleted_count,
					'woocommerce-payments'
				),
				$deleted_count
			);
		} catch ( Exception $e ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Error deleting test orders: %s', 'woocommerce-payments' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Schedules the canceled authorization fee remediation.
	 *
	 * This tool fixes incorrect refund records and fee data from orders where
	 * payment authorization was canceled but never captured.
	 *
	 * @return string Success or error message.
	 */
	public function schedule_canceled_auth_remediation() {
		// Add explicit capability check.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return __( 'You do not have permission to run this tool.', 'woocommerce-payments' );
		}

		try {
			include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
			$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();

			// Check if already complete.
			if ( $remediation->is_complete() ) {
				return __( 'Remediation has already been completed.', 'woocommerce-payments' );
			}

			// Check if already running (either full action or dry run).
			if ( function_exists( 'as_has_scheduled_action' ) ) {
				if ( as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK ) ||
					as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::DRY_RUN_ACTION_HOOK ) ) {
					return __( 'Remediation is already in progress. Check the Action Scheduler for status.', 'woocommerce-payments' );
				}
			}

			// Schedule the remediation.
			$remediation->schedule_remediation();

			return __( 'Remediation has been scheduled and will run in the background. You can monitor progress in the Action Scheduler.', 'woocommerce-payments' );

		} catch ( Exception $e ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Error scheduling remediation: %s', 'woocommerce-payments' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Schedules the canceled authorization fee remediation dry run.
	 *
	 * This previews what orders would be affected without making changes.
	 *
	 * @return string Success or error message.
	 */
	public function schedule_canceled_auth_dry_run() {
		// Add explicit capability check.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return __( 'You do not have permission to run this tool.', 'woocommerce-payments' );
		}

		try {
			include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
			$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();

			// Check if already complete.
			if ( $remediation->is_complete() ) {
				return __( 'Remediation has already been completed.', 'woocommerce-payments' );
			}

			// Check if already running.
			if ( function_exists( 'as_has_scheduled_action' ) ) {
				if ( as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK ) ||
					as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::DRY_RUN_ACTION_HOOK ) ) {
					return __( 'Remediation is already in progress. Check the Action Scheduler for status.', 'woocommerce-payments' );
				}
			}

			// Schedule the dry run.
			$remediation->schedule_dry_run();

			return __( 'Dry run has been scheduled and will run in the background. Check WooCommerce > Status > Logs for results (source: wcpay-fee-remediation).', 'woocommerce-payments' );

		} catch ( Exception $e ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Error scheduling dry run: %s', 'woocommerce-payments' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Get the button text for the dry run tool based on current status.
	 *
	 * @return string Button text.
	 */
	private function get_dry_run_button_text(): string {
		$status = get_option( 'wcpay_fee_remediation_status', '' );

		if ( 'completed' === $status ) {
			return __( 'Completed', 'woocommerce-payments' );
		}

		if ( 'running' === $status || $this->is_remediation_action_scheduled() ) {
			return __( 'Running...', 'woocommerce-payments' );
		}

		return __( 'Preview', 'woocommerce-payments' );
	}

	/**
	 * Get the button text for the remediation tool based on current status.
	 *
	 * @return string Button text.
	 */
	private function get_remediation_button_text(): string {
		$status = get_option( 'wcpay_fee_remediation_status', '' );

		if ( 'completed' === $status ) {
			return __( 'Completed', 'woocommerce-payments' );
		}

		if ( 'running' === $status || $this->is_remediation_action_scheduled() ) {
			return __( 'Running...', 'woocommerce-payments' );
		}

		return __( 'Run', 'woocommerce-payments' );
	}

	/**
	 * Get the description for the remediation tool including current status.
	 *
	 * @return string Tool description with status.
	 */
	private function get_remediation_description(): string {
		$base_desc = __( 'This tool removes incorrect refund records and fee data from orders where payment authorization was canceled (not captured). This fixes negative values appearing in WooCommerce Analytics for stores using manual capture.', 'woocommerce-payments' );

		$status = get_option( 'wcpay_fee_remediation_status', '' );

		if ( 'completed' === $status ) {
			$stats      = get_option( 'wcpay_fee_remediation_stats', [] );
			$processed  = isset( $stats['processed'] ) ? (int) $stats['processed'] : 0;
			$remediated = isset( $stats['remediated'] ) ? (int) $stats['remediated'] : 0;

			if ( $processed > 0 ) {
				return sprintf(
					/* translators: 1: base description, 2: number of orders processed, 3: number of orders remediated */
					__( '%1$s <strong>Status: Completed.</strong> Processed %2$d orders, remediated %3$d.', 'woocommerce-payments' ),
					$base_desc,
					$processed,
					$remediated
				);
			}

			return sprintf(
				/* translators: %s: base description */
				__( '%s <strong>Status: Completed.</strong> No affected orders found.', 'woocommerce-payments' ),
				$base_desc
			);
		}

		if ( 'running' === $status || $this->is_remediation_action_scheduled() ) {
			$stats     = get_option( 'wcpay_fee_remediation_stats', [] );
			$processed = isset( $stats['processed'] ) ? (int) $stats['processed'] : 0;

			if ( $processed > 0 ) {
				return sprintf(
					/* translators: 1: base description, 2: number of orders processed so far */
					__( '%1$s <strong>Status: Running...</strong> Processed %2$d orders so far. Check the Action Scheduler for details.', 'woocommerce-payments' ),
					$base_desc,
					$processed
				);
			}

			return sprintf(
				/* translators: %s: base description */
				__( '%s <strong>Status: Running...</strong> Check the Action Scheduler for details.', 'woocommerce-payments' ),
				$base_desc
			);
		}

		return $base_desc;
	}

	/**
	 * Check if the remediation is currently running or already complete.
	 *
	 * @return bool True if running or complete.
	 */
	private function is_remediation_running_or_complete(): bool {
		$status = get_option( 'wcpay_fee_remediation_status', '' );

		if ( 'completed' === $status || 'running' === $status ) {
			return true;
		}

		return $this->is_remediation_action_scheduled();
	}

	/**
	 * Check if the remediation action is scheduled in Action Scheduler.
	 *
	 * @return bool True if action is scheduled.
	 */
	private function is_remediation_action_scheduled(): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
		return as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK );
	}

	/**
	 * Renders WCPay information on the status page.
	 */
	public function render_status_report_section() {
		?>
	<table class="wc_status_table widefat" cellspacing="0">
		<thead>
			<tr>
				<th colspan="3" data-export-label="WooPayments">
					<h2>WooPayments</h2>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td data-export-label="Version"><?php esc_html_e( 'Version', 'woocommerce-payments' ); ?>:</td>
				<td class="help">
					<?php
					/* translators: %s: WooPayments */
					echo wc_help_tip( sprintf( esc_html__( 'The current version of the %s extension.', 'woocommerce-payments' ), 'WooPayments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */
					?>
				</td>
				<td><?php echo esc_html( WCPAY_VERSION_NUMBER ); ?></td>
			</tr>
			<tr>
				<td data-export-label="Connected to WPCOM"><?php esc_html_e( 'Connected to WPCOM', 'woocommerce-payments' ); ?>:</td>
				<td class="help">
					<?php
					/* translators: %s: WooPayments */
					echo wc_help_tip( sprintf( esc_html__( 'Can your store connect securely to wordpress.com? Without a proper WPCOM connection %s can\'t function!', 'woocommerce-payments' ), 'WooPayments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */
					?>
				</td>
				<td><?php echo $this->http->is_connected() ? esc_html__( 'Yes', 'woocommerce-payments' ) : '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'No', 'woocommerce-payments' ) . '</mark>'; ?></td>
			</tr>
			<?php if ( $this->http->is_connected() ) : ?>
				<tr>
					<td data-export-label="WPCOM Blog ID"><?php esc_html_e( 'WPCOM Blog ID', 'woocommerce-payments' ); ?>:</td>
					<td class="help"><?php echo wc_help_tip( esc_html__( 'The corresponding wordpress.com blog ID for this store.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
					<td><?php echo esc_html( $this->http->is_connected() ? $this->http->get_blog_id() : '-' ); ?></td>
				</tr>
				<tr>
					<td data-export-label="Account ID"><?php esc_html_e( 'Account ID', 'woocommerce-payments' ); ?>:</td>
					<td class="help"><?php echo wc_help_tip( esc_html__( 'The merchant account ID you are currently using to process payments with.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
					<td><?php echo $this->gateway->is_connected() ? esc_html( $this->account->get_stripe_account_id() ?? '-' ) : '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Not connected', 'woocommerce-payments' ) . '</mark>'; ?></td>
				</tr>
				<?php
				// Only display the rest if the payment gateway is connected since many places check for this and we might get inaccurate data.
				if ( $this->gateway->is_connected() ) :
					?>
					<tr>
						<td data-export-label="Payment Gateway"><?php esc_html_e( 'Payment Gateway', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Is the payment gateway ready and enabled for use on your store?', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td><?php echo $this->gateway->needs_setup() ? '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Needs setup', 'woocommerce-payments' ) . '</mark>' : ( $this->gateway->is_enabled() ? esc_html__( 'Enabled', 'woocommerce-payments' ) : esc_html__( 'Disabled', 'woocommerce-payments' ) ); ?></td>
					</tr>
					<tr>
						<td data-export-label="Test Mode"><?php esc_html_e( 'Test Mode', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the payment gateway has test payments enabled or not.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td><?php WC_Payments::mode()->is_test() ? esc_html_e( 'Enabled', 'woocommerce-payments' ) : esc_html_e( 'Disabled', 'woocommerce-payments' ); ?></td>
					</tr>
					<tr>
						<td data-export-label="Enabled APMs"><?php esc_html_e( 'Enabled APMs', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'What payment methods are enabled for the store.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td><?php echo esc_html( implode( ',', $this->gateway->get_upe_enabled_payment_method_ids() ) ); ?></td>
					</tr>

					<?php if ( ! WC_Payments_Features::is_woopay_express_checkout_enabled() ) : ?>
					<tr>
						<td data-export-label="WooPay"><?php esc_html_e( 'WooPay Express Checkout', 'woocommerce-payments' ); ?>:</td>
						<td class="help">
							<?php
							/* translators: %s: WooPayments */
							echo wc_help_tip( sprintf( esc_html__( 'WooPay is not available, as a %s feature, or the store is not yet eligible.', 'woocommerce-payments' ), 'WooPayments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */
							?>
						</td>
						<td><?php echo ! WC_Payments_Features::is_woopay_eligible() ? esc_html__( 'Not eligible', 'woocommerce-payments' ) : esc_html__( 'Not active', 'woocommerce-payments' ); ?></td>
					</tr>
<?php else : ?>
					<tr>
						<td data-export-label="WooPay"><?php esc_html_e( 'WooPay Express Checkout', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the new WooPay Express Checkout is enabled or not.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td>
						<?php
						$woopay_enabled_locations = $this->get_express_checkout_method_locations( 'woopay' );
						$woopay_enabled_locations = empty( $woopay_enabled_locations ) ? 'no locations enabled' : implode( ',', $woopay_enabled_locations );
						echo esc_html( WC_Payments_Features::is_woopay_enabled() ? __( 'Enabled', 'woocommerce-payments' ) . ' (' . $woopay_enabled_locations . ')' : __( 'Disabled', 'woocommerce-payments' ) );
						?>
						</td>
					</tr>
					<tr>
						<td data-export-label="WooPay Incompatible Extensions"><?php esc_html_e( 'WooPay Incompatible Extensions', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether there are extensions active that are have known incompatibilities with the functioning of the new WooPay Express Checkout.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td><?php get_option( \WCPay\WooPay\WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, false ) ? esc_html_e( 'Yes', 'woocommerce-payments' ) : esc_html_e( 'No', 'woocommerce-payments' ); ?></td>
					</tr>
<?php endif; ?>

					<tr>
						<td data-export-label="Apple Pay / Google Pay"><?php esc_html_e( 'Apple Pay / Google Pay Express Checkout', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the store has Payment Request enabled or not.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td>
						<?php
						$payment_request_enabled           = $this->gateway->is_payment_request_enabled();
						$payment_request_enabled_locations = $this->get_express_checkout_method_locations( 'payment_request' );
						$payment_request_enabled_locations = empty( $payment_request_enabled_locations ) ? 'no locations enabled' : implode( ',', $payment_request_enabled_locations );
						echo esc_html( $payment_request_enabled ? __( 'Enabled', 'woocommerce-payments' ) . ' (' . $payment_request_enabled_locations . ')' : __( 'Disabled', 'woocommerce-payments' ) );
						?>
						</td>
					</tr>
					<tr>
						<td data-export-label="Fraud Protection Level"><?php esc_html_e( 'Fraud Protection Level', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'The current fraud protection level the payment gateway is using.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td><?php echo esc_html( $this->gateway->get_option( 'current_protection_level' ) ); ?></td>
					</tr>
					<?php if ( $this->gateway->get_option( 'current_protection_level' ) === 'advanced' ) : ?>
					<tr>
						<td data-export-label="Enabled Fraud Filters"><?php esc_html_e( 'Enabled Fraud Filters', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'The advanced fraud protection filters currently enabled.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td>
							<?php
							// Process the list.
							$adv_fraud_settings = json_decode( wp_json_encode( $this->gateway->get_option( 'advanced_fraud_protection_settings' ) ), true );
							$list               = array_filter(
								array_map(
									function ( $rule ) {
										if ( empty( $rule['key'] ) ) {
											return null;
										}

										switch ( $rule['key'] ) {
											case 'avs_verification':
												return 'AVS Verification';
											case 'international_ip_address':
												return 'International IP Address';
											case 'ip_address_mismatch':
												return 'IP Address Mismatch';
											case 'address_mismatch':
												return 'Address Mismatch';
											case 'purchase_price_threshold':
												return 'Purchase Price Threshold';
											case 'order_items_threshold':
												return 'Order Items Threshold';
											default:
												// Ignore all others.
												return null;
										}
									},
									$adv_fraud_settings
								)
							);

							echo empty( $list ) ? '-' : esc_html( implode( ',', $list ) );
							?>
						</td>
					</tr>
<?php endif; ?>

					<tr>
						<td data-export-label="Multi-currency"><?php esc_html_e( 'Multi-currency', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the store has the Multi-currency feature enabled or not.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td><?php WC_Payments_Features::is_customer_multi_currency_enabled() ? esc_html_e( 'Enabled', 'woocommerce-payments' ) : esc_html_e( 'Disabled', 'woocommerce-payments' ); ?></td>
					</tr>
					<tr>
						<td data-export-label="Auth and Capture"><?php esc_html_e( 'Auth and Capture', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the store has the Auth & Capture feature enabled or not.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td>
						<?php
							$manual_capture_enabled = 'yes' === $this->gateway->get_option( 'manual_capture' );
							echo $manual_capture_enabled ? esc_html__( 'Enabled', 'woocommerce-payments' ) : esc_html__( 'Disabled', 'woocommerce-payments' );
						?>
						</td>
					</tr>
					<tr>
						<td data-export-label="Documents"><?php esc_html_e( 'Documents', 'woocommerce-payments' ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the tax documents section is enabled or not.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
						<td><?php WC_Payments_Features::is_documents_section_enabled() ? esc_html_e( 'Enabled', 'woocommerce-payments' ) : esc_html_e( 'Disabled', 'woocommerce-payments' ); ?></td>
					</tr>
<?php endif; // Gateway connected. ?>
<?php endif; // Connected to WPCOM. ?>
			<tr>
				<td data-export-label="Logging"><?php esc_html_e( 'Logging', 'woocommerce-payments' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether debug logging is enabled and working or not.', 'woocommerce-payments' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php \WCPay\Logger::can_log() ? esc_html_e( 'Enabled', 'woocommerce-payments' ) : esc_html_e( 'Disabled', 'woocommerce-payments' ); ?></td>
			</tr>
		</tbody>
	</table>
		<?php
	}
}
