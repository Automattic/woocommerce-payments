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
								// Translators: placeholders are opening and closing strong HTML tags.
								esc_html__( 'By deactivating the %1$sWooCommerce Subscriptions%2$s plugin, your store will switch to %1$sSubscriptions powered by WooCommerce Payments%2$s.', 'woocommerce-payments' ),
								'<strong>',
								'</strong>'
							);
							?>
						</br>
						<?php
							printf(
								// Translators: $1 and $2 placeholders are opening and closing strong HTML tags. $3 and $4 are opening and closing link HTML tags. $5 is an opening link HTML tag.
								esc_html__( 'Existing subscriptions will %1$s%3$srenew manually%4$s%2$s - subscribers will need to log in to pay for renewal. Access to premium features will also be removed. %5$sLearn more.%4$s', 'woocommerce-payments' ),
								'<strong>',
								'</strong>',
								'<a href="https://woocommerce.com/document/subscriptions/renewal-process/#section-4">',
								'</a>',
								'<a href="http://woocommerce.com/document/subscriptions/deactivation/">'
							);
							?>
					</p>
					<strong><?php esc_html_e( 'Are you sure you want to deactivate WooCommerce Subscriptions?', 'woocommerce-payments' ); ?></strong>
				</article>
				<footer>
					<div class="inner">
						<button id="wcpay-subscriptions-plugin-deactivation-submit" class="button button-primary button-large"><?php esc_html_e( 'Yes, deactivate WooCommerce Subscriptions', 'woocommerce-payments' ); ?></button>
						<button class="modal-close button button-secondary button-large"><?php esc_html_e( 'Cancel', 'woocommerce-payments' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
