<?php
/**
 * Shopper approval and receipt for the local agent purchase demo.
 *
 * @var array $view Controller-provided purchase view.
 */

defined( 'ABSPATH' ) || exit;

$demo_state = $view['state'] ?? 'error';
$demo_quote = $view['quote'] ?? array();
$demo_states = array(
	'awaiting_approval' => array( 'Review your purchase', 'Your agent has prepared this order. Check the details before you approve.' ),
	'approved'          => array( 'Purchase approved', 'Your agent can now complete this test purchase. Payment has not been confirmed yet.' ),
	'paid'              => array( 'Your order is confirmed', 'Your test payment is complete. You can return to your agent.' ),
	'expired'           => array( 'This quote has expired', 'Ask your agent for a fresh quote, then review the updated order.' ),
	'changed'           => array( 'Your order needs another look', 'The purchase details have changed. Ask your agent for a new quote before approving.' ),
	'cancelled'         => array( 'Purchase cancelled', 'You did not approve this purchase. Your agent cannot pay using this approval.' ),
	'error'             => array( 'We could not show this purchase', 'Return to your agent to check the purchase status before trying again.' ),
);
$demo_copy = $demo_states[ $demo_state ] ?? $demo_states['error'];
$demo_currency = (string) ( $demo_quote['currency'] ?? 'USD' );
$demo_price = static function ( $amount ) use ( $demo_currency ): string {
	return wc_price( (float) $amount, array( 'currency' => $demo_currency ) );
};
$demo_address = array_filter( array_map( 'strval', array_intersect_key( $view['address'] ?? array(), array_flip( array( 'address_1', 'city', 'state', 'postcode', 'country' ) ) ) ) );
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php if ( 'approved' === $demo_state ) : ?>
		<meta http-equiv="refresh" content="3">
	<?php endif; ?>
	<title><?php echo esc_html( $demo_copy[0] ); ?> — WooPayments test store</title>
	<link rel="stylesheet" href="<?php echo esc_url( $view['css_url'] ); ?>">
</head>
<body>
	<div class="demo-shell">
		<header class="store-header">
			<span class="store-name">WooPayments test store</span>
			<span class="test-badge">Test mode</span>
		</header>
		<main class="receipt">
			<p class="test-notice">This is a test purchase. No real money will be charged.</p>
			<div class="receipt-content">
				<div class="purchase-status<?php echo 'paid' === $demo_state ? ' purchase-status-success' : ''; ?>" role="status">
					<?php if ( 'paid' === $demo_state ) : ?><span class="confirmation-mark" aria-hidden="true">✓</span><?php endif; ?>
					<h1><?php echo esc_html( $demo_copy[0] ); ?></h1>
					<p><?php echo esc_html( $demo_copy[1] ); ?></p>
				</div>
				<?php if ( ! empty( $view['message'] ) ) : ?>
					<p class="purchase-message"><?php echo esc_html( $view['message'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $demo_quote ) ) : ?>
					<section class="order-summary" aria-label="Order summary">
						<div class="product-line">
							<h2><?php echo esc_html( $demo_quote['product_name'] ?? 'Your product' ); ?></h2>
							<span class="quantity">Qty <?php echo esc_html( $demo_quote['quantity'] ?? 1 ); ?></span>
						</div>
						<dl class="price-breakdown">
							<div><dt>Subtotal</dt><dd><?php echo wp_kses_post( $demo_price( $demo_quote['subtotal'] ?? 0 ) ); ?></dd></div>
							<div><dt>Shipping</dt><dd><?php echo wp_kses_post( $demo_price( $demo_quote['shipping_total'] ?? 0 ) ); ?></dd></div>
							<div><dt>Tax</dt><dd><?php echo wp_kses_post( $demo_price( $demo_quote['tax_total'] ?? 0 ) ); ?></dd></div>
							<div class="total-line"><dt>Total <span class="currency"><?php echo esc_html( $demo_currency ); ?></span></dt><dd><?php echo wp_kses_post( $demo_price( $demo_quote['total'] ?? 0 ) ); ?></dd></div>
						</dl>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $demo_address ) ) : ?>
					<section class="delivery" aria-labelledby="delivery-heading">
						<h2 id="delivery-heading">Deliver to</h2>
						<address><?php echo implode( '<br>', array_map( 'esc_html', $demo_address ) ); ?></address>
					</section>
				<?php endif; ?>
				<?php if ( 'awaiting_approval' === $demo_state ) : ?>
					<div class="approval-actions">
						<p id="consent-description">Approving lets your agent complete this order for the total shown. Approval alone does not take payment.</p>
						<form method="post" action="<?php echo esc_url( $view['form_action'] ); ?>" aria-describedby="consent-description">
							<input type="hidden" name="quote_id" value="<?php echo esc_attr( $view['quote_id'] ); ?>">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $view['nonce'] ); ?>">
							<button class="button-approve" type="submit" name="decision" value="approve">Approve <?php echo wp_kses_post( $demo_price( $demo_quote['total'] ?? 0 ) ); ?> test purchase</button>
							<button class="button-cancel" type="submit" name="decision" value="cancel">Cancel purchase</button>
						</form>
					</div>
				<?php elseif ( 'approved' === $demo_state ) : ?>
					<p class="next-step">Return to your agent to finish the purchase. This page checks for confirmation every few seconds.</p>
				<?php elseif ( 'paid' === $demo_state && ! empty( $view['order_id'] ) ) : ?>
					<p class="order-reference">Order #<?php echo esc_html( $view['order_id'] ); ?></p>
				<?php endif; ?>
			</div>
		</main>
		<footer class="store-footer">Test payments handled by WooPayments</footer>
	</div>
</body>
</html>
