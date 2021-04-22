/**
 * Internal dependencies
 */
import request from '../../checkout/blocks/request';
import { getPaymentRequestData, getAjaxURL } from '../utils';

/**
 * Submits shipping address to get available shipping options
 * from Payment Request button.
 *
 * @param {Object} shippingAddress Shipping details.
 * @return {Promise} Promise for the request to the server.
 */
export const paymentRequestCalculateShippingOptions = ( shippingAddress ) => {
	return request( getAjaxURL( 'get_shipping_options' ), {
		security: getPaymentRequestData( 'nonce' )?.shipping,
		// eslint-disable-next-line camelcase
		is_product_page: getPaymentRequestData( 'is_product_page' ),
		...shippingAddress,
	} ).then( ( response ) => {
		return JSON.parse( response );
	} );
};

/**
 * Updates cart with selected shipping option.
 *
 * @param {integer} shippingOption Shipping option id.
 * @return {Promise} Promise for the request to the server.
 */
export const paymentRequestUpdateShippingDetails = ( shippingOption ) => {
	return request( getAjaxURL( 'update_shipping_method' ), {
		security: getPaymentRequestData( 'nonce' )?.update_shipping,
		/* eslint-disable camelcase */
		shipping_method: [ shippingOption.id ],
		is_product_page: getPaymentRequestData( 'is_product_page' ),
		/* eslint-enable camelcase */
	} ).then( ( response ) => {
		return JSON.parse( response );
	} );
};

/**
 * Creates order based on Payment Request payment method.
 *
 * @param {Object} paymentData Order data.
 * @return {Promise} Promise for the request to the server.
 */
export const paymentRequestCreateOrder = ( paymentData ) => {
	return request( getAjaxURL( 'create_order' ), {
		_wpnonce: getPaymentRequestData( 'nonce' )?.checkout,
		// eslint-disable-next-line camelcase
		...paymentData,
	} ).then( ( response ) => {
		return JSON.parse( response );
	} );
};
