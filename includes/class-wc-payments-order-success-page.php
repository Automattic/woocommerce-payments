<?php
/**
 * Class WC_Payments_Order_Success_Page
 *
 * @package WooCommerce\Payments
 */

/**
 * Class handling order success page.
 */
class WC_Payments_Order_Success_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'show_woopay_thankyou_notice' ], 10, 2 );
		add_action( 'woocommerce_thankyou_woocommerce_payments', [ $this, 'show_woopay_payment_method_name' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}


	/**
	 * Return the WooPay thank you notice when the order was created via WooPay
	 *
	 * @param string        $text  the default thank you text.
	 * @param WC_Order|null $order the order being shown.
	 *
	 * @return string
	 */
	public function show_woopay_thankyou_notice( $text, $order ) {
		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return $text;
		}

		return '<div class="thankyou-notice-woopay">' . __( 'Thank you! We’ve received your order.', 'woocommerce-payments' ) . '</div>';
	}


	/**
	 * Add a WooPay logo and card last 4 to the payment method name in the success page.
	 *
	 * @param int $order_id the order id.
	 */
	public function show_woopay_payment_method_name( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return;
		}
		?>
		<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details woopay">
			<li class="woocommerce-order-overview__payment-method method">
				<?php esc_html_e( 'Payment method:', 'woocommerce-payments' ); ?>
				<strong>
					<div class="wc-payment-gateway-method-name-woopay-wrapper">
						<img alt="WooPay" src="<?php echo esc_url_raw( plugins_url( 'assets/images/woopay.svg', WCPAY_PLUGIN_FILE ) ); ?>">
						<?php
						if ( $order->get_meta( 'last4' ) ) {
							echo esc_html_e( 'Card ending in', 'woocommerce-payments' ) . ' ';
							echo esc_html( $order->get_meta( 'last4' ) );
						}
						?>
					</div>
				</strong>
			</li>
		</ul>

		<?php
	}

	/**
	 * Enqueue style to the order success page
	 */
	public function enqueue_scripts() {
		if ( ! is_order_received_page() ) {
			return;
		}

		wp_enqueue_style(
			'wcpay-success-css',
			plugins_url( 'assets/css/success.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/success.css' )
		);
	}
}
