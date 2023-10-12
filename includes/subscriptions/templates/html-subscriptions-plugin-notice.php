<?php
/**
 * Admin WC Subscriptions plugin warning template.
 *
 * @package WooCommerce\Payments
 */

?>
<script type="text/template" id="tmpl-wcpay-subscriptions-plugin-warning">
	<div id="wcpay-subscriptions-plugin-warning-notice" class="wc-backbone-modal woopayments-plugin-warning-modal">
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
								// Translators: %1-%4 placeholders are opening and closing a or strong HTML tags. %5$s: WooPayments, %6$s: Woo Subscriptions.
								esc_html__( 'Your store has subscriptions using %5$s Stripe Billing functionality for payment processing. Due to the %1$soff-site billing engine%2$s these subscriptions use,%3$s they will continue to renew even after you deactivate %6$s%4$s.', 'woocommerce-payments' ),
								'<a href="https://woocommerce.com/document/woopayments/subscriptions/stripe-billing/#faq" target="_blank">',
								'</a>',
								'<strong>',
								'</strong>',
								'WooPayments',
								'Woo Subscriptions'
							);
							?>
						</br>
						</br>
						<?php
							printf(
								// translators: $1 $2 placeholders are opening and closing HTML link tags, linking to documentation. $3 is WooPayments.
								esc_html__( 'If you do not want these subscriptions to continue to be billed, you should %1$scancel these subscriptions%2$s prior to deactivating %3$s.', 'woocommerce-payments' ),
								'<a href="https://woocommerce.com/document/subscriptions/store-manager-guide/#cancel-or-suspend-subscription" target="_blank">',
								'</a>',
								'Woo Subscriptions'
							);
							?>
					</p>
					<strong>
						<?php
							printf(
								// translators: Placeholder is "Woo Subscriptions"".
								esc_html__( 'Are you sure you want to deactivate %s?', 'woocommerce-payments' ),
								'Woo Subscriptions'
							);
							?>
					</strong>
				</article>
				<footer>
					<div class="inner">
						<button class="modal-close button button-secondary button-large"><?php esc_html_e( 'Cancel', 'woocommerce-payments' ); ?></button>
						<button id="wcpay-subscriptions-plugin-deactivation-submit" class="button button-primary button-large">
							<?php
								printf(
									// translators: Placeholder is "Woo Subscriptions"".
									esc_html__( 'Yes, deactivate %s', 'woocommerce-payments' ),
									'Woo Subscriptions'
								);
								?>
						</button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
