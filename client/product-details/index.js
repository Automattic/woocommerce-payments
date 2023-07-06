/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

const bnplPaymentMessageElement = initializeBnplSiteMessaging();
let selectedVariationPrice = 0;

jQuery( function ( $ ) {
	$( '.quantity input' ).on( 'change', function ( event ) {
		const newQuantity = event.target.value;
		const price =
			selectedVariationPrice || window.wcpayStripeSiteMessaging.price;
		bnplPaymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
		} );
	} );

	// Handle BNPL payment message changes for product variation.
	const { productPrices } = window.wcpayStripeSiteMessaging;

	$( '.single_variation_wrap' ).on( 'show_variation', function (
		event,
		variation
	) {
		const quantity = $( '.quantity input' ).val();
		const variationPrice = productPrices[ variation.variation_id ];
		selectedVariationPrice = variationPrice;
		bnplPaymentMessageElement.update( {
			amount: parseInt( variationPrice, 10 ) * quantity,
		} );
	} );

	$( '.reset_variations' ).on( 'click', function () {
		const quantity = $( '.quantity input' ).val();
		selectedVariationPrice = productPrices.base_product;
		bnplPaymentMessageElement.update( {
			amount: productPrices.base_product * quantity,
		} );
	} );
} );
