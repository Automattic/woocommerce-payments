<?php
/**
 * Admin WC Subscriptions plugin warning template.
 *
 * @package WooCommerce\Payments
 */

?>
<script type="text/template" id="tmpl-wcpay-plugin-deactivate-warning">
	<div id="wcpay-plugin-deactivate-warning-notice" class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Are you sure?', 'woocommerce-payments' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text">Close modal panel</span>
					</button>
				</header>
				<article>
					<p>
						<?php
							printf(
								// Translators: $1 $2 are placeholders are opening and closing HTML link tags, linking to documentation.
								esc_html__( 'Your store has active WCPay Subscriptions, using an %1$soff-site billing engine%2$s. If you deactivate WooCommerce Payments, these subscriptions will continue to renew and collect payments from customers.', 'woocommerce-payments' ),
								'<a href="https://woocommerce.com/document/payments/subscriptions/comparison/#billing-engine">',
								'</a>'
							);
							?>
					<p>
					</p>
						<?php
							printf(
								// Translators: $1 $2 placeholders are opening and closing strong HTML tags.
								// Translators: $3 $4 placeholders are opening and closing HTML link tags, linking to documentation.
								esc_html__( 'If you do not want payments to continue to be processed, %1$scancel all subscriptions%2$s before deactivating WooCommerce Payments. %3$sLearn more.%4$s', 'woocommerce-payments' ),
								'<strong>',
								'</strong>',
								'<a href="https://woocommerce.com/document/woocommerce-payments/built-in-subscriptions/deactivate/#existing-subscriptions">',
								'</a>'
							);
							?>
					</p>
					<strong><?php esc_html_e( 'Are you sure you want to deactivate WooCommerce Payments?', 'woocommerce-payments' ); ?></strong>
				</article>
				<footer>
					<div class="inner">
						<button id="wcpay-plugin-deactivate-modal-submit" class="button button-primary button-large"><?php esc_html_e( 'Yes, deactivate WooCommerce Payments', 'woocommerce-payments' ); ?></button>
						<button class="modal-close button button-secondary button-large"><?php esc_html_e( 'Cancel', 'woocommerce-payments' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
