/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

const bnplPaymentMessageElement = initializeBnplSiteMessaging();

jQuery( function ( $ ) {
	$( '.quantity input' ).on( 'change', function ( event ) {
		const newQuantity = event.target.value;
		const price = window.wcpayStripeSiteMessaging.price;
		bnplPaymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
		} );
	} );

	// Handle BNPL payment message changes for product variation.
	$( '.single_variation_wrap' ).on( 'show_variation', function (
		event,
		variation
	) {
		const { variationsPriceList } = window.wcpayStripeSiteMessaging;
		const variationPrice = variationsPriceList[ variation.variation_id ];
		const quantity = $( '.quantity input' ).val();

		window.wcpayStripeSiteMessaging.price = variationPrice;
		bnplPaymentMessageElement.update( {
			amount: parseInt( variationPrice, 10 ) * quantity,
		} );
	} );

	$( '.reset_variations' ).on( 'click', function () {
		bnplPaymentMessageElement.update( {
			amount: 0,
		} );
	} );
} );
