<?php
/**
 * Admin WC Subscriptions plugin warning template.
 *
 * @package WooCommerce\Payments
 */

?>
<script type="text/template" id="tmpl-wcpay-subscriptions-plugin-warning">
	<div id="wcpay-subscriptions-plugin-warning-notice" class="wc-backbone-modal">
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
								// Translators: placeholders are opening and closing strong HTML tags. %5$s: WooPayments, %6$s: WooCommerce Subscriptions.
								esc_html__( 'By deactivating the %1$s%6$s%2$s plugin, your store will switch to using the subscriptions functionality %1$sbuilt into %5$s%2$s. %1$s%3$sLearn more.%4$s%2$s', 'woocommerce-payments' ),
								'<strong>',
								'</strong>',
								'<a href="https://woocommerce.com/document/subscriptions/renewal-process/#section-4" target="_blank" rel="noopener noreferrer">',
								'</a>',
								'WooPayments',
								'WooCommerce Subscriptions'
							);
							?>
						</br>
						</br>
						<?php
							printf(
								// Translators: $1 and $2 placeholders are opening and closing strong HTML tags. $3 and $4 are opening and closing link HTML tags.
								esc_html__( 'Existing subscribers will need to pay for their next renewal manually, after which automatic payments will resume. You will also no longer have access to the %1$s%3$sadvanced features%4$s%2$s of WooCommerce Subscriptions.', 'woocommerce-payments' ),
								'<strong>',
								'</strong>',
								'<a href="https://woocommerce.com/document/woocommerce-payments/built-in-subscriptions/comparison/" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
							?>
					</p>
					<strong><?php esc_html_e( 'Are you sure you want to deactivate WooCommerce Subscriptions?', 'woocommerce-payments' ); ?></strong>
				</article>
				<footer>
					<div class="inner">
						<button class="modal-close button button-secondary button-large"><?php esc_html_e( 'Cancel', 'woocommerce-payments' ); ?></button>
						<button id="wcpay-subscriptions-plugin-deactivation-submit" class="button button-primary button-large"><?php esc_html_e( 'Yes, deactivate WooCommerce Subscriptions', 'woocommerce-payments' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
