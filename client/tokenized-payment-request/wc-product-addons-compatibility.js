/* global jQuery */

/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

addFilter(
	'wcpay.payment-request.cart-add-item',
	'automattic/wcpay/payment-request',
	( productData ) => {
		const data = [];

		const formData = jQuery( 'form.cart' ).serializeArray();
		jQuery.each( formData, ( index, { name, value } ) => {
			if ( ! /^(addon-|wc_)/.test( name ) ) {
				return;
			}

			// tests for a field name that has `[]` at the end (meaning: an array).
			if ( ! /\[\]$/.test( name ) ) {
				// if it's not an array, just add the value.
				data[ name ] = value;

				return;
			}

			// getting the field's name by removing the square brackets at the end.
			const fieldName = name.substring( 0, name.length - 2 );
			// if the value to send exists already, just add this value to it.
			if ( data[ fieldName ] ) {
				data[ fieldName ].push( value );
			} else {
				// otherwise, create an array out of it and add the current value.
				data[ fieldName ] = [ value ];
			}
		} );

		return {
			// TODO ~FR: where do we want to put this `data`?
			//  Product add-ons isn't compatible with the `add-to-cart` endpoint in the Store API.
			...productData,
		};
	}
);
