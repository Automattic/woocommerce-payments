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
	const quantity = $( '.quantity input' ).val();
	const { variationsPriceList } = window.wcpayStripeSiteMessaging;

	$( '.single_variation_wrap' ).on( 'show_variation', function (
		event,
		variation
	) {
		const variationPrice = variationsPriceList[ variation.variation_id ];
		window.wcpayStripeSiteMessaging.price = variationPrice;
		bnplPaymentMessageElement.update( {
			amount: parseInt( variationPrice, 10 ) * quantity,
		} );
	} );

	$( '.reset_variations' ).on( 'click', function () {
		bnplPaymentMessageElement.update( {
			amount: variationsPriceList.base_product * quantity,
		} );
	} );
} );
