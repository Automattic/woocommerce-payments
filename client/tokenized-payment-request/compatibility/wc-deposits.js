/* global jQuery */
jQuery( ( $ ) => {
	// WooCommerce Deposits support.
	// Trigger the "woocommerce_variation_has_changed" event when the deposit option is changed.
	$( 'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]' ).on(
		'change',
		() => {
			$( 'form' )
				.has(
					'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
				)
				.trigger( 'woocommerce_variation_has_changed' );
		}
	);
} );
