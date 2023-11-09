<?php
/**
 * Admin WC Subscriptions plugin warning template.
 *
 * @package WooCommerce\Payments
 */

?>
<script type="text/template" id="tmpl-wcpay-plugin-deactivate-warning">
	<div id="wcpay-plugin-deactivate-warning-notice" class="wc-backbone-modal woopayments-plugin-warning-modal">
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
								// Translators: placeholders are opening and closing strong HTML tags. %6$s: WooPayments, %7$s: Woo Subscriptions.
								esc_html__( 'Your store has subscriptions using %6$s Stripe Billing functionality for payment processing. Due to the %1$soff-site billing engine%3$s these subscriptions use,%4$s they will continue to renew even after you deactivate %6$s%5$s.', 'woocommerce-payments' ),
								'<a href="https://woo.com/document/woopayments/built-in-subscriptions/comparison/#billing-engine" target="_blank">',
								'<a href="https://woo.com/document/woopayments/built-in-subscriptions/deactivate/#existing-subscriptions" target="_blank">',
								'</a>',
								'<strong>',
								'</strong>',
								'WooPayments',
							);
							?>
						</br>
						</br>
						<?php
							printf(
								// translators: $1 $2 placeholders are opening and closing HTML link tags, linking to documentation. $3 is WooPayments.
								esc_html__( 'If you do not want these subscriptions to continue to be billed, you should %1$scancel these subscriptions%2$s prior to deactivating %3$s.', 'woocommerce-payments' ),
								'<a href="https://woo.com/document/subscriptions/store-manager-guide/#cancel-or-suspend-subscription" target="_blank">',
								'</a>',
								'WooPayments'
							);
							?>
					</p>
					<strong>
						<?php
							printf(
								// translators: Placeholder is "Woo Subscriptions"".
								esc_html__( 'Are you sure you want to deactivate %s?', 'woocommerce-payments' ),
								'WooPayments'
							);
							?>
					</strong>
				</article>
				<footer>
					<div class="inner">
						<button class="modal-close button button-secondary button-large"><?php esc_html_e( 'Cancel', 'woocommerce-payments' ); ?></button>
						<button id="wcpay-plugin-deactivate-modal-submit" class="button button-primary button-large">
						<?php
							printf(
								// translators: Placeholder is "Woo Subscriptions"".
								esc_html__( 'Yes, deactivate %s', 'woocommerce-payments' ),
								'WooPayments'
							);
							?>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
