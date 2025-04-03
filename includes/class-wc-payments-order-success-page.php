<?php
/**
 * Class WC_Payments_Order_Success_Page
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Payment_Method;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Status;

/**
 * Class handling order success page.
 */
class WC_Payments_Order_Success_Page {


	/**
	 * Whether to hide the blocks status description.
	 *
	 * @var bool
	 */
	private $should_hide_status_description = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_order_received_verify_known_shoppers', [ $this, 'determine_woopay_order_received_verify_known_shoppers' ], 11 );
		add_action( 'woocommerce_before_thankyou', [ $this, 'register_payment_method_override' ] );
		add_action( 'woocommerce_before_thankyou', [ $this, 'maybe_render_multibanco_payment_instructions' ] );
		add_action( 'woocommerce_order_details_before_order_table', [ $this, 'unregister_payment_method_override' ] );
		add_action( 'woocommerce_order_details_before_order_table', [ $this, 'maybe_render_multibanco_payment_instructions' ] );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'add_notice_previous_paid_order' ], 11 );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'add_notice_previous_successful_intent' ], 11 );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'replace_order_received_text_for_failed_orders' ], 11 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'woocommerce_email_order_details', [ $this, 'add_multibanco_payment_instructions_to_order_on_hold_email' ], 10, 4 );
		add_action( 'wp_footer', [ $this, 'output_footer_scripts' ] );
	}

	/**
	 * Register the hook to override the payment method name on the order received page.
	 */
	public function register_payment_method_override() {
		// Override the payment method title on the order received page.
		add_filter( 'woocommerce_order_get_payment_method_title', [ $this, 'show_woocommerce_payments_payment_method_name' ], 10, 2 );
	}

	/**
	 * Maybe render the payment instructions for Multibanco payment method.
	 *
	 * @param int $order_id The order ID.
	 */
	public function maybe_render_multibanco_payment_instructions( $order_id ) {
		if ( is_order_received_page() && current_filter() === 'woocommerce_order_details_before_order_table' ) {
			// Prevent rendering twice on order received page.
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_payment_method() !== 'woocommerce_payments_' . Payment_Method::MULTIBANCO || 'on-hold' !== $order->get_status() ) {
			return;
		}

		$order_service         = WC_Payments::get_order_service();
		$multibanco_info       = $order_service->get_multibanco_info_from_order( $order );
		$unix_expiry           = $multibanco_info['expiry'];
		$expiry_date           = date_i18n( wc_date_format() . ' ' . wc_time_format(), $unix_expiry );
		$days_remaining        = max( 0, floor( ( $unix_expiry - time() ) / DAY_IN_SECONDS ) );
		$formatted_order_total = $order->get_formatted_order_total();
		wc_print_notice(
			__( 'Your order is on hold until payment is received. Please follow the payment instructions by the expiry date.', 'woocommerce-payments' ),
			'notice'
		);
		?>
		<div id="wc-payment-gateway-multibanco-instructions-container">
			<div class="card">
				<div class="card-header">
					<div class="logo-container">
						<img src="<?php echo esc_url_raw( plugins_url( 'assets/images/payment-methods/multibanco-instructions.svg', WCPAY_PLUGIN_FILE ) ); ?>" alt="<?php esc_attr_e( 'Multibanco', 'woocommerce-payments' ); ?>">
					</div>
					<div class="payment-details">
						<div class="payment-header">
							<?php
							/* translators: %s: order number */
							echo esc_html( sprintf( __( 'Order #%s', 'woocommerce-payments' ), $order->get_order_number() ) );
							?>
						</div>
						<div class="payment-expiry">
						<?php
						printf(
							WC_Payments_Utils::esc_interpolated_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							/* translators: %s: expiry date */
								__( 'Expires <strong>%s</strong>', 'woocommerce-payments' ),
								[
									'strong' => '<strong>',
								]
							),
							esc_html( $expiry_date )
						);
						?>
							<span class="badge">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of days */
									_n( '%d day', '%d days', $days_remaining, 'woocommerce-payments' ),
									$days_remaining
								)
							);
							?>
							</span>
						</div>
					</div>
				</div>

				<div class="payment-instructions">
					<p><strong><?php esc_html_e( 'Payment instructions', 'woocommerce-payments' ); ?></strong></p>
					<ol>
						<li><?php esc_html_e( 'In your online bank account or from an ATM, choose "Payment and other services".', 'woocommerce-payments' ); ?></li>
						<li><?php esc_html_e( 'Click "Payments of services/shopping".', 'woocommerce-payments' ); ?></li>
						<li><?php esc_html_e( 'Enter the entity number, reference number, and amount.', 'woocommerce-payments' ); ?></li>
					</ol>
				</div>

				<div class="payment-box">
					<div class="payment-box-row">
						<span class="payment-box-label"><?php esc_html_e( 'Entity', 'woocommerce-payments' ); ?></span>
						<button type="button" class="payment-box-value copy-btn" data-copy-value="<?php echo esc_attr( $multibanco_info['entity'] ); ?>"><?php echo esc_html( $multibanco_info['entity'] ); ?><i class="copy-icon"></i></button>
					</div>
					<div class="payment-box-row">
						<span class="payment-box-label"><?php esc_html_e( 'Reference', 'woocommerce-payments' ); ?></span>
						<button type="button" class="payment-box-value copy-btn" data-copy-value="<?php echo esc_attr( $multibanco_info['reference'] ); ?>"><?php echo esc_html( $multibanco_info['reference'] ); ?><i class="copy-icon"></i></button>
					</div>
					<div class="payment-box-row">
						<span class="payment-box-label"><?php esc_html_e( 'Amount', 'woocommerce-payments' ); ?></span>
						<button type="button" class="payment-box-value copy-btn" data-copy-value="<?php echo esc_attr( wp_strip_all_tags( $formatted_order_total ) ); ?>"><?php echo esc_html( wp_strip_all_tags( $formatted_order_total ) ); ?><i class="copy-icon"></i></button>
					</div>
				</div>

				<button type="button" class="button alt print-btn"><?php esc_html_e( 'Print', 'woocommerce-payments' ); ?></button>
				<button type="button" class="button alt copy-link-btn copy-btn" data-copy-value="<?php echo esc_attr( $multibanco_info['url'] ); ?>"><?php esc_html_e( 'Copy link for sharing', 'woocommerce-payments' ); ?><i class="copy-icon"></i></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Remove the hook to override the payment method name on the order received page before the order summary.
	 */
	public function unregister_payment_method_override() {
		remove_filter( 'woocommerce_order_get_payment_method_title', [ $this, 'show_woocommerce_payments_payment_method_name' ], 10 );
	}

	/**
	 * Hooked into `woocommerce_order_get_payment_method_title` to change the payment method title on the
	 * order received page for WooPay and BNPL orders.
	 *
	 * @param string            $payment_method_title Original payment method title.
	 * @param WC_Abstract_Order $abstract_order Successful received order being shown.
	 * @return string
	 */
	public function show_woocommerce_payments_payment_method_name( $payment_method_title, $abstract_order ) {
		// Only change the payment method title on the order received page.
		if ( ! is_order_received_page() ) {
			return $payment_method_title;
		}

		$order_id = $abstract_order->get_id();
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return $payment_method_title;
		}

		$payment_method_id = $order->get_payment_method();

		if ( stripos( $payment_method_id, 'woocommerce_payments' ) !== 0 ) {
			return $payment_method_title;
		}

		// If this is a WooPay order, return the html for the WooPay payment method name.
		if ( $order->get_meta( 'is_woopay' ) ) {
			return $this->show_woopay_payment_method_name( $order );
		}

		$gateway = WC()->payment_gateways()->payment_gateways()[ $payment_method_id ];

		if ( ! is_object( $gateway ) || ! method_exists( $gateway, 'get_payment_method' ) ) {
			return $payment_method_title;
		}

		$payment_method = $gateway->get_payment_method( $order );
		// GooglePay/ApplePay/Link/Card to be supported later.
		if ( $payment_method->get_id() === Payment_Method::CARD ) {
			return $this->show_card_payment_method_name( $order, $payment_method );
		}

		// If this is an LPM (BNPL or local payment method) order, return the html for the payment method name.
		$name_output = $this->show_lpm_payment_method_name( $gateway, $payment_method );

		if ( false !== $name_output ) {
			return $name_output;
		}

		return $payment_method_title;
	}

	/**
	 * Returns the HTML to add the card brand logo and the last 4 digits of the card used to the
	 * payment method name on the order received page.
	 *
	 * @param WC_Order                                 $order the order being shown.
	 * @param WCPay\Payment_Methods\UPE_Payment_Method $payment_method the payment method being shown.
	 *
	 * @return string
	 */
	public function show_card_payment_method_name( $order, $payment_method ) {
		$card_brand = $order->get_meta( '_card_brand' );

		if ( ! $card_brand ) {
			return $payment_method->get_title();
		}

		ob_start();
		?>
		<div class="wc-payment-gateway-method-logo-wrapper wc-payment-card-logo">
			<img alt="<?php echo esc_attr( $payment_method->get_title() ); ?>" src="<?php echo esc_url_raw( plugins_url( "assets/images/cards/{$card_brand}.svg", WCPAY_PLUGIN_FILE ) ); ?>">
			<?php
			if ( $order->get_meta( 'last4' ) ) {
				echo esc_html_e( '•••', 'woocommerce-payments' ) . ' ';
				echo esc_html( $order->get_meta( 'last4' ) );
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Returns the HTML to add the WooPay logo and the last 4 digits of the card used to the
	 * payment method name on the order received page.
	 *
	 * @param WC_Order $order the order being shown.
	 *
	 * @return string
	 */
	public function show_woopay_payment_method_name( $order ) {
		ob_start();
		?>
		<div class="wc-payment-gateway-method-logo-wrapper woopay">
			<img alt="WooPay" src="<?php echo esc_url_raw( plugins_url( 'assets/images/payment-methods/woo-short.svg', WCPAY_PLUGIN_FILE ) ); ?>">
			<?php
			if ( $order->get_meta( 'last4' ) ) {
				echo esc_html_e( 'Card ending in', 'woocommerce-payments' ) . ' ';
				echo esc_html( $order->get_meta( 'last4' ) );
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add the LPM logo to the payment method name on the order received page.
	 *
	 * @param WC_Payment_Gateway_WCPay                 $gateway the gateway being shown.
	 * @param WCPay\Payment_Methods\UPE_Payment_Method $payment_method the payment method being shown.
	 *
	 * @return string|false
	 */
	public function show_lpm_payment_method_name( $gateway, $payment_method ) {
		$method_logo_url = apply_filters_deprecated(
			'wc_payments_thank_you_page_bnpl_payment_method_logo_url',
			[
				$payment_method->get_payment_method_icon_for_location( 'checkout', false, $gateway->get_account_country() ),
				$payment_method->get_id(),
			],
			'8.5.0',
			'wc_payments_thank_you_page_lpm_payment_method_logo_url'
		);
		$method_logo_url = apply_filters(
			'wc_payments_thank_you_page_lpm_payment_method_logo_url',
			$method_logo_url,
			$payment_method->get_id()
		);

		// If we don't have a logo URL here for some reason, bail.
		if ( ! $method_logo_url ) {
			return false;
		}

		ob_start();
		?>
		<div class="wc-payment-gateway-method-logo-wrapper wc-payment-lpm-logo wc-payment-lpm-logo--<?php echo esc_attr( $payment_method->get_id() ); ?>">
			<img alt="<?php echo esc_attr( $payment_method->get_title() ); ?>" title="<?php echo esc_attr( $payment_method->get_title() ); ?>" src="<?php echo esc_url_raw( $method_logo_url ); ?>">
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add the notice to the thank you page in case a recent order with the same content has already paid.
	 *
	 * @param string $text  the default thank you text.
	 *
	 * @return string
	 */
	public function add_notice_previous_paid_order( $text ) {
		if ( isset( $_GET[ Duplicate_Payment_Prevention_Service::FLAG_PREVIOUS_ORDER_PAID ] ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			$text .= $this->format_addtional_thankyou_order_received_text(
				__( 'We detected and prevented an attempt to pay for a duplicate order. If this was a mistake and you wish to try again, please create a new order.', 'woocommerce-payments' )
			);
		}

		return $text;
	}

	/**
	 * Add the notice to the thank you page in case an existing intention was successful for the order.
	 *
	 * @param string $text  the default thank you text.
	 *
	 * @return string
	 */
	public function add_notice_previous_successful_intent( $text ) {
		if ( isset( $_GET[ Duplicate_Payment_Prevention_Service::FLAG_PREVIOUS_SUCCESSFUL_INTENT ] ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			$text .= $this->format_addtional_thankyou_order_received_text(
				__( 'We prevented multiple payments for the same order. If this was a mistake and you wish to try again, please create a new order.', 'woocommerce-payments' )
			);
		}

		return $text;
	}

	/**
	 * Formats the additional text to be displayed on the thank you page, with the side effect
	 * as a workaround for an issue in Woo core 8.1.x and 8.2.x.
	 *
	 * @param string $additional_text The additional text to be displayed.
	 *
	 * @return string Formatted text.
	 */
	private function format_addtional_thankyou_order_received_text( string $additional_text ): string {
		/**
		 * This condition is a workaround for Woo core 8.1.x and 8.2.x as it formatted the filtered text,
		 * while it should format the original text only.
		 *
		 * It's safe to remove this conditional when WooPayments requires Woo core 8.3.x or higher.
		 *
		 * @see https://github.com/woocommerce/woocommerce/pull/39758 Introduce the issue since 8.1.0.
		 * @see https://github.com/woocommerce/woocommerce/pull/40353 Fix the issue since 8.3.0.
		 */
		if (
			version_compare( WC_VERSION, '8.0', '>' )
			&& version_compare( WC_VERSION, '8.3', '<' )
		) {
			echo "
				<script type='text/javascript'>
					document.querySelector('.woocommerce-thankyou-order-received')?.classList?.add('woocommerce-info');
				</script>
			";

			return ' ' . $additional_text;
		}

		return sprintf( '<div class="woocommerce-info">%s</div>', $additional_text );
	}

	/**
	 * Replace the order received text with a failure message when the order status is 'failed'.
	 *
	 * @param string $text The original thank you text.
	 * @return string
	 */
	public function replace_order_received_text_for_failed_orders( $text ) {
		global $wp;

		$order_id = absint( $wp->query_vars['order-received'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ||
			! $order->needs_payment() ||
			0 !== strpos( $order->get_payment_method(), WC_Payment_Gateway_WCPay::GATEWAY_ID )
		) {
			return $text;
		}

		$intent_id      = $order->get_meta( '_intent_id', true );
		$payment_method = $order->get_payment_method();

		// Strip the gateway ID prefix from the payment method.
		$payment_method_type = str_replace( WC_Payment_Gateway_WCPay::GATEWAY_ID . '_', '', $payment_method );

		$should_show_failure = false;

		// Check order status first to avoid unnecessary API calls.
		if ( $order->has_status( Order_Status::FAILED ) ) {
			$should_show_failure = true;
		} elseif ( ! empty( $intent_id ) && ! empty( $payment_method_type ) && in_array( $payment_method_type, Payment_Method::REDIRECT_PAYMENT_METHODS, true ) ) {
			// For redirect-based payment methods that haven't been marked as failed yet, check the intent status.
			// Add a small delay to allow the intent to be updated.
			sleep( 1 );

			$intent        = Get_Intention::create( $intent_id );
			$intent        = $intent->send();
			$intent_status = $intent->get_status();

			if ( Intent_Status::REQUIRES_PAYMENT_METHOD === $intent_status && $intent->get_last_payment_error() ) {
				$should_show_failure = true;
			}
		}

		if ( $should_show_failure ) {
			// Store the failure state to use in wp_footer.
			$this->should_hide_status_description = true;

			$checkout_url = wc_get_checkout_url();
			return sprintf(
				/* translators: %s: checkout URL */
				__( 'Unfortunately, your order has failed. Please <a href="%s">try checking out again</a>.', 'woocommerce-payments' ),
				esc_url( $checkout_url )
			);
		}

		return $text;
	}

	/**
	 * Output any necessary footer scripts
	 */
	public function output_footer_scripts() {
		if ( ! empty( $this->should_hide_status_description ) ) {
			echo "
				<script type='text/javascript'>
					const element = document.querySelector('.wc-block-order-confirmation-status-description');
					if (element) {
						element.style.display = 'none';
					}
				</script>
			";
		}
	}

	/**
	 * Enqueue style to the order success page
	 */
	public function enqueue_scripts() {
		if ( ! is_order_received_page() && ! is_view_order_page() ) {
			return;
		}

		WC_Payments_Utils::enqueue_style(
			'wcpay-success-css',
			plugins_url( 'assets/css/success.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/success.css' ),
			'all',
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_SUCCESS_PAGE', 'dist/success', [] );
		wp_set_script_translations( 'WCPAY_SUCCESS_PAGE', 'woocommerce-payments' );
		wp_enqueue_script( 'WCPAY_SUCCESS_PAGE' );
	}

	/**
	 * Make sure we show the TYP page for orders paid with WooPay
	 * that create new user accounts, code mainly copied from
	 * WooCommerce WC_Shortcode_Checkout::order_received and
	 * WC_Shortcode_Checkout::guest_should_verify_email.
	 *
	 * @param bool $value The current value for this filter.
	 */
	public function determine_woopay_order_received_verify_known_shoppers( $value ) {
		global $wp;

		$order_id  = $wp->query_vars['order-received'];
		$order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) ) );
		$order     = wc_get_order( $order_id );

		if ( ( ! $order instanceof WC_Order ) || ! $order->get_meta( 'is_woopay' ) || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return $value;
		}

		$verification_grace_period = (int) apply_filters( 'woocommerce_order_email_verification_grace_period', 10 * MINUTE_IN_SECONDS, $order );
		$date_created              = $order->get_date_created();

		// We do not need to verify the email address if we are within the grace period immediately following order creation.
		$is_within_grace_period = is_a( $date_created, \WC_DateTime::class, true )
			&& time() - $date_created->getTimestamp() <= $verification_grace_period;

		return ! $is_within_grace_period;
	}

	/**
	 * Add Multibanco payment instructions to the order on-hold email.
	 *
	 * @param WC_Order|mixed  $order The order object.
	 * @param bool|mixed      $sent_to_admin Whether the email is being sent to the admin.
	 * @param bool|mixed      $plain_text Whether the email is plain text.
	 * @param WC_Email|string $email The email object.
	 */
	public function add_multibanco_payment_instructions_to_order_on_hold_email( $order, $sent_to_admin = false, $plain_text = false, $email = '' ): void {
		if ( ! $email instanceof WC_Email_Customer_On_Hold_Order || ! $order || $order->get_payment_method() !== 'woocommerce_payments_' . Payment_Method::MULTIBANCO ) {
			return;
		}

		$order_service         = WC_Payments::get_order_service();
		$multibanco_info       = $order_service->get_multibanco_info_from_order( $order );
		$unix_expiry           = $multibanco_info['expiry'];
		$expiry_date           = date_i18n( wc_date_format() . ' ' . wc_time_format(), $unix_expiry );
		$formatted_order_total = $order->get_formatted_order_total();

		if ( $plain_text ) {
			echo "----------------------------------------\n";
			echo esc_html__( 'Multibanco Payment instructions', 'woocommerce-payments' ) . "\n\n";
			printf(
			/* translators: %s: expiry date */
				esc_html__( 'Expires %s', 'woocommerce-payments' ) . "\n\n",
				esc_html( $expiry_date )
			);
			echo '1. ' . esc_html__( 'In your online bank account or from an ATM, choose "Payment and other services".', 'woocommerce-payments' ) . "\n";
			echo '2. ' . esc_html__( 'Click "Payments of services/shopping".', 'woocommerce-payments' ) . "\n";
			echo '3. ' . esc_html__( 'Enter the entity number, reference number, and amount.', 'woocommerce-payments' ) . "\n\n";
			echo esc_html__( 'Entity', 'woocommerce-payments' ) . ': ' . esc_html( $multibanco_info['entity'] ) . "\n";
			echo esc_html__( 'Reference', 'woocommerce-payments' ) . ': ' . esc_html( $multibanco_info['reference'] ) . "\n";
			echo esc_html__( 'Amount', 'woocommerce-payments' ) . ': ' . esc_html( wp_strip_all_tags( $formatted_order_total ) ) . "\n";
			echo "----------------------------------------\n\n";
		} else {
			?>
			<table class="td" cellspacing="0" cellpadding="6" border="1" width="100%">
				<tbody>
				<tr>
					<td class="td">
						<table cellpadding="6">
							<tr>
								<td rowspan="2" style="padding: 0 5px 0 0;">
									<div
										style="background-color: #f6f7f7; border: 1px solid rgba( 109, 109, 109, 0.16 ); border-radius: 4px; box-sizing: border-box; padding: 10px;">
										<img style="margin: 0; height: 35px; width: 35px;"
											src="<?php echo esc_url_raw( plugins_url( 'assets/images/payment-methods/multibanco-instructions.svg', WCPAY_PLUGIN_FILE ) ); ?>"
											alt="<?php esc_attr_e( 'Multibanco', 'woocommerce-payments' ); ?>">
									</div>
								</td>
								<td style="font-size: 20px; padding: 0;">
									<?php
									/* translators: %s: order number */
									echo esc_html( sprintf( __( 'Order #%s', 'woocommerce-payments' ), $order->get_order_number() ) );
									?>
								</td>
							</tr>
							<tr>
								<td style="padding: 0;">
									<?php
									printf(
										WC_Payments_Utils::esc_interpolated_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											/* translators: %s: expiry date */
											__( 'Expires <strong>%s</strong>', 'woocommerce-payments' ),
											[
												'strong' => '<strong>',
											]
										),
										esc_html( $expiry_date )
									);
									?>
								</td>
							</tr>
						</table>
						<!-- Using a paragraph to add consistent spacing between the table and the text below. -->
						<p></p>
						<p><strong><?php esc_html_e( 'Payment instructions', 'woocommerce-payments' ); ?></strong></p>
						<ol>
							<li><?php esc_html_e( 'In your online bank account or from an ATM, choose "Payment and other services".', 'woocommerce-payments' ); ?></li>
							<li><?php esc_html_e( 'Click "Payments of services/shopping".', 'woocommerce-payments' ); ?></li>
							<li><?php esc_html_e( 'Enter the entity number, reference number, and amount.', 'woocommerce-payments' ); ?></li>
						</ol>

						<table class="td" cellspacing="0" cellpadding="6" border="1" width="100%">
							<tbody>
							<tr>
								<th class="td"><?php esc_html_e( 'Entity', 'woocommerce-payments' ); ?></th>
								<td class="td"><?php echo esc_html( $multibanco_info['entity'] ); ?></td>
							</tr>
							<tr>
								<th class="td"><?php esc_html_e( 'Reference', 'woocommerce-payments' ); ?></th>
								<td class="td"><?php echo esc_html( $multibanco_info['reference'] ); ?></td>
							</tr>
							<tr>
								<th class="td"><?php esc_html_e( 'Amount', 'woocommerce-payments' ); ?></th>
								<td class="td"><?php echo esc_html( wp_strip_all_tags( $formatted_order_total ) ); ?></td>
							</tr>
							</tbody>
						</table>
					</td>
				</tr>
				</tbody>
			</table>
			<!-- Using a paragraph to add consistent spacing between the table and the text below. -->
			<p></p>
			<?php
		}
	}
}
