<?php
/**
 * Shopper approval and receipt for experimental agent purchases.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Controller-provided purchase view.
 *
 * @var array $view
 */
$view = $view ?? [];
wp_enqueue_style( 'wcpay-agent-purchases', $view['css_url'], [], WCPAY_VERSION_NUMBER );

$purchase_state    = $view['state'] ?? 'error';
$purchase_quote    = $view['quote'] ?? [];
$purchase_states   = [
	'awaiting_approval' => [ __( 'Review your purchase', 'woocommerce-payments' ), __( 'Your agent has prepared this order. Check the details before you approve.', 'woocommerce-payments' ) ],
	'approved'          => [ __( 'Purchase approved', 'woocommerce-payments' ), __( 'Your agent can now complete this test purchase. Payment has not been confirmed yet.', 'woocommerce-payments' ) ],
	'paid'              => [ __( 'Your order is confirmed', 'woocommerce-payments' ), __( 'Your test payment is complete. You can return to your agent.', 'woocommerce-payments' ) ],
	'expired'           => [ __( 'This quote has expired', 'woocommerce-payments' ), __( 'Ask your agent for a fresh quote, then review the updated order.', 'woocommerce-payments' ) ],
	'changed'           => [ __( 'Your order needs another look', 'woocommerce-payments' ), __( 'The purchase details have changed. Ask your agent for a new quote before approving.', 'woocommerce-payments' ) ],
	'cancelled'         => [ __( 'Purchase cancelled', 'woocommerce-payments' ), __( 'You did not approve this purchase. Your agent cannot pay using this approval.', 'woocommerce-payments' ) ],
	'error'             => [ __( 'We could not show this purchase', 'woocommerce-payments' ), __( 'Return to your agent to check the purchase status before trying again.', 'woocommerce-payments' ) ],
];
$purchase_copy     = $purchase_states[ $purchase_state ] ?? $purchase_states['error'];
$purchase_currency = (string) ( $purchase_quote['currency'] ?? 'USD' );
$purchase_price    = static function ( $amount ) use ( $purchase_currency ): string {
	return wc_price( (float) $amount, [ 'currency' => $purchase_currency ] );
};
$purchase_address  = array_filter( array_map( 'strval', array_intersect_key( $view['address'] ?? [], array_flip( [ 'address_1', 'city', 'state', 'postcode', 'country' ] ) ) ) );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php if ( 'approved' === $purchase_state ) : ?>
		<meta http-equiv="refresh" content="3">
	<?php endif; ?>
	<title><?php echo esc_html( $purchase_copy[0] ); ?> — <?php esc_html_e( 'WooPayments test store', 'woocommerce-payments' ); ?></title>
	<?php wp_print_styles( 'wcpay-agent-purchases' ); ?>
</head>
<body>
	<div class="demo-shell">
		<header class="store-header">
			<span class="store-name"><?php esc_html_e( 'WooPayments test store', 'woocommerce-payments' ); ?></span>
			<span class="test-badge"><?php esc_html_e( 'Test mode', 'woocommerce-payments' ); ?></span>
		</header>
		<main class="receipt">
			<p class="test-notice"><?php esc_html_e( 'This is a test purchase. No real money will be charged.', 'woocommerce-payments' ); ?></p>
			<div class="receipt-content">
				<div class="purchase-status<?php echo 'paid' === $purchase_state ? ' purchase-status-success' : ''; ?>" role="status">
					<?php
					if ( 'paid' === $purchase_state ) :
						?>
						<span class="confirmation-mark" aria-hidden="true">✓</span><?php endif; ?>
					<h1><?php echo esc_html( $purchase_copy[0] ); ?></h1>
					<p><?php echo esc_html( $purchase_copy[1] ); ?></p>
				</div>
				<?php if ( ! empty( $view['message'] ) ) : ?>
					<p class="purchase-message"><?php echo esc_html( $view['message'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $purchase_quote ) ) : ?>
					<section class="order-summary" aria-label="<?php esc_attr_e( 'Order summary', 'woocommerce-payments' ); ?>">
						<div class="product-line">
							<h2><?php echo esc_html( $purchase_quote['product_name'] ?? __( 'Your product', 'woocommerce-payments' ) ); ?></h2>
							<span class="quantity">
							<?php
							/* translators: %s: Product quantity. */
							printf( esc_html__( 'Qty %s', 'woocommerce-payments' ), esc_html( $purchase_quote['quantity'] ?? 1 ) );
							?>
							</span>
						</div>
						<dl class="price-breakdown">
							<div><dt><?php esc_html_e( 'Subtotal', 'woocommerce-payments' ); ?></dt><dd><?php echo wp_kses_post( $purchase_price( $purchase_quote['subtotal'] ?? 0 ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Shipping', 'woocommerce-payments' ); ?></dt><dd><?php echo wp_kses_post( $purchase_price( $purchase_quote['shipping_total'] ?? 0 ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Tax', 'woocommerce-payments' ); ?></dt><dd><?php echo wp_kses_post( $purchase_price( $purchase_quote['tax_total'] ?? 0 ) ); ?></dd></div>
							<div class="total-line"><dt><?php esc_html_e( 'Total', 'woocommerce-payments' ); ?> <span class="currency"><?php echo esc_html( $purchase_currency ); ?></span></dt><dd><?php echo wp_kses_post( $purchase_price( $purchase_quote['total'] ?? 0 ) ); ?></dd></div>
						</dl>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $purchase_address ) ) : ?>
					<section class="delivery" aria-labelledby="delivery-heading">
						<h2 id="delivery-heading"><?php esc_html_e( 'Deliver to', 'woocommerce-payments' ); ?></h2>
						<address><?php echo implode( '<br>', array_map( 'esc_html', $purchase_address ) ); ?></address>
					</section>
				<?php endif; ?>
				<?php if ( 'awaiting_approval' === $purchase_state ) : ?>
					<div class="approval-actions">
						<p id="consent-description"><?php esc_html_e( 'Approving lets your agent complete this order for the total shown. Approval alone does not take payment.', 'woocommerce-payments' ); ?></p>
						<form method="post" action="<?php echo esc_url( $view['form_action'] ); ?>" aria-describedby="consent-description">
							<input type="hidden" name="quote_id" value="<?php echo esc_attr( $view['quote_id'] ); ?>">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $view['nonce'] ); ?>">
							<button class="button-approve" type="submit" name="decision" value="approve">
							<?php
							/* translators: %s: Formatted purchase total. */
							echo wp_kses_post( sprintf( __( 'Approve %s test purchase', 'woocommerce-payments' ), $purchase_price( $purchase_quote['total'] ?? 0 ) ) );
							?>
							</button>
							<button class="button-cancel" type="submit" name="decision" value="cancel"><?php esc_html_e( 'Cancel purchase', 'woocommerce-payments' ); ?></button>
						</form>
					</div>
				<?php elseif ( 'approved' === $purchase_state ) : ?>
					<p class="next-step"><?php esc_html_e( 'Return to your agent to finish the purchase. This page checks for confirmation every few seconds.', 'woocommerce-payments' ); ?></p>
				<?php elseif ( 'paid' === $purchase_state && ! empty( $view['order_id'] ) ) : ?>
					<p class="order-reference">
					<?php
					/* translators: %s: Order number. */
					printf( esc_html__( 'Order #%s', 'woocommerce-payments' ), esc_html( $view['order_id'] ) );
					?>
					</p>
				<?php endif; ?>
			</div>
		</main>
		<footer class="store-footer"><?php esc_html_e( 'Test payments handled by WooPayments', 'woocommerce-payments' ); ?></footer>
	</div>
</body>
</html>
