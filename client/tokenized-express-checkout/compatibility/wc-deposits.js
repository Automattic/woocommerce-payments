/* global jQuery */
/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

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
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		const depositsData = {};
		if ( jQuery( 'input[name=wc_deposit_option]' ).length ) {
			depositsData.wc_deposit_option = jQuery(
				'input[name=wc_deposit_option]:checked'
			).val();
		}
		if ( jQuery( 'input[name=wc_deposit_payment_plan]' ).length ) {
			depositsData.wc_deposit_payment_plan = jQuery(
				'input[name=wc_deposit_payment_plan]:checked'
			).val();
		}

		return { ...productData, ...depositsData };
	}
);
