<?php
/**
 * Class WC_Payments_Order_Success_Page
 *
 * @package WooCommerce\Payments
 */

use WCPay\Duplicate_Payment_Prevention_Service;

/**
 * Class handling order success page.
 */
class WC_Payments_Order_Success_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_order_received_verify_known_shoppers', [ $this, 'determine_woopay_order_received_verify_known_shoppers' ], 11 );
		add_action( 'woocommerce_before_thankyou', [ $this, 'register_payment_method_override' ] );
		add_action( 'woocommerce_order_details_before_order_table', [ $this, 'unregister_payment_method_override' ] );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'add_notice_previous_paid_order' ], 11 );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'add_notice_previous_successful_intent' ], 11 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Register the hook to override the payment method name on the order received page.
	 */
	public function register_payment_method_override() {
		// Override the payment method title on the order received page.
		add_filter( 'woocommerce_order_get_payment_method_title', [ $this, 'show_woocommerce_payments_payment_method_name' ], 10, 2 );
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

		// If this is a BNPL order, return the html for the BNPL payment method name.
		if ( $payment_method->is_bnpl() ) {
			$bnpl_output = $this->show_bnpl_payment_method_name( $gateway, $payment_method );

			if ( false !== $bnpl_output ) {
				return $bnpl_output;
			}
		}

		return $payment_method_title;
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
			<img alt="WooPay" src="<?php echo esc_url_raw( plugins_url( 'assets/images/woopay.svg', WCPAY_PLUGIN_FILE ) ); ?>">
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
	 * Add the BNPL logo to the payment method name on the order received page.
	 *
	 * @param WC_Payment_Gateway_WCPay                 $gateway the gateway being shown.
	 * @param WCPay\Payment_Methods\UPE_Payment_Method $payment_method the payment method being shown.
	 *
	 * @return string|false
	 */
	public function show_bnpl_payment_method_name( $gateway, $payment_method ) {
		$method_logo_url = apply_filters(
			'wc_payments_thank_you_page_bnpl_payment_method_logo_url',
			$payment_method->get_payment_method_icon_for_location( 'checkout', false, $gateway->get_account_country() ),
			$payment_method->get_id()
		);

		// If we don't have a logo URL here for some reason, bail.
		if ( ! $method_logo_url ) {
			return false;
		}

		ob_start();
		?>
		<div class="wc-payment-gateway-method-logo-wrapper wc-payment-bnpl-logo <?php echo esc_attr( $payment_method->get_id() ); ?>">
			<img alt="<?php echo esc_attr( $payment_method->get_title() ); ?>" src="<?php echo esc_url_raw( $method_logo_url ); ?>">
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
		if ( version_compare( WC_VERSION, '8.0', '>' )
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
	 * Enqueue style to the order success page
	 */
	public function enqueue_scripts() {
		if ( ! is_order_received_page() ) {
			return;
		}

		WC_Payments_Utils::enqueue_style(
			'wcpay-success-css',
			plugins_url( 'assets/css/success.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/success.css' ),
			'all',
		);
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
}
