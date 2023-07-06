/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

const bnplPaymentMessageElement = initializeBnplSiteMessaging();

const { productPrices } = window.wcpayStripeSiteMessaging;
let { productId } = window.wcpayStripeSiteMessaging;

jQuery( function ( $ ) {
	$( '.quantity input' ).on( 'change', function ( event ) {
		const newQuantity = event.target.value;
		const price = productPrices[ productId ];
		bnplPaymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
		} );
	} );

	$( '.single_variation_wrap' ).on( 'show_variation', function (
		event,
		variation
	) {
		const quantity = $( '.quantity input' ).val();
		const variationPrice = productPrices[ variation.variation_id ];
		productId = variation.variation_id;
		bnplPaymentMessageElement.update( {
			amount: parseInt( variationPrice, 10 ) * quantity,
		} );
	} );

	$( '.reset_variations' ).on( 'click', function () {
		const quantity = $( '.quantity input' ).val();
		productId = 'base_product';
		bnplPaymentMessageElement.update( {
			amount: parseInt( productPrices.base_product, 10 ) * quantity,
		} );
	} );
} );
